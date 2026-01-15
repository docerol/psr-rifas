<?php

namespace App\Jobs\Orders;

use App\Models\Order;
use App\Repositories\OrderRepository;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ExpireReservedOrders implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Número de tentativas de execução do job.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * Número de segundos de espera antes de tentar novamente.
     *
     * @var array
     */
    public $backoff = [60, 300, 600];

    /**
     * Cria uma nova instância do job.
     */
    public function __construct()
    {
        $this->onQueue('orders');
    }

    /**
     * Executa o job.
     */
    public function handle(OrderRepository $orderRepository): void
    {
        try {
            $minutes = config('payment.order_expiration', 60);
            $expiredCount = $orderRepository->removeExpiredOrders($minutes);
            
            if ($expiredCount > 0) {
                Log::info("Foram removidos $expiredCount pedidos expirados");
            }
        } catch (\Exception $e) {
            Log::error('Erro ao processar pedidos expirados: ' . $e->getMessage(), [
                'exception' => $e,
                'trace' => $e->getTraceAsString()
            ]);
            
            // Relança a exceção para que o job seja tentado novamente
            throw $e;
        }
    }

    /**
     * Trata uma falha no job.
     */
    public function failed(\Throwable $exception): void
    {
        Log::emergency('Falha ao processar pedidos expirados após várias tentativas', [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);
    }
}
