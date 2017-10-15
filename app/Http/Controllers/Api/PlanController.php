<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\Request;

class PlanController extends Controller
{
    /** GET /api/superadmin/plans */
    public function index()
    {
        return response()->json(Plan::orderBy('sort_order')->orderBy('price_monthly')->get());
    }

    /** POST /api/superadmin/plans/reorder */
    public function reorder(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:plans,id'
        ]);

        foreach ($request->ids as $index => $id) {
            Plan::where('id', $id)->update(['sort_order' => $index]);
        }

        return response()->json(['message' => 'Urutan paket berhasil disimpan']);
    }

    /** POST /api/superadmin/plans */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'           => 'required|string|max:100',
            'slug'           => 'required|string|unique:plans,slug|max:50',
            'description'    => 'nullable|string',
            'price_monthly'  => 'required|integer|min:0',
            'max_employees'  => 'required|integer|min:0',
            'max_vehicles'   => 'required|integer|min:0',
            'features'       => 'nullable|array',
            'is_active'      => 'boolean',
            'is_custom'      => 'boolean',
        ]);

        $plan = Plan::create($validated);
        return response()->json($plan, 201);
    }

    /** GET /api/superadmin/plans/{id} */
    public function show(Plan $plan)
    {
        return response()->json($plan);
    }

    /** PUT /api/superadmin/plans/{id} */
    public function update(Request $request, Plan $plan)
    {
        $validated = $request->validate([
            'name'           => 'sometimes|string|max:100',
            'description'    => 'nullable|string',
            'price_monthly'  => 'sometimes|integer|min:0',
            'max_employees'  => 'sometimes|integer|min:0',
            'max_vehicles'   => 'sometimes|integer|min:0',
            'features'       => 'nullable|array',
            'is_active'      => 'boolean',
            'is_custom'      => 'boolean',
        ]);

        $plan->update($validated);
        return response()->json($plan);
    }

    /** DELETE /api/superadmin/plans/{id} */
    public function destroy(Plan $plan)
    {
        $plan->delete();
        return response()->json(['message' => 'Plan deleted']);
    }
}
