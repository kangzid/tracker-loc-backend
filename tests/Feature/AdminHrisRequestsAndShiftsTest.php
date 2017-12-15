<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Employee;
use App\Models\HrisShift;
use App\Models\HrisShiftAssignment;
use App\Models\HrisRequest;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AdminHrisRequestsAndShiftsTest extends TestCase
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
            'employee_id' => 'EMP-RS1',
            'department' => 'Operations',
            'position' => 'Staff',
            'is_active' => true,
        ]);
    }

    public function test_shift_management_and_assignment()
    {
        // 1. Create Shift
        $shiftRes = $this->actingAs($this->admin)->postJson('/api/hris/shifts', [
            'name' => 'Shift Pagi Operasional',
            'code' => 'SP-01',
            'check_in_start' => '06:00',
            'work_start_time' => '07:00',
            'late_tolerance_time' => '07:15',
            'check_in_end' => '08:00',
            'work_end_time' => '15:00',
            'color' => '#10B981',
        ]);
        $shiftRes->assertStatus(201);
        $shiftId = $shiftRes->json('shift.id') ?? $shiftRes->json('data.id') ?? $shiftRes->json('id');
        $this->assertNotNull($shiftId);

        // 2. Assign Shift to Employee
        $assignRes = $this->actingAs($this->admin)->postJson('/api/hris/shift-assignments', [
            'employee_ids' => [$this->employee->id],
            'shift_id' => (int)$shiftId,
            'start_date' => Carbon::today()->format('Y-m-d'),
            'end_date' => Carbon::today()->addDays(2)->format('Y-m-d'),
        ]);
        $assignRes->assertStatus(200);

        $getRes = $this->actingAs($this->admin)->getJson('/api/hris/shift-assignments');
        $getRes->assertStatus(200);
    }

    public function test_leave_and_permission_request_lifecycle()
    {
        $payload = [
            'employee_id' => $this->employee->id,
            'request_type' => 'leave',
            'start_date' => Carbon::today()->format('Y-m-d'),
            'end_date' => Carbon::today()->addDays(2)->format('Y-m-d'),
            'reason' => 'Cuti tahunan keperluan keluarga',
        ];

        // 1. Submit Request
        $createRes = $this->actingAs($this->employee->user)->postJson('/api/hris/requests', $payload);
        $createRes->assertStatus(201);
        $reqId = $createRes->json('data.id') ?? $createRes->json('id');

        // 2. Admin Approves Request
        $appRes = $this->actingAs($this->admin)->postJson("/api/hris/requests/{$reqId}/approve");
        $appRes->assertStatus(200);

        $this->assertDatabaseHas('hris_requests', [
            'id' => $reqId,
            'status' => 'approved',
        ]);
    }
}
