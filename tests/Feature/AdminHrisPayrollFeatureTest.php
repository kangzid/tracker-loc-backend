<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Employee;
use App\Models\HrisPayroll;
use App\Models\HrisPayrollSetting;
use App\Models\HrisEmployeeSalary;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AdminHrisPayrollFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected $admin;
    protected $employee;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $empUser = User::factory()->create(['role' => 'employee', 'admin_id' => $this->admin->id]);
        $this->employee = Employee::create([
            'user_id' => $empUser->id,
            'admin_id' => $this->admin->id,
            'employee_id' => 'EMP-PAY1',
            'department' => 'Operations',
            'position' => 'Staff',
            'is_active' => true,
        ]);

        HrisEmployeeSalary::create([
            'tenant_id' => $this->admin->id,
            'employee_id' => $this->employee->id,
            'code' => 'SAL01',
            'wage_type' => 'Bulanan',
            'amount' => 4500000,
            'effective_date' => Carbon::today()->subMonths(1),
        ]);
    }

    public function test_payroll_settings_save_and_retrieve()
    {
        $saveRes = $this->actingAs($this->admin)->postJson('/api/hris/payroll/settings', [
            'working_days_divider_type' => 'fixed_25',
            'custom_working_days' => 25,
        ]);
        $saveRes->assertStatus(200);

        $getRes = $this->actingAs($this->admin)->getJson('/api/hris/payroll/settings');
        $getRes->assertStatus(200)
            ->assertJsonPath('data.working_days_divider_type', 'fixed_25');
    }

    public function test_monthly_payroll_generation_and_payslip_access()
    {
        $genRes = $this->actingAs($this->admin)->postJson('/api/hris/payrolls/generate-monthly', [
            'month' => (int)date('m'),
            'year' => (int)date('Y'),
            'period_start' => Carbon::today()->startOfMonth()->format('Y-m-d'),
            'period_end' => Carbon::today()->endOfMonth()->format('Y-m-d'),
        ]);
        $genRes->assertStatus(201);

        $payrollId = $genRes->json('payroll.id') ?? $genRes->json('data.id') ?? $genRes->json('id');
        $this->assertNotNull($payrollId);

        $slipsRes = $this->actingAs($this->admin)->getJson("/api/hris/payrolls/{$payrollId}/slips");
        $slipsRes->assertStatus(200);
    }
}
