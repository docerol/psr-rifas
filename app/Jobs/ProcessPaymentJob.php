<?php

namespace App\Jobs;

use App\Models\Order;
use App\Repositories\OrderRepository;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessPaymentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * O número de vezes que o job pode ser tentado.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * O número de segundos a aguardar antes de tentar novamente o job.
     *
     * @var array
     */
    public $backoff = [60, 300, 600];

    /**
     * A instância do pedido.
     *
     * @var \App\Models\Order
     */
    protected $order;

    /**
     * Os dados do pagamento.
     *
     * @var array
     */
    protected $paymentData;

    /**
     * Cria uma nova instância do job.
     *
     * @param  \App\Models\Order  $order
     * @param  array  $paymentData
     * @return void
     */
    public function __construct(Order $order, array $paymentData = [])
    {
        $this->order = $order;
        $this->paymentData = $paymentData;
        $this->onQueue('payments');
    }

    /**
     * Executa o job.
     *
     * @param  \App\Repositories\OrderRepository  $orderRepository
     * @return void
     */
    public function handle(OrderRepository $orderRepository)
    {
        try {
            // Verifica se o pedido já foi pago
            if ($this->order->status === Order::STATUS_PAID) {
                Log::info("Pedido #{$this->order->id} já está pago.");
                return;
            }

            // Verifica se o pedido expirou
            if ($this->order->isExpired()) {
                Log::warning("Não foi possível processar o pagamento. O pedido #{$this->order->id} expirou.");
                return;
            }

            // Aqui você pode adicionar a lógica de processamento do pagamento
            // com o gateway de pagamento de sua escolha
            // Exemplo: Mercado Pago, PagSeguro, Stripe, etc.
            
            // Simulação de processamento de pagamento bem-sucedido
            $paymentSuccessful = $this->processPayment();

            if ($paymentSuccessful) {
                // Atualiza o status do pedido para pago
                $orderRepository->markAsPaid($this->order);
                
                // Dispara evento de pagamento aprovado, se necessário
                // event(new PaymentApproved($this->order));
                
                Log::info("Pagamento aprovado para o pedido #{$this->order->id}");
            } else {
                // Se o pagamento falhar, o job será tentado novamente automaticamente
                // devido à configuração de $tries e $backoff
                throw new \Exception("Falha ao processar o pagamento");
            }
            
        } catch (\Exception $e) {
            Log::error("Erro ao processar pagamento do pedido #{$this->order->id}: " . $e->getMessage(), [
                'exception' => $e,
                'order_id' => $this->order->id,
            ]);
            
            // Relança a exceção para que o job seja tentado novamente
            throw $e;
        }
    }

    /**
     * Processa o pagamento com o gateway de pagamento.
     * 
     * @return bool Retorna true se o pagamento foi aprovado, false caso contrário
     */
    protected function processPayment(): bool
    {
        // Implemente aqui a lógica de processamento de pagamento
        // com o gateway de pagamento de sua escolha
        
        // Exemplo com Mercado Pago (implementação fictícia)
        // $mp = new MP(env('MP_ACCESS_TOKEN'));
        // $payment = $mp->post('/v1/payments', $this->paymentData);
        // return $payment['status'] === 'approved';
        
        // Por enquanto, apenas simulamos um pagamento bem-sucedido
        return true;
    }
    
    /**
     * Calcula o número de segundos para esperar antes de tentar novamente o job.
     *
     * @return array
     */
    public function backoff()
    {
        return $this->backoff;
    }
    
    /**
     * Manipula uma falha no job.
     *
     * @param  \Throwable  $exception
     * @return void
     */
    public function failed(\Throwable $exception)
    {
        // Notifica o administrador sobre a falha
        // Mail::to('admin@example.com')->send(new PaymentFailed($this->order, $exception));
        
        Log::error("Falha ao processar o pagamento do pedido #{$this->order->id} após {$this->tries} tentativas", [
            'exception' => $exception,
            'order_id' => $this->order->id,
        ]);
    }
}
