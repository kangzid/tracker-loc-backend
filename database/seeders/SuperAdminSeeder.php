<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        // Cek dulu apakah superadmin sudah ada, agar tidak duplikat
        $existing = User::where('role', 'superadmin')->first();

        if ($existing) {
            $this->command->info('Superadmin sudah ada: ' . $existing->email);
            return;
        }

        $user = User::create([
            'name'      => 'LocaTrack Superadmin',
            'email'     => 'superadmin@locatrack.id',
            'password'  => Hash::make('SuperAdmin@2026!'),
            'role'      => 'superadmin',
            'is_active' => true,
        ]);

        $this->command->info('✅ Superadmin berhasil dibuat!');
        $this->command->info('   Email    : ' . $user->email);
        $this->command->info('   Password : SuperAdmin@2026!');
        $this->command->warn('   ⚠️  Segera ganti password setelah login pertama!');
    }
}
