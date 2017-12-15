<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Employee;
use App\Models\HrisKpiPeriod;
use App\Models\HrisPerformanceReview;
use App\Models\HrisMutation;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AdminHrisPerformanceAndMutationsTest extends TestCase
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
            'employee_id' => 'EMP-PM1',
            'department' => 'Marketing',
            'position' => 'Junior Officer',
            'is_active' => true,
        ]);
    }

    public function test_kpi_performance_review_lifecycle()
    {
        $revRes = $this->actingAs($this->admin)->postJson('/api/hris/performance', [
            'target_type' => 'employee',
            'employee_id' => $this->employee->id,
            'period' => '2026-Q3',
            'attendance_score' => 4.8,
            'task_completion_score' => 4.9,
            'discipline_score' => 4.7,
            'teamwork_score' => 5.0,
            'remarks' => 'Kinerja luar biasa dalam pencapaian target penjualan.',
        ]);
        $revRes->assertStatus(201);
        $revId = $revRes->json('data.id') ?? $revRes->json('id');

        $finRes = $this->actingAs($this->admin)->postJson("/api/hris/performance/{$revId}/finalize");
        $finRes->assertStatus(200);

        $this->assertDatabaseHas('hris_performance_reviews', [
            'id' => $revId,
            'status' => 'finalized',
        ]);
    }

    public function test_employee_mutation_and_promotion()
    {
        $mutRes = $this->actingAs($this->admin)->postJson('/api/hris/mutations', [
            'employee_id' => $this->employee->id,
            'mutation_type' => 'promotion',
            'effective_date' => Carbon::today()->format('Y-m-d'),
            'new_department' => 'Marketing',
            'new_position' => 'Senior Officer',
            'reason' => 'Promosi jabatan atas pencapaian kinerja Q3',
        ]);
        $mutRes->assertStatus(201);
        $mutId = $mutRes->json('data.id') ?? $mutRes->json('id');

        $this->assertDatabaseHas('hris_mutations', [
            'id' => $mutId,
            'new_position' => 'Senior Officer',
        ]);
    }
}
