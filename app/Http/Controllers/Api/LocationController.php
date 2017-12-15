<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\Employee;
use App\Models\Vehicle;
use App\Events\LocationUpdated;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class LocationController extends Controller
{
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'trackable_type' => 'required|in:employee,vehicle',
            'trackable_id' => 'required|integer',
            'speed' => 'nullable|numeric',
            'accuracy' => 'nullable|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Verify ownership for employee tracking
        $entityName = null;
        if ($request->trackable_type === 'employee') {
            $employee = $request->user()->employee;
            if (!$employee || $employee->id != $request->trackable_id) {
                return response()->json([
                    'message' => 'Unauthorized Location Update',
                    'debug' => [
                        'user_id' => $request->user()->id,
                        'employee_id' => $employee ? $employee->id : null,
                        'requested_id' => $request->trackable_id
                    ]
                ], 403);
            }
            $trackable = $employee;
            $entityName = $employee->user->name;
        } else {
            $trackable = Vehicle::findOrFail($request->trackable_id);
            $entityName = $trackable->vehicle_number;
        }

        // OPTIMIZATION: Use DB transaction for atomic updates
        DB::transaction(function () use ($request, $trackable, $entityName) {
            $trackableType = $request->trackable_type === 'employee' ? Employee::class : Vehicle::class;
            
            // 1. Dapatkan lokasi terakhir dari database
            $latestLocation = Location::where('trackable_type', $trackableType)
                ->where('trackable_id', $request->trackable_id)
                ->orderBy('recorded_at', 'desc')
                ->first();

            $shouldCreateNewRow = true;

            if ($latestLocation) {
                $distance = $this->calculateDistance(
                    $latestLocation->latitude, $latestLocation->longitude,
                    $request->latitude, $request->longitude
                );

                // Jika jarak kurang dari 50 meter (diam/tidak bergerak signifikan)
                if ($distance < 50) {
                    $latestLocation->update([
                        'recorded_at' => now(), // Update waktu saja
                        'speed' => $request->speed,
                        'accuracy' => $request->accuracy
                    ]);
                    $shouldCreateNewRow = false;
                }
            }

            // 2. Jika ada pergerakan signifikan, baru buat baris baru untuk history
            if ($shouldCreateNewRow) {
                Location::create([
                    'trackable_type' => $trackableType,
                    'trackable_id' => $request->trackable_id,
                    'latitude' => $request->latitude,
                    'longitude' => $request->longitude,
                    'speed' => $request->speed,
                    'accuracy' => $request->accuracy,
                    'recorded_at' => now(),
                ]);
            }

            // Update last location in trackable model
            $trackable->update([
                'latitude' => $request->latitude,
                'longitude' => $request->longitude,
                'last_location_update' => now(),
            ]);

            $tenantId = $request->trackable_type === 'employee' ? ($trackable->admin_id ?? $request->user()->id) : ($trackable->admin_id ?? $request->user()->id);

            // Broadcast location update via WebSocket untuk real-time tracking (Individual & Tenant Aggregated)
            LocationUpdated::dispatch(
                $request->trackable_type,
                $request->trackable_id,
                $request->latitude,
                $request->longitude,
                $request->speed,
                $request->accuracy,
                now(),
                $entityName,
                $tenantId
            );
        });

        return response()->json(['message' => 'Location updated successfully'], 201);
    }

    public function liveTracking(Request $request)
    {
        $user = $request->user();

        if ($user->isAdmin()) {
            // OPTIMIZATION: Eager load relationships and select only needed columns
            $adminId = $user->id;

            $employees = Employee::with(['user:id,name,email', 'latestLocation:id,trackable_type,trackable_id,latitude,longitude,recorded_at'])
                ->select('id', 'user_id', 'admin_id', 'employee_id', 'latitude', 'longitude', 'last_location_update')
                ->where('admin_id', $adminId)
                ->whereHas('user', function ($q) {
                    $q->where('is_active', true);
                })
                ->get();

            $vehicles = Vehicle::with(['latestLocation:id,trackable_type,trackable_id,latitude,longitude,recorded_at'])
                ->select('id', 'admin_id', 'vehicle_number', 'vehicle_type', 'latitude', 'longitude', 'last_location_update', 'is_active')
                ->where('admin_id', $adminId)
                ->where('is_active', true)
                ->get();
        } else {
            // Employee hanya bisa lihat dirinya sendiri dan vehicle di tenant yang sama
            $employee = $user->employee;
            if (!$employee) {
                return response()->json(['message' => 'Employee profile not found'], 404);
            }

            $employees = Employee::with(['user:id,name,email', 'latestLocation:id,trackable_type,trackable_id,latitude,longitude,recorded_at'])
                ->select('id', 'user_id', 'admin_id', 'employee_id', 'latitude', 'longitude', 'last_location_update')
                ->where('id', $employee->id)
                ->get();

            $vehicles = Vehicle::with(['latestLocation:id,trackable_type,trackable_id,latitude,longitude,recorded_at'])
                ->select('id', 'admin_id', 'vehicle_number', 'vehicle_type', 'latitude', 'longitude', 'last_location_update', 'is_active')
                ->where('admin_id', $employee->admin_id)
                ->where('is_active', true)
                ->get();
        }

        return response()->json([
            'employees' => $employees,
            'vehicles' => $vehicles
        ]);
    }

    public function employeeHistory(Request $request, $employeeId)
    {
        $user = $request->user();

        // Verify employee belongs to this admin's tenant
        if ($user->isAdmin()) {
            $employee = Employee::where('admin_id', $user->id)
                ->where('id', $employeeId)
                ->first();

            if (!$employee) {
                return response()->json(['message' => 'Employee not found or unauthorized'], 404);
            }
        } else {
            // Employee can only see their own history
            if (!$user->employee || $user->employee->id != $employeeId) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
        }

        $validator = Validator::make($request->all(), [
            'date' => 'nullable|date',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // OPTIMIZATION: Use query scope and select only needed columns
        $query = Location::select('id', 'trackable_type', 'trackable_id', 'latitude', 'longitude', 'speed', 'accuracy', 'recorded_at')
            ->forEmployee($employeeId)
            ->orderBy('recorded_at', 'desc');

        if ($request->date) {
            $query->whereDate('recorded_at', $request->date);
        } elseif ($request->start_date && $request->end_date) {
            $query->whereBetween('recorded_at', [
                $request->start_date . ' 00:00:00', 
                $request->end_date . ' 23:59:59'
            ]);
        }

        $locations = $query->get();

        // OPTIMIZATION: Downsampling (Pilar 3) - Batasi maksimal 20 titik agar memori browser web Svelte aman
        $downsampledLocations = $this->downsampleLocations($locations, 20);

        return response()->json([
            'data' => $downsampledLocations->values()
        ]);
    }

    public function vehicleHistory(Request $request, $vehicleId)
    {
        $user = $request->user();

        // Verify vehicle belongs to this admin's tenant
        if ($user->isAdmin()) {
            $vehicle = Vehicle::where('admin_id', $user->id)
                ->where('id', $vehicleId)
                ->first();

            if (!$vehicle) {
                return response()->json(['message' => 'Vehicle not found or unauthorized'], 404);
            }
        } else {
            // Employee can only see vehicles in their tenant
            $employee = $user->employee;
            if (!$employee) {
                return response()->json(['message' => 'Employee profile not found'], 404);
            }

            $vehicle = Vehicle::where('admin_id', $employee->admin_id)
                ->where('id', $vehicleId)
                ->first();

            if (!$vehicle) {
                return response()->json(['message' => 'Vehicle not found or unauthorized'], 404);
            }
        }

        $validator = Validator::make($request->all(), [
            'date' => 'nullable|date',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // OPTIMIZATION: Use query scope and select only needed columns
        $query = Location::select('id', 'trackable_type', 'trackable_id', 'latitude', 'longitude', 'speed', 'accuracy', 'recorded_at')
            ->forVehicle($vehicleId)
            ->orderBy('recorded_at', 'desc');

        if ($request->date) {
            $query->whereDate('recorded_at', $request->date);
        } elseif ($request->start_date && $request->end_date) {
            $query->whereBetween('recorded_at', [
                $request->start_date . ' 00:00:00', 
                $request->end_date . ' 23:59:59'
            ]);
        }

        $locations = $query->get();

        // OPTIMIZATION: Downsampling (Pilar 3) - Batasi maksimal 20 titik agar memori browser web Svelte aman
        $downsampledLocations = $this->downsampleLocations($locations, 20);

        return response()->json([
            'data' => $downsampledLocations->values()
        ]);
    }

    public function shareLocation(Request $request)
    {
        $employee = $request->user()->employee;
        if (!$employee) {
            return response()->json(['message' => 'Employee profile not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
            'duration' => 'nullable|integer|min:1|max:1440',
            'duration_minutes' => 'nullable|integer|min:1|max:1440',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $latitude = $request->latitude ?? $employee->latitude;
        $longitude = $request->longitude ?? $employee->longitude;

        if (is_null($latitude) || is_null($longitude)) {
            return response()->json([
                'message' => 'Lokasi terakhir tidak ditemukan. Pastikan GPS aktif dan telah memperbarui lokasi.'
            ], 400);
        }

        $duration = $request->duration ?? $request->duration_minutes ?? 60;
        $shareToken = bin2hex(random_bytes(16));
        $expiresAt = now()->addMinutes($duration);

        // Store in cache
        cache()->put("location_share_{$shareToken}", [
            'employee_id' => $employee->id,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'expires_at' => $expiresAt,
        ], $expiresAt);

        return response()->json([
            'share_token' => $shareToken,
            'share_url' => url("/api/shared-location/{$shareToken}"),
            'expires_at' => $expiresAt,
        ]);
    }

    public function getSharedLocation($token)
    {
        $locationData = cache()->get("location_share_{$token}");

        if (!$locationData) {
            return response()->json(['message' => 'Location share not found or expired'], 404);
        }

        $employee = Employee::with('user')->find($locationData['employee_id']);

        return response()->json([
            'employee' => $employee->user->name,
            'latitude' => $locationData['latitude'],
            'longitude' => $locationData['longitude'],
            'shared_at' => $locationData['expires_at']->subMinutes(request()->duration ?? 60),
        ]);
    }

    private function calculateDistance($lat1, $lon1, $lat2, $lon2)
    {
        $earthRadius = 6371000; // Meter
        $latDelta = deg2rad($lat2 - $lat1);
        $lonDelta = deg2rad($lon2 - $lon1);

        $a = sin($latDelta / 2) * sin($latDelta / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($lonDelta / 2) * sin($lonDelta / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c; // Jarak dalam meter
    }

    private function downsampleLocations($locations, $maxPoints = 20)
    {
        $count = $locations->count();
        if ($count <= $maxPoints) {
            return $locations;
        }

        $step = ceil($count / $maxPoints);
        $downsampled = collect();
        
        // Selalu sertakan titik paling baru (titik terakhir yang direkam, karena orderByDesc)
        $downsampled->push($locations->first());
        
        for ($i = $step; $i < $count - 1; $i += $step) {
            $downsampled->push($locations[$i]);
        }
        
        // Selalu sertakan titik paling awal (lokasi awal pergerakan)
        if ($count > 1) {
            $downsampled->push($locations->last());
        }

        return $downsampled;
    }
}