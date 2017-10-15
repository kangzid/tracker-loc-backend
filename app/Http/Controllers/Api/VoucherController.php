<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Voucher;
use Illuminate\Http\Request;

class VoucherController extends Controller
{
    /** GET /api/superadmin/vouchers */
    public function index()
    {
        return response()->json(Voucher::latest()->get());
    }

    /** POST /api/superadmin/vouchers */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'code'                => 'required|string|max:50|unique:vouchers,code',
            'discount_percentage' => 'required|integer|min:1|max:100',
            'max_uses'            => 'nullable|integer|min:0',
            'expired_at'          => 'nullable|date',
            'is_active'           => 'boolean',
        ]);

        $validated['code'] = strtoupper($validated['code']);
        $voucher = Voucher::create($validated);
        return response()->json($voucher, 201);
    }

    /** GET /api/superadmin/vouchers/{id} */
    public function show(Voucher $voucher)
    {
        return response()->json($voucher);
    }

    /** PUT /api/superadmin/vouchers/{id} */
    public function update(Request $request, Voucher $voucher)
    {
        $validated = $request->validate([
            'discount_percentage' => 'sometimes|integer|min:1|max:100',
            'max_uses'            => 'nullable|integer|min:0',
            'expired_at'          => 'nullable|date',
            'is_active'           => 'boolean',
        ]);

        $voucher->update($validated);
        return response()->json($voucher);
    }

    /** DELETE /api/superadmin/vouchers/{id} */
    public function destroy(Voucher $voucher)
    {
        $voucher->delete();
        return response()->json(['message' => 'Voucher deleted']);
    }
}
