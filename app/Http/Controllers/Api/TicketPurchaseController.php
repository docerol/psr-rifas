<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Repositories\OrderRepository;
use App\Exceptions\InsufficientNumbersException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class TicketPurchaseController extends Controller
{
    public function __construct(
        protected OrderRepository $orderRepository
    ) {}

    /**
     * Reserva bilhetes para um pedido
     */
    public function reserveTickets(Request $request)
    {
        $validated = $request->validate([
            'rifa_id' => 'required|exists:rifas,id',
            'ticket_numbers' => 'required|array|min:1',
            'ticket_numbers.*' => 'integer|min:1',
            'customer' => 'required|array',
            'customer.name' => 'required|string|max:255',
            'customer.email' => 'required|email|max:255',
            'customer.phone' => 'required|string|max:20',
        ]);

        try {
            DB::beginTransaction();

            $order = $this->orderRepository->createOrderWithTickets(
                $validated['rifa_id'],
                $validated['ticket_numbers'],
                $validated['customer'],
                auth()->id()
            );

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Bilhetes reservados com sucesso!',
                'data' => [
                    'order' => $order,
                    'expires_at' => $order->expires_at->toDateTimeString(),
                ]
            ]);

        } catch (InsufficientNumbersException $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error_code' => 'INSUFFICIENT_NUMBERS'
            ], 400);
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erro ao reservar bilhetes: ' . $e->getMessage(), [
                'exception' => $e,
                'request' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Ocorreu um erro ao processar sua solicitação. Por favor, tente novamente.'
            ], 500);
        }
    }

    /**
     * Confirma o pagamento de um pedido
     */
    public function confirmPayment($orderId)
    {
        try {
            $order = $this->orderRepository->findWithTickets($orderId);
            
            // Verifica se o pedido pertence ao usuário autenticado
            if (auth()->id() !== $order->user_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Acesso não autorizado.'
                ], 403);
            }

            // Verifica se o pedido já está pago
            if ($order->status === Order::STATUS_PAID) {
                return response()->json([
                    'success' => true,
                    'message' => 'Este pedido já foi pago anteriormente.',
                    'data' => ['order' => $order]
                ]);
            }

            // Verifica se o pedido expirou
            if ($order->isExpired()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Este pedido expirou. Por favor, faça uma nova reserva.',
                    'error_code' => 'ORDER_EXPIRED'
                ], 400);
            }

            // Marca o pedido como pago
            $order = $this->orderRepository->markAsPaid($order);

            return response()->json([
                'success' => true,
                'message' => 'Pagamento confirmado com sucesso!',
                'data' => ['order' => $order]
            ]);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Pedido não encontrado.'
            ], 404);
            
        } catch (\Exception $e) {
            Log::error('Erro ao confirmar pagamento: ' . $e->getMessage(), [
                'order_id' => $orderId,
                'exception' => $e,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Ocorreu um erro ao processar o pagamento. Por favor, tente novamente.'
            ], 500);
        }
    }
}
