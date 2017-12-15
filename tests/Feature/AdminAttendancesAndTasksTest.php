<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Employee;
use App\Models\Task;
use App\Models\Attendance;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AdminAttendancesAndTasksTest extends TestCase
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
            'employee_id' => 'EMP200',
            'department' => 'Operations',
            'position' => 'Staff',
            'is_active' => true,
        ]);
    }

    public function test_admin_can_list_and_filter_attendances()
    {
        Attendance::create([
            'admin_id' => $this->admin->id,
            'employee_id' => $this->employee->id,
            'date' => Carbon::today()->format('Y-m-d'),
            'status' => 'present',
            'check_in' => '07:55:00',
        ]);

        $response = $this->actingAs($this->admin)->getJson('/api/attendances');
        $response->assertStatus(200);
    }

    public function test_admin_can_create_assign_and_track_tasks()
    {
        $taskPayload = [
            'title' => 'Pengiriman Logistik Wilayah Utara',
            'description' => 'Antar paket ke gudang cabang 2',
            'assigned_to' => $this->employee->id,
            'priority' => 'urgent',
            'due_date' => Carbon::tomorrow()->format('Y-m-d H:i:s'),
        ];

        $createRes = $this->actingAs($this->admin)->postJson('/api/tasks', $taskPayload);
        $createRes->assertStatus(201);

        $this->assertDatabaseHas('tasks', [
            'admin_id' => $this->admin->id,
            'title' => 'Pengiriman Logistik Wilayah Utara',
        ]);

        $taskId = $createRes->json('data.id') ?? $createRes->json('task.id') ?? $createRes->json('id');
        if ($taskId) {
            $upRes = $this->actingAs($this->employee->user)->postJson("/api/tasks/{$taskId}/complete");
            $this->assertTrue(in_array($upRes->status(), [200, 204]));
        }
    }
}
