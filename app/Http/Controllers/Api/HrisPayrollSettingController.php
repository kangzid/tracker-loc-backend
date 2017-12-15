<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HrisPayrollSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class HrisPayrollSettingController extends Controller
{
    private function getTenantId(Request $request)
    {
        $user = $request->user();
        if ($user->role === 'employee') {
            return $user->employee->admin_id ?? $user->admin_id ?? 2;
        }
        return $user->id ?? 2;
    }

    public function getSettings(Request $request)
    {
        $tenantId = $this->getTenantId($request);

        $setting = HrisPayrollSetting::firstOrCreate(
            ['tenant_id' => $tenantId],
            [
                'working_days_divider_type' => 'fixed_25',
                'custom_working_days' => 25,
                'overtime_rate_multiplier' => 1.5,
                'late_deduction_rate' => 0,
                'auto_generate_payslip' => true,
            ]
        );

        return response()->json([
            'status' => 'success',
            'data' => $setting
        ]);
    }

    public function saveSettings(Request $request)
    {
        $tenantId = $this->getTenantId($request);

        $validator = Validator::make($request->all(), [
            'working_days_divider_type' => 'required|string|in:fixed_25,fixed_22,fixed_21,fixed_20,calendar_days,custom',
            'custom_working_days' => 'nullable|integer|min:1|max:31',
            'overtime_rate_multiplier' => 'nullable|numeric|min:0',
            'late_deduction_rate' => 'nullable|numeric|min:0',
            'auto_generate_payslip' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], 422);
        }

        $setting = HrisPayrollSetting::firstOrNew(['tenant_id' => $tenantId]);
        $setting->working_days_divider_type = $request->working_days_divider_type;
        $setting->custom_working_days = (int)($request->custom_working_days ?: 25);
        if ($request->has('overtime_rate_multiplier')) {
            $setting->overtime_rate_multiplier = (float)$request->overtime_rate_multiplier;
        }
        if ($request->has('late_deduction_rate')) {
            $setting->late_deduction_rate = (float)$request->late_deduction_rate;
        }
        if ($request->has('auto_generate_payslip')) {
            $setting->auto_generate_payslip = (bool)$request->auto_generate_payslip;
        }
        $setting->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Pengaturan payroll berhasil diperbarui',
            'data' => $setting
        ]);
    }
}
