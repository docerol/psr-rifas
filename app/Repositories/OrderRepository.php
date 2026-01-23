<?php

namespace App\Repositories;

use App\Models\Order;
use App\Models\Ticket;
use App\Models\Rifa;
use App\Exceptions\InsufficientNumbersException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class OrderRepository
{
    public function __construct(protected Order $model) {}

    /**
     * Encontra bilhetes disponíveis para uma rifa com bloqueio para evitar race conditions
     */
    public function findAvailableTickets(int $rifaId, array $ticketNumbers): Collection
    {
        return Ticket::where('rifa_id', $rifaId)
            ->whereIn('number', $ticketNumbers)
            ->where('status', 'available')
            ->lockForUpdate()
            ->get();
    }

    /**
     * Cria um novo pedido com bilhetes de forma atômica
     *
     * @throws InsufficientNumbersException
     */
    public function createOrderWithTickets(int $rifaId, array $ticketNumbers, array $customerData, ?int $userId = null): Order
    {
        return DB::transaction(function () use ($rifaId, $ticketNumbers, $customerData, $userId) {
            $rifa = Rifa::findOrFail($rifaId);
            
            // Verifica se os números estão disponíveis e aplica lock
            $tickets = $this->findAvailableTickets($rifaId, $ticketNumbers);

            if ($tickets->count() !== count($ticketNumbers)) {
                throw new InsufficientNumbersException('Alguns bilhetes selecionados já foram vendidos ou reservados.');
            }

            // Cria o pedido
            $order = $this->model->create([
                'rifa_id' => $rifaId,
                'user_id' => $userId,
                'customer_fullname' => $customerData['name'],
                'customer_email' => $customerData['email'],
                'customer_telephone' => $customerData['phone'],
                'total' => $rifa->price * count($ticketNumbers),
                'status' => Order::STATUS_RESERVED,
                'expires_at' => now()->addMinutes(config('rifa.reservation_expires_after', 30)),
            ]);

            // Associa os bilhetes ao pedido
            $tickets->each->reserveForOrder($order);

            return $order->load('tickets');
        });
    }

    /**
     * Encontra um pedido com suas relações
     */
    public function findWithRelations(int $id, array $relations = []): ?Order
    {
        return $this->model->with($relations)->find($id);
    }

    /**
     * Atualiza o status de um pedido
     */
    public function updateStatus(int $orderId, string $status): bool
    {
        return $this->model->where('id', $orderId)->update(['status' => $status]);
    }

    /**
     * Obtém pedidos expirados que ainda não foram processados
     */
    public function getExpiredOrders(int $minutes = 60)
    {
        return $this->model->query()
            ->where('expire_at', '<=', now()->subMinutes($minutes))
            ->where('status', Order::STATUS_RESERVED)
            ->get();
    }

    /**
     * Remove pedidos expirados e libera os bilhetes reservados
     */
    public function removeExpiredOrders(int $minutes = 30): int
    {
        $expiredOrders = $this->model->query()
            ->where('expires_at', '<=', now())
            ->where('status', Order::STATUS_RESERVED)
            ->with('tickets')
            ->get();

        $count = 0;

        foreach ($expiredOrders as $order) {
            try {
                DB::transaction(function () use ($order) {
                    // Libera os bilhetes reservados
                    $order->tickets->each->update([
                        'status' => 'available',
                        'order_id' => null,
                    ]);

                    // Atualiza o status do pedido para expirado
                    $order->update(['status' => 'expired']);
                });
                $count++;
            } catch (\Exception $e) {
                // Log do erro e continua com os próximos pedidos
                \Log::error("Erro ao remover pedido expirado #{$order->id}: " . $e->getMessage());
            }
        }

        return $count;
    }

    /**
     * Marca um pedido como pago e atualiza o status dos bilhetes
     */
    public function markAsPaid(Order $order): Order
    {
        return DB::transaction(function () use ($order) {
            $order->markAsPaid();
            return $order->load('tickets');
        });
    }

    /**
     * Encontra um pedido com seus bilhetes
     *
     * @throws ModelNotFoundException
     */
    public function findWithTickets(int $id): Order
    {
        return $this->model->with('tickets')->findOrFail($id);
    }
}
