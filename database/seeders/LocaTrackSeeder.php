<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Employee;
use App\Models\Vehicle;
use App\Models\Geofence;
use Illuminate\Support\Facades\Hash;

class LocaTrackSeeder extends Seeder
{
    public function run(): void
    {
        // Create Admin User
        $admin = User::create([
            'name' => 'Admin LocaTrack',
            'email' => 'admin@locatrack.com',
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'is_active' => true,
        ]);

        // Create Employee Users
        $employee1 = User::create([
            'name' => 'John Doe',
            'email' => 'john@locatrack.com',
            'password' => Hash::make('password123'),
            'role' => 'employee',
            'is_active' => true,
        ]);

        $employee2 = User::create([
            'name' => 'Jane Smith',
            'email' => 'jane@locatrack.com',
            'password' => Hash::make('password123'),
            'role' => 'employee',
            'is_active' => true,
        ]);

        // Create Employee Profiles
        Employee::create([
            'user_id' => $employee1->id,
            'employee_id' => 'EMP001',
            'phone' => '081234567890',
            'address' => 'Jl. Contoh No. 123, Jakarta',
            'department' => 'IT',
            'position' => 'Software Developer',
        ]);

        Employee::create([
            'user_id' => $employee2->id,
            'employee_id' => 'EMP002',
            'phone' => '081234567891',
            'address' => 'Jl. Contoh No. 124, Jakarta',
            'department' => 'Marketing',
            'position' => 'Marketing Executive',
        ]);

        // Create Vehicles
        Vehicle::create([
            'vehicle_number' => 'B 1234 ABC',
            'vehicle_type' => 'Car',
            'brand' => 'Toyota',
            'model' => 'Avanza',
            'year' => 2022,
            'is_active' => true,
        ]);

        Vehicle::create([
            'vehicle_number' => 'B 5678 DEF',
            'vehicle_type' => 'Motorcycle',
            'brand' => 'Honda',
            'model' => 'Vario',
            'year' => 2023,
            'is_active' => true,
        ]);

        // Create Geofences
        Geofence::create([
            'name' => 'Kantor Pusat',
            'description' => 'Area kantor pusat perusahaan',
            'center_lat' => -6.2088,
            'center_lng' => 106.8456,
            'radius' => 100, // 100 meters
            'type' => 'office',
            'is_active' => true,
        ]);

        Geofence::create([
            'name' => 'Area Kerja Lapangan',
            'description' => 'Area kerja lapangan proyek A',
            'center_lat' => -6.1751,
            'center_lng' => 106.8650,
            'radius' => 200, // 200 meters
            'type' => 'work_area',
            'is_active' => true,
        ]);
    }
}