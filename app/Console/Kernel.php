<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use App\Models\Attendance;
use Carbon\Carbon;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        // Auto cleanup attendances older than 3 months - runs monthly
        $schedule->call(function () {
            $threeMonthsAgo = Carbon::now()->subMonths(3);
            Attendance::where('date', '<', $threeMonthsAgo->format('Y-m-d'))->delete();
        })->monthly();
    }

    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');
    }
}