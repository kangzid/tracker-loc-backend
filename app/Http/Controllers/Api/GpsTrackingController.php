<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use App\Models\Location;
use App\Events\LocationUpdated;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class GpsTrackingController extends Controller
{
    /**
     * Public endpoint untuk GPS device kirim lokasi
     * Tidak perlu login, cukup pakai tracking_token
     * 
     * POST /api/gps/track
     * Headers: X-Tracking-Token: {vehicle_tracking_token}
     * Body: { latitude, longitude, speed, accuracy }
     */
    public function track(Request $request)
    {
        // Get token from header
        $token = $request->header('X-Tracking-Token');

        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'Tracking token is required in X-Tracking-Token header'
            ], 401);
        }

        // Find vehicle by token
        $vehicle = Vehicle::where('tracking_token', $token)->first();

        if (!$vehicle) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid tracking token'
            ], 401);
        }

        // Validate location data
        $validator = Validator::make($request->all(), [
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'speed' => 'nullable|numeric|min:0',
            'accuracy' => 'nullable|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // OPTIMIZATION: Distance-Based Filtering
        $latestLocation = Location::where('trackable_type', Vehicle::class)
            ->where('trackable_id', $vehicle->id)
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

        // Jika ada pergerakan signifikan, baru buat baris baru
        if ($shouldCreateNewRow) {
            Location::create([
                'trackable_type' => Vehicle::class,
                'trackable_id' => $vehicle->id,
                'latitude' => $request->latitude,
                'longitude' => $request->longitude,
                'speed' => $request->speed,
                'accuracy' => $request->accuracy,
                'recorded_at' => now(),
            ]);
        }

        // Update vehicle location
        $vehicle->update([
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'last_location_update' => now(),
        ]);

        // Broadcast real-time update to Pusher
        LocationUpdated::dispatch(
            'vehicle',
            $vehicle->id,
            $request->latitude,
            $request->longitude,
            $request->speed ?? 0,
            $request->accuracy ?? 0,
            now(),
            $vehicle->vehicle_number
        );

        return response()->json([
            'success' => true,
            'message' => 'Location updated successfully',
            'data' => [
                'vehicle_id' => $vehicle->id,
                'vehicle_number' => $vehicle->vehicle_number,
                'latitude' => $vehicle->latitude,
                'longitude' => $vehicle->longitude,
                'updated_at' => $vehicle->last_location_update,
            ]
        ], 200);
    }

    /**
     * Endpoint untuk test koneksi GPS device
     * 
     * GET /api/gps/ping
     * Headers: X-Tracking-Token: {vehicle_tracking_token}
     */
    public function ping(Request $request)
    {
        $token = $request->header('X-Tracking-Token');

        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'Tracking token is required'
            ], 401);
        }

        $vehicle = Vehicle::where('tracking_token', $token)->first();

        if (!$vehicle) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid tracking token'
            ], 401);
        }

        return response()->json([
            'success' => true,
            'message' => 'Connection successful',
            'data' => [
                'vehicle_id' => $vehicle->id,
                'vehicle_number' => $vehicle->vehicle_number,
                'vehicle_type' => $vehicle->vehicle_type,
                'is_active' => $vehicle->is_active,
                'last_update' => $vehicle->last_location_update,
            ]
        ], 200);
    }

    /**
     * Endpoint untuk GPS device cek status
     * 
     * GET /api/gps/status
     * Headers: X-Tracking-Token: {vehicle_tracking_token}
     */
    public function status(Request $request)
    {
        $token = $request->header('X-Tracking-Token');

        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'Tracking token is required'
            ], 401);
        }

        $vehicle = Vehicle::where('tracking_token', $token)
            ->with('latestLocation')
            ->first();

        if (!$vehicle) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid tracking token'
            ], 401);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'vehicle' => [
                    'id' => $vehicle->id,
                    'vehicle_number' => $vehicle->vehicle_number,
                    'vehicle_type' => $vehicle->vehicle_type,
                    'is_active' => $vehicle->is_active,
                ],
                'location' => [
                    'latitude' => $vehicle->latitude,
                    'longitude' => $vehicle->longitude,
                    'last_update' => $vehicle->last_location_update,
                ],
                'latest_tracking' => $vehicle->latestLocation,
            ]
        ], 200);
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
}
