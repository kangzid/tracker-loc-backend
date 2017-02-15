<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Models\Employee;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

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
        if ($request->trackable_type === 'employee') {
            $employee = $request->user()->employee;
            if (!$employee || $employee->id != $request->trackable_id) {
                return response()->json(['message' => 'Unauthorized'], 403);
            }
            $trackable = $employee;
        } else {
            $trackable = Vehicle::findOrFail($request->trackable_id);
        }

        // Update or create latest location (only keep one record per trackable)
        $location = Location::updateOrCreate(
            [
                'trackable_type' => $request->trackable_type === 'employee' ? Employee::class : Vehicle::class,
                'trackable_id' => $request->trackable_id,
            ],
            [
                'latitude' => $request->latitude,
                'longitude' => $request->longitude,
                'speed' => $request->speed,
                'accuracy' => $request->accuracy,
                'recorded_at' => now(),
            ]
        );

        // Update last location in trackable model
        $trackable->update([
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'last_location_update' => now(),
        ]);

        return response()->json($location, 201);
    }

    public function liveTracking(Request $request)
    {
        $employees = Employee::with(['user', 'latestLocation'])
            ->whereHas('user', function($q) {
                $q->where('is_active', true);
            })
            ->get();

        $vehicles = Vehicle::with('latestLocation')
            ->where('is_active', true)
            ->get();

        return response()->json([
            'employees' => $employees,
            'vehicles' => $vehicles
        ]);
    }

    public function employeeHistory(Request $request, $employeeId)
    {
        $validator = Validator::make($request->all(), [
            'date' => 'nullable|date',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $query = Location::where('trackable_type', Employee::class)
            ->where('trackable_id', $employeeId)
            ->orderBy('recorded_at', 'desc');

        if ($request->date) {
            $query->whereDate('recorded_at', $request->date);
        } elseif ($request->start_date && $request->end_date) {
            $query->whereBetween('recorded_at', [$request->start_date, $request->end_date]);
        }

        $locations = $query->paginate(100);

        return response()->json($locations);
    }

    public function vehicleHistory(Request $request, $vehicleId)
    {
        $validator = Validator::make($request->all(), [
            'date' => 'nullable|date',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $query = Location::where('trackable_type', Vehicle::class)
            ->where('trackable_id', $vehicleId)
            ->orderBy('recorded_at', 'desc');

        if ($request->date) {
            $query->whereDate('recorded_at', $request->date);
        } elseif ($request->start_date && $request->end_date) {
            $query->whereBetween('recorded_at', [$request->start_date, $request->end_date]);
        }

        $locations = $query->paginate(100);

        return response()->json($locations);
    }

    public function shareLocation(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'duration' => 'required|integer|min:1|max:1440', // max 24 hours
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $employee = $request->user()->employee;
        if (!$employee) {
            return response()->json(['message' => 'Employee profile not found'], 404);
        }

        $shareToken = bin2hex(random_bytes(16));
        $expiresAt = now()->addMinutes($request->duration);

        // Store in cache or create a temporary sharing table
        cache()->put("location_share_{$shareToken}", [
            'employee_id' => $employee->id,
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
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
}