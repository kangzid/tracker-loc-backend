<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Employee;
use App\Models\Vehicle;
use App\Models\Task;
use App\Models\Geofence;
use App\Models\Notification;
use Illuminate\Support\Facades\DB;

class FixTenantIsolation extends Command
{
    protected $signature = 'fix:tenant-isolation {--dry-run : Run without making changes}';
    protected $description = 'Fix tenant isolation by assigning admin_id to all records';

    public function handle()
    {
        $dryRun = $this->option('dry-run');
        
        if ($dryRun) {
            $this->warn('🔍 DRY RUN MODE - No changes will be made');
        } else {
            $this->warn('⚠️  This will modify your database!');
            if (!$this->confirm('Do you want to continue?')) {
                $this->info('Operation cancelled.');
                return 0;
            }
        }

        DB::beginTransaction();

        try {
            $this->info('Starting tenant isolation fix...');
            $this->newLine();

            $admins = User::where('role', 'admin')->get();
            
            if ($admins->isEmpty()) {
                $this->error('No admin users found!');
                return 1;
            }

            $this->info("Found {$admins->count()} admin(s)");
            $this->newLine();

            foreach ($admins as $index => $admin) {
                $adminNumber = $index + 1;
                $this->info("Processing Admin #{$adminNumber}: {$admin->name} (ID: {$admin->id})");
                
                $employeeCount = Employee::whereNull('admin_id')->count();
                if ($employeeCount > 0) {
                    if (!$dryRun) {
                        Employee::whereNull('admin_id')->update(['admin_id' => $admin->id]);
                    }
                    $this->line("  ✓ Assigned {$employeeCount} employees");
                }

                $vehicleCount = Vehicle::whereNull('admin_id')->count();
                if ($vehicleCount > 0) {
                    if (!$dryRun) {
                        Vehicle::whereNull('admin_id')->update(['admin_id' => $admin->id]);
                    }
                    $this->line("  ✓ Assigned {$vehicleCount} vehicles");
                }

                $taskCount = Task::whereNull('admin_id')->count();
                if ($taskCount > 0) {
                    if (!$dryRun) {
                        Task::whereNull('admin_id')->update(['admin_id' => $admin->id]);
                    }
                    $this->line("  ✓ Assigned {$taskCount} tasks");
                }

                $geofenceCount = Geofence::whereNull('admin_id')->count();
                if ($geofenceCount > 0) {
                    if (!$dryRun) {
                        Geofence::whereNull('admin_id')->update(['admin_id' => $admin->id]);
                    }
                    $this->line("  ✓ Assigned {$geofenceCount} geofences");
                }

                $notificationCount = Notification::whereNull('admin_id')->count();
                if ($notificationCount > 0) {
                    if (!$dryRun) {
                        DB::statement('
                            UPDATE notifications n
                            INNER JOIN employees e ON n.employee_id = e.id
                            SET n.admin_id = e.admin_id
                            WHERE n.admin_id IS NULL AND e.admin_id IS NOT NULL
                        ');
                    }
                    $this->line("  ✓ Assigned {$notificationCount} notifications");
                }

                $this->newLine();
                // Only process first admin
                break;
            }

            $this->info('Verification:');
            $this->table(
                ['Table', 'Records without admin_id'],
                [
                    ['employees', Employee::whereNull('admin_id')->count()],
                    ['vehicles', Vehicle::whereNull('admin_id')->count()],
                    ['tasks', Task::whereNull('admin_id')->count()],
                    ['geofences', Geofence::whereNull('admin_id')->count()],
                    ['notifications', Notification::whereNull('admin_id')->count()],
                ]
            );

            if ($dryRun) {
                DB::rollBack();
                $this->warn('🔍 DRY RUN completed - No changes were made');
            } else {
                DB::commit();
                $this->info('✅ Tenant isolation fix completed successfully!');
            }

            return 0;

        } catch (\Exception $e) {
            DB::rollBack();
            $this->error('❌ Error: ' . $e->getMessage());
            return 1;
        }
    }
}
