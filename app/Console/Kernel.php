<?php

namespace App\Console;

use App\Console\Commands\ClearExpiredOrdersCommand;
use App\Console\Commands\ProcessPendingPaymentsCommand;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Limpa pedidos expirados a cada hora
        $schedule->command(ClearExpiredOrdersCommand::class)->hourly();
        
        // Processa pagamentos pendentes a cada 5 minutos
        $schedule->command(ProcessPendingPaymentsCommand::class)->everyFiveMinutes();
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
