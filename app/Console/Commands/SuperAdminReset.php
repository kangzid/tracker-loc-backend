<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class SuperAdminReset extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'superadmin:reset';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset Superadmin email and password';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('--- LocaTrack Superadmin Reset Tool ---');

        $superadmin = User::where('role', 'superadmin')->first();

        if (!$superadmin) {
            $this->error('No user with role "superadmin" found in the database.');
            if ($this->confirm('Do you want to create a new Superadmin?', true)) {
                $email = $this->ask('Enter Email for new Superadmin');
                $password = $this->secret('Enter Password for new Superadmin');
                
                $user = new User();
                $user->name = 'LocaTrack Superadmin';
                $user->email = $email;
                $user->password = Hash::make($password);
                $user->role = 'superadmin';
                $user->is_active = true;
                $user->save();

                $this->info("Superadmin created successfully with email: $email");
            }
            return;
        }

        $this->info("Found Superadmin: {$superadmin->email} ({$superadmin->name})");

        if ($this->confirm('Do you want to change the email?', false)) {
            $newEmail = $this->ask('Enter new Email');
            $superadmin->email = $newEmail;
        }

        if ($this->confirm('Do you want to change the password?', true)) {
            $newPassword = $this->secret('Enter new Password');
            $superadmin->password = Hash::make($newPassword);
        }

        $superadmin->save();

        $this->info('Superadmin credentials updated successfully!');
    }
}
