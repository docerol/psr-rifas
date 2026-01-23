<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use App\Repositories\OrderRepository;
use App\Repositories\PaymentRepository;
use App\Services\MercadoPago as MercadoPagoService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentService
{
    private const STATUS_APPROVED = 'approved';
    private const STATUS_PENDING = 'pending';

    public function __construct(
        protected PaymentRepository $paymentRepository,
        protected OrderRepository $orderRepository,
        protected MercadoPagoService $mercadoPagoService
    ) {}

    /**
     * Cria um novo pagamento PIX para um pedido
     *
     * @throws \Exception
     */
    public function createPixPayment(Order $order): Payment
    {
        try {
            return DB::transaction(function () use ($order) {
                // Verifica se já existe um pagamento para este pedido
                if ($order->payment) {
                    return $order->payment;
                }

                // Verifica se o pedido está expirado
                if ($order->isExpired()) {
                    throw new \Exception('O pedido está expirado');
                }

                // Gera o PIX no gateway de pagamento
                $pixData = $this->mercadoPagoService->generatePix($order, $order->rifa);

                // Cria o registro do pagamento
                return $this->paymentRepository->createPayment([
                    'id' => $pixData->id,
                    'ticket_url' => $pixData->ticket_url ?? null,
                    'payment_code' => $pixData->payment_method_id,
                    'date_of_expiration' => Carbon::parse($pixData->date_of_expiration)
                        ->timezone(config('app.timezone')),
                    'transaction_amount' => $pixData->transaction_amount,
                    'qr_code' => $pixData->qr_code,
                    'order_id' => $order->id,
                    'status' => self::STATUS_PENDING,
                ]);
            });
        } catch (\Exception $e) {
            Log::error('Erro ao criar pagamento PIX', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    /**
     * Processa uma notificação de webhook do gateway de pagamento
     */
    public function processWebhookNotification(Request $request): void
    {
        try {
            $data = $request->all();
            
            if (!isset($data['data']['id'])) {
                Log::warning('Webhook sem ID de pagamento', ['data' => $data]);
                return;
            }

            $paymentId = $data['data']['id'];
            
            // Busca os dados atualizados do pagamento no gateway
            $paymentInfo = $this->mercadoPagoService->getPayment($paymentId);
            
            if (!$paymentInfo) {
                Log::warning('Pagamento não encontrado no gateway', ['payment_id' => $paymentId]);
                return;
            }

            // Atualiza o status do pagamento
            $this->updatePaymentStatus(
                $paymentId,
                $paymentInfo->status,
                [
                    'status_detail' => $paymentInfo->status_detail ?? null,
                    'date_approved' => isset($paymentInfo->date_approved) 
                        ? Carbon::parse($paymentInfo->date_approved)->timezone(config('app.timezone')) 
                        : null,
                ]
            );

        } catch (\Exception $e) {
            Log::error('Erro ao processar webhook', [
                'data' => $request->all(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw $e;
        }
    }

    /**
     * Atualiza o status de um pagamento
     */
    public function updatePaymentStatus(string $paymentId, string $status, array $additionalData = []): bool
    {
        return DB::transaction(function () use ($paymentId, $status, $additionalData) {
            $updated = $this->paymentRepository->updatePaymentStatus($paymentId, $status, $additionalData);
            
            if ($updated && $status === self::STATUS_APPROVED) {
                // Atualiza o status do pedido para pago
                $payment = $this->paymentRepository->findWithRelations($paymentId, ['order']);
                
                if ($payment && $payment->order) {
                    $this->orderRepository->updateStatus($payment->order->id, Order::STATUS_PAID);
                }
            }
            
            return $updated;
        });
    }

    /**
     * Busca um pagamento pelo ID com suas relações
     */
    public function findPayment(string $id, array $relations = []): ?Payment
    {
        return $this->paymentRepository->findWithRelations($id, $relations);
    }

    /**
     * Processa um pagamento para um pedido
     *
     * @param Order $order
     * @return array
     * @throws \Exception
     */
    public function processPayment(Order $order): array
    {
        try {
            Log::info('Iniciando processamento de pagamento', [
                'order_id' => $order->id,
                'amount' => $order->total,
                'user_id' => $order->user_id,
                'rifa_id' => $order->rifa_id,
            ]);
            
            $response = $this->mercadoPagoService->createPayment([
                'transaction_amount' => $order->total,
                'description' => "Rifa #{$order->rifa_id}",
                'payment_method_id' => $order->payment_method,
                'payer' => [
                    'email' => $order->user->email,
                ],
            ]);
            
            Log::info('Pagamento processado com sucesso', [
                'order_id' => $order->id,
                'payment_id' => $response['id'] ?? null,
                'status' => $response['status'] ?? null,
            ]);
            
            return $response;
            
        } catch (\Exception $e) {
            Log::error('Erro ao processar pagamento', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'context' => [
                    'rifa_id' => $order->rifa_id,
                    'user_id' => $order->user_id,
                    'amount' => $order->total,
                ]
            ]);
            
            throw $e;
        }
    }
}
