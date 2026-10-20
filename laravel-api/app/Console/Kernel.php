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
        // Penjadwalan backup otomatis (Fase 02c — DEC-19)
        // Default aktivasi adalah OFF (opt-in via BACKUP_SCHEDULE_ENABLED=true)
        if (config('backup.schedule_enabled', false)) {
            $timezone = config('backup.schedule_timezone', 'Asia/Jakarta');
            $backupTime = config('backup.schedule_backup_time', '02:00');
            $pruneTime = config('backup.schedule_prune_time', '02:30');

            // 1. Backup harian non-interaktif
            $schedule->command('app:backup-run --non-interactive')
                ->dailyAt($backupTime)
                ->timezone($timezone)
                ->withoutOverlapping(60);

            // 2. Rotasi retensi bertingkat (7 harian + 4 mingguan)
            $schedule->command('app:backup-prune')
                ->dailyAt($pruneTime)
                ->timezone($timezone)
                ->withoutOverlapping(60);
        }
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
