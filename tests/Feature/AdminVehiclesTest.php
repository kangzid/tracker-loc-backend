<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AdminVehiclesTest extends TestCase
{
    use RefreshDatabase;

    protected $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $plan = Plan::create([
            'name' => 'Enterprise Plan',
            'slug' => 'enterprise',
            'price_monthly' => 1000000,
            'max_employees' => 100,
            'max_vehicles' => 100,
            'features' => ['all'],
            'is_active' => true,
        ]);

        Subscription::create([
            'user_id' => $this->admin->id,
            'plan_id' => $plan->id,
            'status' => 'active',
            'started_at' => Carbon::now(),
            'expired_at' => Carbon::now()->addDays(365),
        ]);
    }

    public function test_admin_can_create_and_list_vehicles()
    {
        $payload = [
            'vehicle_number' => 'B 1234 CD',
            'vehicle_type' => 'truck',
            'brand' => 'Mitsubishi Canter',
            'model' => 'FE 74 HD',
            'year' => 2022,
        ];

        $createRes = $this->actingAs($this->admin)->postJson('/api/vehicles', $payload);
        $createRes->assertStatus(201);

        $this->assertDatabaseHas('vehicles', [
            'admin_id' => $this->admin->id,
            'vehicle_number' => 'B 1234 CD',
        ]);

        $listRes = $this->actingAs($this->admin)->getJson('/api/vehicles');
        $listRes->assertStatus(200);
    }
}
