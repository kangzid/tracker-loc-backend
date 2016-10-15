<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use App\Models\Location;
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

        // Update vehicle location
        $vehicle->update([
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'last_location_update' => now(),
        ]);

        // Save to location history
        Location::create([
            'trackable_type' => Vehicle::class,
            'trackable_id' => $vehicle->id,
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'speed' => $request->speed,
            'accuracy' => $request->accuracy,
            'recorded_at' => now(),
        ]);

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
}
