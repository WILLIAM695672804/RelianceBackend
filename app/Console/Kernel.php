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
        $schedule->command('proposals:expire')->everyMinute();
        // Absences aux rendez-vous confirmés (pénalités SLA automatiques)
        $schedule->command('sla:detect-no-shows')->everyTenMinutes();
        // Calibrage quotidien du prix suggéré sur les prix réellement acceptés
        $schedule->command('ml:calibrate-prices')->dailyAt('03:00');
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
