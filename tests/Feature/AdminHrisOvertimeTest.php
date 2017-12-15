<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Employee;
use App\Models\HrisOvertime;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AdminHrisOvertimeTest extends TestCase
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
            'employee_id' => 'EMP-OT1',
            'department' => 'Warehouse',
            'position' => 'Staff',
            'is_active' => true,
        ]);
    }

    public function test_employee_can_submit_overtime_and_admin_can_approve()
    {
        $payload = [
            'employee_id' => $this->employee->id,
            'date' => Carbon::today()->format('Y-m-d'),
            'start_time' => '17:00',
            'end_time' => '20:00',
            'reason' => 'Packing pengiriman besar akhir bulan',
        ];

        $postRes = $this->actingAs($this->employee->user)->postJson('/api/hris/overtimes', $payload);
        $postRes->assertStatus(201);

        $otId = $postRes->json('data.id') ?? $postRes->json('id');
        $this->assertNotNull($otId);

        $appRes = $this->actingAs($this->admin)->postJson("/api/hris/overtimes/{$otId}/approve");
        $appRes->assertStatus(200);

        $this->assertDatabaseHas('hris_overtimes', [
            'id' => $otId,
            'status' => 'approved',
        ]);
    }
}
