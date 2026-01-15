<?php

namespace App\Console\Commands;

use App\Jobs\Orders\ExpireReservedOrders;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ClearExpiredOrdersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'orders:clear-expired {--sync : Executa o comando de forma síncrona}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove pedidos expirados e libera os números reservados para compra';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Iniciando processo de limpeza de pedidos expirados...');
        
        try {
            if ($this->option('sync')) {
                $this->info('Modo síncrono ativado. Executando limpeza imediatamente...');
                $minutes = config('payment.order_expiration', 60);
                $this->info("Removendo pedidos expirados (mais de {$minutes} minutos atrás)...");
                
                $count = app(\App\Repositories\OrderRepository::class)
                    ->removeExpiredOrders($minutes);
                
                $this->info("Foram removidos {$count} pedidos expirados.");
            } else {
                $this->info('Enviando job para processamento assíncrono...');
                ExpireReservedOrders::dispatch();
                $this->info('Job de limpeza de pedidos expirados foi enfileirado com sucesso!');
            }
            
            $this->info('Processo concluído com sucesso!');
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error('Ocorreu um erro ao processar os pedidos expirados: ' . $e->getMessage());
            Log::error('Erro ao processar pedidos expirados', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return Command::FAILURE;
        }
    }
}
