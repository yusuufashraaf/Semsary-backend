<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Auto-cancel unpaid rent requests every 5 minutes
        $schedule->command('rent-requests:auto-cancel')
            ->everyFiveMinutes()
            ->withoutOverlapping(10) // Prevent overlapping runs, timeout after 10 minutes
            ->runInBackground()
            ->onSuccess(function () {
                \Log::info('Auto-cancel job completed successfully');
            })
            ->onFailure(function () {
                \Log::error('Auto-cancel job failed');
                // Optionally send notification to admins
                // Notification::send(User::admins()->get(), new AutoCancelJobFailed());
            });

        // Optional: Daily cleanup of old cancelled/rejected requests
        $schedule->command('rent-requests:cleanup-old')
            ->dailyAt('02:00') // Run at 2 AM
            ->withoutOverlapping()
            ->runInBackground();

        // Optional: Weekly report of rent request statistics
        $schedule->command('rent-requests:weekly-report')
            ->weeklyOn(1, '09:00') // Monday at 9 AM
            ->runInBackground();

        // Optional: Daily notification digest for owners with pending requests
        $schedule->command('rent-requests:daily-digest')
            ->dailyAt('09:00') // 9 AM
            ->runInBackground();

        // System health check
        $schedule->command('system:health-check')
            ->everyFifteenMinutes()
            ->withoutOverlapping();

                // Release property escrows automatically
                $schedule->command('escrow:release')->everyMinute();

    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
    
}