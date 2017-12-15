<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Geofence;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AdminGeofencesTest extends TestCase
{
    use RefreshDatabase;

    protected $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
    }

    public function test_admin_can_create_list_and_delete_geofences()
    {
        $payload = [
            'name' => 'Kantor Pusat HQ',
            'center_lat' => -7.782868,
            'center_lng' => 110.358429,
            'radius' => 150,
            'type' => 'office',
            'description' => 'Area geofence kantor pusat operasional',
        ];

        // 1. Create Geofence
        $createRes = $this->actingAs($this->admin)->postJson('/api/geofences', $payload);
        $createRes->assertStatus(201);

        $this->assertDatabaseHas('geofences', [
            'admin_id' => $this->admin->id,
            'name' => 'Kantor Pusat HQ',
        ]);

        $geoId = $createRes->json('data.id') ?? $createRes->json('id');

        // 2. List Geofences
        $listRes = $this->actingAs($this->admin)->getJson('/api/geofences');
        $listRes->assertStatus(200);

        // 3. Delete Geofence
        if ($geoId) {
            $delRes = $this->actingAs($this->admin)->deleteJson("/api/geofences/{$geoId}");
            $delRes->assertStatus(200);
            $this->assertDatabaseMissing('geofences', ['id' => $geoId]);
        }
    }
}
