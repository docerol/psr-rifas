<?php

namespace App\Jobs;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessPaymentWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(
        private array $paymentData
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $paymentId = $this->paymentData['data']['id'] ?? null;
            
            if (!$paymentId) {
                Log::error('ID do pagamento não encontrado no webhook', [
                    'payment_data' => $this->paymentData
                ]);
                return;
            }
            
            // Encontrar o pedido pelo ID do pagamento
            $order = Order::where('payment_id', $paymentId)->first();
            
            if (!$order) {
                Log::error('Pedido não encontrado para o pagamento', [
                    'payment_id' => $paymentId,
                    'payment_data' => $this->paymentData
                ]);
                return;
            }
            
            // Obter o status do pagamento
            $status = $this->paymentData['data']['status'] ?? null;
            
            // Atualizar o status do pedido com base no status do pagamento
            switch ($status) {
                case 'approved':
                    $order->status = 'paid';
                    $order->save();
                    
                    // Atualizar o status dos bilhetes para 'sold'
                    $order->tickets()->update(['status' => 'sold']);
                    
                    Log::info('Pagamento aprovado e pedido atualizado', [
                        'order_id' => $order->id,
                        'payment_id' => $paymentId
                    ]);
                    break;
                    
                case 'rejected':
                case 'cancelled':
                    $order->status = 'expired';
                    $order->save();
                    
                    // Liberar os números reservados
                    // Aqui você pode adicionar a lógica para liberar os números reservados
                    
                    Log::info('Pagamento rejeitado/cancelado, pedido atualizado', [
                        'order_id' => $order->id,
                        'payment_id' => $paymentId,
                        'status' => $status
                    ]);
                    break;
                    
                default:
                    Log::info('Status de pagamento não tratado', [
                        'order_id' => $order->id,
                        'payment_id' => $paymentId,
                        'status' => $status
                    ]);
                    break;
            }
            
        } catch (\Exception $e) {
            Log::error('Erro ao processar webhook do MercadoPago', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'payment_data' => $this->paymentData
            ]);
        }
    }
}
