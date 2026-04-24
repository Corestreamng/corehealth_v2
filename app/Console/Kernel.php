<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // Bed billing now runs automatically via AppServiceProvider
        // No cron/scheduler needed for shared hosting environments

        // Sync HMO executives to messenger group every hour
        $schedule->command('hmo:sync-executives-group')->hourly();

        // Automated daily database backup at 2:00 AM
        $schedule->command('backup:database')->dailyAt('02:00')
                 ->appendOutputTo(storage_path('logs/backup.log'));
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
