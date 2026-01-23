<?php

namespace App\Console\Commands;

use App\Jobs\ProcessPaymentJob;
use App\Models\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessPendingPaymentsCommand extends Command
{
    /**
     * O nome e a assinatura do comando.
     *
     * @var string
     */
    protected $signature = 'payments:process-pending';

    /**
     * A descrição do comando.
     *
     * @var string
     */
    protected $description = 'Processa pagamentos pendentes em lotes';

    /**
     * O número de pedidos a serem processados por vez.
     *
     * @var int
     */
    protected $batchSize = 10;

    /**
     * Executa o comando.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Iniciando processamento de pagamentos pendentes...');
        
        $processed = 0;
        $page = 1;
        
        do {
            // Busca pedidos pendentes em lotes
            $orders = Order::query()
                ->where('status', Order::STATUS_RESERVED)
                ->where('expires_at', '>', now())
                ->orderBy('created_at')
                ->with('tickets')
                ->paginate($this->batchSize, ['*'], 'page', $page);
            
            foreach ($orders as $order) {
                try {
                    // Despacha o job para processar o pagamento em segundo plano
                    ProcessPaymentJob::dispatch($order);
                    $processed++;
                    
                    $this->info("Pedido #{$order->id} enviado para processamento.");
                } catch (\Exception $e) {
                    Log::error("Erro ao processar pagamento do pedido #{$order->id}: " . $e->getMessage(), [
                        'exception' => $e,
                        'order_id' => $order->id,
                    ]);
                    
                    $this->error("Erro ao processar pagamento do pedido #{$order->id}: " . $e->getMessage());
                }
            }
            
            $page++;
        } while ($orders->hasMorePages());
        
        $this->info("Processamento concluído. Total de pedidos enviados para processamento: {$processed}");
        
        return Command::SUCCESS;
    }
}
