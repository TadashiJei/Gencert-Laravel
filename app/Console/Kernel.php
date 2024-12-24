<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected $commands = [
        Commands\SendScheduledEmails::class,
        Commands\CleanupOldCertificates::class,
    ];

    protected function schedule(Schedule $schedule)
    {
        // Run scheduled email sending every minute
        $schedule->command('emails:send-scheduled')
            ->everyMinute()
            ->withoutOverlapping();

        // Clean up old certificates every day at midnight
        $schedule->command('certificates:cleanup')
            ->daily()
            ->at('00:00')
            ->withoutOverlapping();
    }

    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
