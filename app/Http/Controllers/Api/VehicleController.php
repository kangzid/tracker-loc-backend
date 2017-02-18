<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use App\Models\Location;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class VehicleController extends Controller
{
    public function index(Request $request)
    {
        if (!$request->user()->isAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $vehicles = Vehicle::with('latestLocation')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json($vehicles);
    }

    public function store(Request $request)
    {
        if (!$request->user()->isAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'vehicle_number' => 'required|string|unique:vehicles,vehicle_number',
            'vehicle_type' => 'required|string',
            'brand' => 'nullable|string',
            'model' => 'nullable|string',
            'year' => 'nullable|integer|min:1900|max:' . (date('Y') + 1),
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $vehicle = Vehicle::create($request->all());

        return response()->json($vehicle, 201);
    }

    public function show($id)
    {
        $vehicle = Vehicle::with('latestLocation')->findOrFail($id);
        return response()->json($vehicle);
    }

    public function update(Request $request, $id)
    {
        if (!$request->user()->isAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $vehicle = Vehicle::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'vehicle_number' => 'sometimes|required|string|unique:vehicles,vehicle_number,' . $id,
            'vehicle_type' => 'sometimes|required|string',
            'brand' => 'nullable|string',
            'model' => 'nullable|string',
            'year' => 'nullable|integer|min:1900|max:' . (date('Y') + 1),
            'is_active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $vehicle->update($request->all());

        return response()->json($vehicle);
    }

    public function destroy(Request $request, $id)
    {
        if (!$request->user()->isAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $vehicle = Vehicle::findOrFail($id);
        $vehicle->delete();

        return response()->json(['message' => 'Vehicle deleted successfully']);
    }

    public function activeVehicles()
    {
        $vehicles = Vehicle::with('latestLocation')
            ->where('is_active', true)
            ->get();

        return response()->json($vehicles);
    }

    public function inactiveVehicles()
    {
        $vehicles = Vehicle::with('latestLocation')
            ->where('is_active', false)
            ->get();

        return response()->json($vehicles);
    }

    public function updateLocation(Request $request, $id)
    {
        if (!$request->user()->isAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'speed' => 'nullable|numeric',
            'accuracy' => 'nullable|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $vehicle = Vehicle::findOrFail($id);
        
        // Update vehicle table
        $vehicle->update([
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'last_location_update' => now(),
        ]);

        // Save to locations table for history
        $location = Location::create([
            'trackable_type' => Vehicle::class,
            'trackable_id' => $id,
            'latitude' => $request->latitude,
            'longitude' => $request->longitude,
            'speed' => $request->speed,
            'accuracy' => $request->accuracy,
            'recorded_at' => now(),
        ]);

        return response()->json([
            'vehicle' => $vehicle,
            'location' => $location
        ]);
    }
}