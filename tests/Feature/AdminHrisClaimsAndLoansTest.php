<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Employee;
use App\Models\HrisClaimType;
use App\Models\HrisClaim;
use App\Models\HrisLoan;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AdminHrisClaimsAndLoansTest extends TestCase
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
            'employee_id' => 'EMP-CL1',
            'department' => 'Finance',
            'position' => 'Staff',
            'is_active' => true,
        ]);
    }

    public function test_claim_reimbursement_lifecycle_and_approval()
    {
        $typeRes = $this->actingAs($this->admin)->postJson('/api/hris/claims/types', [
            'name' => 'Transport Bensin',
            'code' => 'BBM',
            'max_amount_per_claim' => 500000,
            'require_proof' => false,
        ]);
        $typeRes->assertStatus(201);
        $typeId = $typeRes->json('data.id') ?? $typeRes->json('id');

        $claimRes = $this->actingAs($this->employee->user)->postJson('/api/hris/claims', [
            'employee_id' => $this->employee->id,
            'claim_type_id' => $typeId,
            'title' => 'Bensin dinas luar kota',
            'amount' => 150000,
            'claim_date' => Carbon::today()->format('Y-m-d'),
            'description' => 'Bensin perjalanan dinas luar kota',
        ]);
        $claimRes->assertStatus(201);
        $claimId = $claimRes->json('data.id') ?? $claimRes->json('id');

        $appRes = $this->actingAs($this->admin)->postJson("/api/hris/claims/{$claimId}/approve");
        $appRes->assertStatus(200);
        $this->assertDatabaseHas('hris_claims', ['id' => $claimId, 'status' => 'approved']);
    }

    public function test_pinjaman_kasbon_creation_and_manual_payment()
    {
        $loanRes = $this->actingAs($this->admin)->postJson('/api/hris/loans', [
            'employee_id' => $this->employee->id,
            'amount' => 1000000,
            'tenor_months' => 2,
            'reason' => 'Pinjaman darurat berobat keluarga',
            'disbursed_at' => Carbon::today()->format('Y-m-d'),
        ]);
        $loanRes->assertStatus(201);
        $loanId = $loanRes->json('data.id') ?? $loanRes->json('id');

        $payRes = $this->actingAs($this->admin)->postJson("/api/hris/loans/{$loanId}/manual-payment", [
            'amount' => 500000,
            'payment_date' => Carbon::today()->format('Y-m-d'),
            'payment_method' => 'cash',
            'notes' => 'Pembayaran cicilan bulan ke-1 tunai',
        ]);
        $payRes->assertStatus(200);

        $this->assertDatabaseHas('hris_loans', [
            'id' => $loanId,
            'paid_amount' => 500000,
        ]);
    }
}
