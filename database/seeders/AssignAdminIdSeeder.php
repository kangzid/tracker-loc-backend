<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Employee;
use App\Models\Vehicle;
use App\Models\Task;
use App\Models\Geofence;
use App\Models\Notification;
use App\Models\Subscription;

class AssignAdminIdSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Starting admin_id assignment...');

        // Get all admins with subscriptions
        $admins = User::where('role', 'admin')->get();

        foreach ($admins as $admin) {
            $this->command->info("Processing admin: {$admin->name} (ID: {$admin->id})");

            // Assign admin_id to employees created by this admin
            // Strategy: employees without admin_id will be assigned to first admin
            // You may need to adjust this logic based on your data
            $employees = Employee::whereNull('admin_id')
                ->whereHas('user', function($q) {
                    $q->where('role', 'employee');
                })
                ->get();

            foreach ($employees as $employee) {
                $employee->update(['admin_id' => $admin->id]);
                $this->command->info("  - Assigned employee {$employee->id} to admin {$admin->id}");
            }

            // Assign admin_id to vehicles
            $vehicles = Vehicle::whereNull('admin_id')->get();
            foreach ($vehicles as $vehicle) {
                $vehicle->update(['admin_id' => $admin->id]);
                $this->command->info("  - Assigned vehicle {$vehicle->id} to admin {$admin->id}");
            }

            // Assign admin_id to tasks
            $tasks = Task::whereNull('admin_id')
                ->where('assigned_by', $admin->id)
                ->get();
            foreach ($tasks as $task) {
                $task->update(['admin_id' => $admin->id]);
                $this->command->info("  - Assigned task {$task->id} to admin {$admin->id}");
            }

            // Assign admin_id to geofences
            $geofences = Geofence::whereNull('admin_id')->get();
            foreach ($geofences as $geofence) {
                $geofence->update(['admin_id' => $admin->id]);
                $this->command->info("  - Assigned geofence {$geofence->id} to admin {$admin->id}");
            }

            // Assign admin_id to notifications
            $notifications = Notification::whereNull('admin_id')->get();
            foreach ($notifications as $notification) {
                $employee = Employee::find($notification->employee_id);
                if ($employee && $employee->admin_id) {
                    $notification->update(['admin_id' => $employee->admin_id]);
                    $this->command->info("  - Assigned notification {$notification->id} to admin {$employee->admin_id}");
                }
            }

            // Only process first admin if you want to assign all data to one admin
            // Remove this break if you have multiple admins and want to distribute data
            break;
        }

        $this->command->info('Admin ID assignment completed!');
    }
}
