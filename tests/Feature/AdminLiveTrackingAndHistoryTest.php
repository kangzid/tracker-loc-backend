<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Employee;
use App\Models\Location;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AdminLiveTrackingAndHistoryTest extends TestCase
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
            'employee_id' => 'EMP100',
            'department' => 'Delivery',
            'position' => 'Courier',
            'is_active' => true,
        ]);
    }

    public function test_employee_can_send_gps_and_admin_can_monitor_live_locations()
    {
        $locPayload = [
            'latitude' => -7.7828,
            'longitude' => 110.3584,
            'speed' => 35.5,
            'heading' => 180.0,
            'accuracy' => 10.0,
            'trackable_type' => 'employee',
            'trackable_id' => $this->employee->id,
        ];

        $postRes = $this->actingAs($this->employee->user)->postJson('/api/locations', $locPayload);
        $postRes->assertStatus(201);

        // Admin monitors live tracking
        $monRes = $this->actingAs($this->admin)->getJson('/api/locations/live');
        $monRes->assertStatus(200);
    }

    public function test_admin_can_query_employee_route_history()
    {
        Location::create([
            'trackable_type' => Employee::class,
            'trackable_id' => $this->employee->id,
            'latitude' => -7.7828,
            'longitude' => 110.3584,
            'speed' => 20,
            'accuracy' => 5,
            'recorded_at' => Carbon::now()->subMinutes(10),
        ]);

        Location::create([
            'trackable_type' => Employee::class,
            'trackable_id' => $this->employee->id,
            'latitude' => -7.7900,
            'longitude' => 110.3600,
            'speed' => 25,
            'accuracy' => 5,
            'recorded_at' => Carbon::now(),
        ]);

        $queryUrl = "/api/locations/employee/{$this->employee->id}/history?date=" . Carbon::today()->format('Y-m-d');
        $histRes = $this->actingAs($this->admin)->getJson($queryUrl);

        $histRes->assertStatus(200);
    }
}
