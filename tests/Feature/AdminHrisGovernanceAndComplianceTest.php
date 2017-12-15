<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Employee;
use App\Models\HrisTraining;
use App\Models\HrisContract;
use App\Models\HrisDocument;
use App\Models\HrisViolation;
use App\Models\HrisResignation;
use App\Models\HrisComplianceItem;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AdminHrisGovernanceAndComplianceTest extends TestCase
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
            'employee_id' => 'EMP-GC1',
            'department' => 'Logistics',
            'position' => 'Driver Operasional',
            'is_active' => true,
        ]);
    }

    public function test_training_and_certification_program()
    {
        $trainRes = $this->actingAs($this->admin)->postJson('/api/hris/training', [
            'title' => 'Sertifikasi Defensive Driving Driver',
            'trainer_name' => 'Pusat Pelatihan Keselamatan Transportasi',
            'training_date' => Carbon::today()->format('Y-m-d'),
            'location' => 'Training Center HQ',
            'duration_hours' => 8,
            'participant_ids' => [$this->employee->id],
        ]);
        $trainRes->assertStatus(201);
        $trainId = $trainRes->json('data.id') ?? $trainRes->json('id') ?? $trainRes->json('training.id');
        $this->assertNotNull($trainId);
    }

    public function test_contract_management()
    {
        $contractRes = $this->actingAs($this->admin)->postJson('/api/hris/contracts', [
            'employee_id' => $this->employee->id,
            'contract_type' => 'PKWT',
            'contract_date' => Carbon::today()->format('Y-m-d'),
            'start_date' => Carbon::today()->format('Y-m-d'),
            'end_date' => Carbon::today()->addYears(1)->format('Y-m-d'),
            'basic_salary' => 4500000,
        ]);
        $contractRes->assertStatus(201);

        $this->assertDatabaseHas('hris_contracts', [
            'employee_id' => $this->employee->id,
            'contract_type' => 'PKWT',
        ]);
    }

    public function test_document_vault_and_verification()
    {
        $docRes = $this->actingAs($this->admin)->postJson('/api/hris/documents', [
            'employee_id' => $this->employee->id,
            'title' => 'KTP Elektronik Karyawan',
            'category' => 'identity',
            'file_base64' => 'data:application/pdf;base64,JVBERi0xLjQKJcTl8uXr',
            'notes' => 'KTP Asli telah diverifikasi',
        ]);
        $docRes->assertStatus(201);

        $docId = $docRes->json('data.id') ?? $docRes->json('id');
        $this->assertNotNull($docId);
    }

    public function test_violation_and_warning_letter_sp()
    {
        $spRes = $this->actingAs($this->admin)->postJson('/api/hris/violations', [
            'employee_id' => $this->employee->id,
            'violation_type' => 'SP1',
            'violation_date' => Carbon::today()->format('Y-m-d'),
            'valid_from' => Carbon::today()->format('Y-m-d'),
            'valid_until' => Carbon::today()->addMonths(6)->format('Y-m-d'),
            'description' => 'Terlambat berulang tanpa keterangan yang sah',
            'sanction' => 'Surat Peringatan Pertama',
        ]);
        $spRes->assertStatus(201);

        $this->assertDatabaseHas('hris_violations', [
            'employee_id' => $this->employee->id,
            'violation_type' => 'SP1',
        ]);
    }

    public function test_employee_resignation_lifecycle()
    {
        $resRes = $this->actingAs($this->admin)->postJson('/api/hris/resignations', [
            'employee_id' => $this->employee->id,
            'category' => 'voluntary',
            'resignation_date' => Carbon::today()->format('Y-m-d'),
            'reason' => 'Pindah domisili luar pulau',
        ]);
        $resRes->assertStatus(201);

        $this->assertDatabaseHas('hris_resignations', [
            'employee_id' => $this->employee->id,
            'status' => 'approved',
        ]);
    }

    public function test_compliance_and_legal_license_alerts()
    {
        $compRes = $this->actingAs($this->admin)->postJson('/api/hris/compliance', [
            'target_type' => 'employee',
            'target_id' => $this->employee->id,
            'doc_name' => 'SIM B1 Pengemudi Truk',
            'doc_number' => 'SIM-99281726',
            'expiry_date' => Carbon::today()->addMonths(3)->format('Y-m-d'),
        ]);
        $compRes->assertStatus(201);

        $summaryRes = $this->actingAs($this->admin)->getJson('/api/hris/compliance/summary');
        $summaryRes->assertStatus(200);
    }
}
