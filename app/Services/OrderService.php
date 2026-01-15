<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Rifa;
use App\Repositories\OrderRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Exceptions\InsufficientNumbersException;

class OrderService
{
    public function __construct(
        protected OrderRepository $orderRepository
    ) {}

    /**
     * Cria um novo pedido de forma segura, evitando race conditions
     *
     * @throws InsufficientNumbersException
     */
    public function createOrder(array $data): Order
    {
        return DB::transaction(function () use ($data) {
            $rifa = Rifa::lockForUpdate()->findOrFail($data['rifa_id']);
            $quantity = $data['quantity'];

            // Obtém números disponíveis de forma segura
            $availableNumbers = $this->getAvailableNumbers($rifa, $quantity);

            if (count($availableNumbers) < $quantity) {
                Log::error('Números insuficientes disponíveis', [
                    'rifa_id' => $rifa->id,
                    'solicitados' => $quantity,
                    'disponiveis' => count($availableNumbers)
                ]);
                
                throw new InsufficientNumbersException(
                    'Não há números suficientes disponíveis para esta rifa.'
                );
            }

            // Prepara os dados do pedido
            $orderData = [
                'customer_fullname' => $data['fullname'],
                'customer_email' => $data['email'],
                'customer_telephone' => $data['telephone'],
                'rifa_id' => $rifa->id,
                'numbers_reserved' => $availableNumbers,
                'status' => Order::STATUS_RESERVED,
                'expire_at' => now()->addMinutes(config('payment.order_expiration', 60)),
            ];

            // Cria o pedido
            return $this->orderRepository->createOrder($orderData);
        });
    }

    /**
     * Obtém números disponíveis de forma segura
     */
    protected function getAvailableNumbers(Rifa $rifa, int $quantity): array
    {
        $rifaNumbers = collect()->range(0, $rifa->total_numbers_available - 1);
        $unavailableNumbers = $this->orderRepository->findUnavailableNumbers($rifa->id);
        
        return $rifaNumbers->diff($unavailableNumbers)
            ->shuffle()
            ->take($quantity)
            ->map(fn ($num) => str_pad($num, 4, '0', STR_PAD_LEFT))
            ->sort()
            ->values()
            ->toArray();
    }

    /**
     * Busca um pedido com suas relações
     */
    public function findOrderWithRelations(int $orderId, array $relations = []): ?Order
    {
        return $this->orderRepository->findWithRelations($orderId, $relations);
    }

    /**
     * Atualiza o status de um pedido
     */
    public function updateOrderStatus(int $orderId, string $status): bool
    {
        return $this->orderRepository->updateStatus($orderId, $status);
    }
}
