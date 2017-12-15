<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Employee;
use App\Models\HrisAsset;
use App\Models\HrisNews;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AdminHrisAssetsAndNewsTest extends TestCase
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
            'employee_id' => 'EMP-AN1',
            'department' => 'IT Support',
            'position' => 'Staff',
            'is_active' => true,
        ]);
    }

    public function test_asset_inventory_management_and_handover()
    {
        // 1. Create Asset Category
        $catRes = $this->actingAs($this->admin)->postJson('/api/hris/asset-categories', [
            'name' => 'Elektronik Laptop',
            'code' => 'LAPTOP',
        ]);
        $catRes->assertStatus(201);

        // 2. Create Asset
        $assetRes = $this->actingAs($this->admin)->postJson('/api/hris/assets', [
            'name' => 'MacBook Pro M2 14 Inch',
            'category' => 'Elektronik Laptop',
            'asset_code' => 'AST-MBP-01',
            'serial_number' => 'SN-99882211',
            'condition' => 'good',
            'status' => 'storage',
        ]);
        $assetRes->assertStatus(201);
        $assetId = $assetRes->json('data.id') ?? $assetRes->json('asset.id') ?? $assetRes->json('id');
        $this->assertNotNull($assetId);

        // 3. Handover / Assign Asset to Employee
        $handoverRes = $this->actingAs($this->admin)->postJson("/api/hris/assets/{$assetId}/assign", [
            'employee_id' => $this->employee->id,
            'handover_date' => Carbon::today()->format('Y-m-d'),
            'notes' => 'Diserahkan dalam kondisi mulus lengkap charger',
        ]);
        $handoverRes->assertStatus(200);

        $this->assertDatabaseHas('hris_assets', [
            'id' => $assetId,
            'employee_id' => $this->employee->id,
            'status' => 'assigned',
        ]);
    }

    public function test_news_and_announcement_publishing()
    {
        $newsRes = $this->actingAs($this->admin)->postJson('/api/hris/news', [
            'title' => 'Pengumuman Libur Hari Raya Perusahaan',
            'category' => 'company_announcement',
            'content' => 'Diberitahukan kepada seluruh staf bahwa operasional libur bersama nasional.',
            'is_published' => true,
            'published_at' => Carbon::today()->format('Y-m-d H:i:s'),
        ]);
        $newsRes->assertStatus(201);
        $newsId = $newsRes->json('data.id') ?? $newsRes->json('id');

        $listRes = $this->actingAs($this->admin)->getJson('/api/hris/news');
        $listRes->assertStatus(200);
    }
}
