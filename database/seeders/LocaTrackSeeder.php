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
        // 1. Create Default Plans
        $trialPlan = \App\Models\Plan::create([
            'name' => 'Free Trial',
            'slug' => 'trial',
            'description' => 'Coba semua fitur dasar secara gratis selama 7 hari',
            'price_monthly' => 0,
            'max_employees' => 2,
            'max_vehicles' => 1,
            'ai_credits' => 20, // 10-20 pertanyaan
            'features' => [
                'Real-time GPS Tracking',
                'Absensi Selfie & Geolocation',
                'Manajemen Karyawan Dasar',
                'Chat Support Dashboard',
                'Laporan Harian (PDF)',
                'AI Assistant (20 Credits)'
            ],
            'sort_order' => 1
        ]);

        $proPlan = \App\Models\Plan::create([
            'name' => 'Pro Tracking',
            'slug' => 'pro',
            'description' => 'Solusi lengkap untuk tim lapangan dan armada kendaraan',
            'price_monthly' => 200000,
            'max_employees' => 50,
            'max_vehicles' => 30,
            'ai_credits' => 500,
            'features' => [
                'Semua Fitur Trial',
                'AI-Powered Attendance Insights',
                'Advanced HRIS (Cuti & Izin)',
                'Geofencing Monitoring',
                'Smart Notifications & Alerts',
                'Riwayat Perjalanan 30 Hari',
                'AI Assistant (500 Credits)'
            ],
            'sort_order' => 2
        ]);

        $businessPlan = \App\Models\Plan::create([
            'name' => 'Enterprise Business',
            'slug' => 'business',
            'description' => 'Solusi kustom dan integrasi penuh untuk skala korporasi',
            'price_monthly' => 800000,
            'max_employees' => 0, // unlimited
            'max_vehicles' => 0, // unlimited
            'ai_credits' => 5000,
            'features' => [
                'Semua Fitur Pro',
                'Solusi Custom (Watermark & Branding)',
                'Integrasi API Penuh (Third Party)',
                'AI Predictive Analytics',
                'Priority Chat Support 24/7',
                'Dedicated Account Manager',
                'AI Assistant (5000 Credits)'
            ],
            'is_custom' => true,
            'sort_order' => 3
        ]);

        // 2. Create Superadmin
        User::create([
            'name' => 'LocaTrack Superadmin',
            'email' => 'superadmin@locatrack.com',
            'password' => Hash::make('password123'),
            'role' => 'superadmin',
            'is_active' => true,
        ]);

        // 3. Create Admin Tenant (Baru Daftar - Fase Trial)
        $admin = User::create([
            'name' => 'Admin Maju Sejahtera',
            'email' => 'admin@majusejahtera.com',
            'password' => Hash::make('password123'),
            'role' => 'admin',
            'is_active' => true,
        ]);

        // Otomatis Berlangganan Paket Trial (1 Minggu)
        \App\Models\Subscription::create([
            'user_id' => $admin->id,
            'plan_id' => $trialPlan->id,
            'max_employees' => 2,
            'max_vehicles' => 1,
            'ai_credits_limit' => 20, // Snapshot dari plan
            'ai_credits_used' => 0,
            'company_name' => 'PT Maju Sejahtera',
            'status' => 'active',
            'started_at' => now(),
            'expired_at' => now()->addWeek(), // Trial 1 Minggu
        ]);

        // 4. Create Employees (Sesuai kuota trial - 2 orang)
        $employeesData = [
            ['name' => 'Budi Santoso', 'email' => 'budi@majusejahtera.com', 'emp_id' => 'EMP001', 'dept' => 'Logistics'],
            ['name' => 'Siti Aminah', 'email' => 'siti@majusejahtera.com', 'emp_id' => 'EMP002', 'dept' => 'Sales'],
        ];

        foreach ($employeesData as $data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make('password123'),
                'role' => 'employee',
                'admin_id' => $admin->id,
                'is_active' => true,
            ]);

            Employee::create([
                'user_id' => $user->id,
                'admin_id' => $admin->id,
                'employee_id' => $data['emp_id'],
                'department' => $data['dept'],
                'phone' => '0812' . rand(11111111, 99999999),
                'position' => 'Staff ' . $data['dept'],
                'latitude' => -6.2088 + (rand(-100, 100) / 10000),
                'longitude' => 106.8456 + (rand(-100, 100) / 10000),
                'last_location_update' => now(),
            ]);
        }

        // 5. Create Vehicle (Sesuai kuota trial - 1 unit)
        $vehicle = Vehicle::create([
            'admin_id' => $admin->id,
            'vehicle_number' => 'B 1234 ABC',
            'vehicle_type' => 'Car',
            'brand' => 'Toyota',
            'model' => 'Avanza',
            'year' => 2022,
            'is_active' => true,
            'latitude' => -6.2088 + (rand(-100, 100) / 10000),
            'longitude' => 106.8456 + (rand(-100, 100) / 10000),
            'last_location_update' => now(),
        ]);
        $vehicle->generateTrackingToken();

        // 6. Create Geofences
        Geofence::create([
            'admin_id' => $admin->id,
            'name' => 'Kantor Pusat',
            'description' => 'Area kantor pusat perusahaan',
            'center_lat' => -6.2088,
            'center_lng' => 106.8456,
            'radius' => 100,
            'type' => 'office',
            'is_active' => true,
        ]);
    }
}