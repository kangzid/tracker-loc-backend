<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);
    }

    public function test_admin_can_access_dashboard_stats()
    {
        $response = $this->actingAs($this->admin)->getJson('/api/dashboard/stats');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'total_employees',
                'active_employees',
                'total_vehicles',
                'active_vehicles',
                'today_attendances',
                'pending_tasks',
                'in_progress_tasks',
            ]);
    }

    public function test_non_admin_cannot_access_admin_dashboard()
    {
        $employeeUser = User::factory()->create(['role' => 'employee']);
        $response = $this->actingAs($employeeUser)->getJson('/api/dashboard/stats');

        $response->assertStatus(403);
    }
}
