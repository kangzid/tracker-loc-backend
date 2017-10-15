<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Starter',
                'slug' => 'starter',
                'description' => 'Cocok untuk UMKM dan tim kecil yang baru mulai.',
                'price_monthly' => 49000,
                'max_employees' => 10,
                'max_vehicles' => 5,
                'features' => [
                    'Hingga 10 Karyawan',
                    'Hingga 5 Kendaraan',
                    'GPS Tracking Real-time',
                    'Manajemen Absensi',
                    'Dashboard Analytics',
                    'Natra AI Assistant',
                ],
                'is_active' => true,
                'is_custom' => false,
            ],
            [
                'name' => 'Pro',
                'slug' => 'pro',
                'description' => 'Ideal untuk perusahaan berkembang dengan operasional yang lebih besar.',
                'price_monthly' => 149000,
                'max_employees' => 50,
                'max_vehicles' => 20,
                'features' => [
                    'Hingga 50 Karyawan',
                    'Hingga 20 Kendaraan',
                    'GPS Tracking Real-time',
                    'Manajemen Absensi + Laporan',
                    'Dashboard Analytics Lanjutan',
                    'Natra AI Assistant Pro',
                    'Manajemen Tugas & Geofencing',
                    'Export Laporan PDF/Excel',
                    'Prioritas Support',
                ],
                'is_active' => true,
                'is_custom' => false,
            ],
            [
                'name' => 'Custom',
                'slug' => 'custom',
                'description' => 'Untuk korporasi besar dengan kebutuhan tanpa batas. Hubungi kami.',
                'price_monthly' => 0,
                'max_employees' => 0,   // 0 = unlimited
                'max_vehicles' => 0,    // 0 = unlimited
                'features' => [
                    'Karyawan Tak Terbatas',
                    'Kendaraan Tak Terbatas',
                    'Semua Fitur Pro',
                    'Dedicated Account Manager',
                    'Custom Integrasi API',
                    'SLA 99.9% Uptime',
                    'Pelatihan Onboarding Tim',
                    'Harga Negosiasi',
                ],
                'is_active' => true,
                'is_custom' => true,
            ],
        ];

        foreach ($plans as $plan) {
            Plan::updateOrCreate(['slug' => $plan['slug']], $plan);
        }
    }
}
