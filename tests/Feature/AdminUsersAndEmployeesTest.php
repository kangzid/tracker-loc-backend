<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AdminUsersAndEmployeesTest extends TestCase
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

    public function test_admin_can_list_users()
    {
        User::factory()->count(3)->create(['admin_id' => $this->admin->id]);

        $response = $this->actingAs($this->admin)->getJson('/api/users');

        $response->assertStatus(200);
    }

    public function test_admin_can_create_new_user()
    {
        $payload = [
            'name' => 'Budi Santoso',
            'email' => 'budi@majusejahtera.com',
            'password' => 'password123',
            'role' => 'employee',
        ];

        $response = $this->actingAs($this->admin)->postJson('/api/users', $payload);

        $response->assertStatus(201);
        $this->assertDatabaseHas('users', ['email' => 'budi@majusejahtera.com']);
    }

    public function test_admin_can_list_employees_with_tenant_isolation()
    {
        $userEmp = User::factory()->create(['role' => 'employee', 'admin_id' => $this->admin->id]);
        Employee::create([
            'user_id' => $userEmp->id,
            'admin_id' => $this->admin->id,
            'employee_id' => 'EMP999',
            'department' => 'IT',
            'position' => 'Developer',
            'is_active' => true,
        ]);

        // Another tenant employee
        $otherAdmin = User::factory()->create(['role' => 'admin']);
        $otherUser = User::factory()->create(['role' => 'employee', 'admin_id' => $otherAdmin->id]);
        Employee::create([
            'user_id' => $otherUser->id,
            'admin_id' => $otherAdmin->id,
            'employee_id' => 'EMP888',
            'department' => 'HR',
            'position' => 'Staff',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->getJson('/api/employees');

        $response->assertStatus(200);
        $data = $response->json();
        $empList = isset($data['data']) ? $data['data'] : $data;

        // Ensure this admin can see EMP999 but NOT EMP888
        $this->assertTrue(collect($empList)->contains('employee_id', 'EMP999'));
        $this->assertFalse(collect($empList)->contains('employee_id', 'EMP888'));
    }
}
