<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Geofence;
use App\Services\GeofenceService;
use Illuminate\Http\Request;

class GeofenceController extends Controller
{
    protected GeofenceService $geofenceService;

    public function __construct(GeofenceService $geofenceService)
    {
        $this->geofenceService = $geofenceService;
    }

    public function index(Request $request)
    {
        if (!$request->user()->isAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $adminId = $request->user()->id;
        $geofences = Geofence::where('admin_id', $adminId)->get();
        return response()->json($geofences);
    }

    public function store(Request $request)
    {
        if (!$request->user()->isAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'center_lat' => 'required|numeric',
            'center_lng' => 'required|numeric',
            'radius' => 'required|numeric|min:1',
            'type' => 'required|in:office,work_area,restricted',
        ]);

        $geofence = Geofence::create(array_merge(
            $request->all(),
            ['admin_id' => $request->user()->id]
        ));

        // Clear cached geofences for this admin
        $this->geofenceService->clearCache($request->user()->id, $geofence->type);

        return response()->json($geofence, 201);
    }

    public function show(Request $request, $id)
    {
        if (!$request->user()->isAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $adminId = $request->user()->id;
        $geofence = Geofence::where('admin_id', $adminId)->findOrFail($id);
        return response()->json($geofence);
    }

    public function update(Request $request, $id)
    {
        if (!$request->user()->isAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $adminId = $request->user()->id;
        $geofence = Geofence::where('admin_id', $adminId)->findOrFail($id);
        
        $oldType = $geofence->type;
        $geofence->update($request->all());

        // Clear cache for both old and new types
        $this->geofenceService->clearCache($adminId, $oldType);
        if ($oldType !== $geofence->type) {
            $this->geofenceService->clearCache($adminId, $geofence->type);
        }

        return response()->json($geofence);
    }

    public function destroy(Request $request, $id)
    {
        if (!$request->user()->isAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $adminId = $request->user()->id;
        $geofence = Geofence::where('admin_id', $adminId)->findOrFail($id);
        
        $type = $geofence->type;
        $geofence->delete();

        // Clear cache for deleted geofence
        $this->geofenceService->clearCache($adminId, $type);

        return response()->json(['message' => 'Geofence deleted']);
    }
}