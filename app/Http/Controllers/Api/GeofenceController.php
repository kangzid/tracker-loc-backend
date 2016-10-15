<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Geofence;
use Illuminate\Http\Request;

class GeofenceController extends Controller
{
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
        $geofence->update($request->all());
        return response()->json($geofence);
    }

    public function destroy(Request $request, $id)
    {
        if (!$request->user()->isAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $adminId = $request->user()->id;
        $geofence = Geofence::where('admin_id', $adminId)->findOrFail($id);
        $geofence->delete();
        return response()->json(['message' => 'Geofence deleted']);
    }
}