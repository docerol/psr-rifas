<?php

namespace App\Repositories;

use App\Models\Order;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class OrderRepository
{
    public function __construct(protected Order $model) {}

    /**
     * Encontra números de rifa indisponíveis com bloqueio para evitar race conditions
     */
    public function findUnavailableNumbers(int $rifaId): Collection
    {
        return $this->model->query()
            ->where('rifa_id', $rifaId)
            ->lockForUpdate()
            ->pluck('numbers_reserved')
            ->flatten();
    }

    /**
     * Cria um novo pedido de forma atômica
     */
    public function createOrder(array $data): Order
    {
        return DB::transaction(function () use ($data) {
            return $this->model->create($data);
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
     * Remove pedidos expirados
     */
    public function removeExpiredOrders(int $minutes = 60): int
    {
        return $this->model->query()
            ->where('expire_at', '<=', now()->subMinutes($minutes))
            ->where('status', Order::STATUS_RESERVED)
            ->delete();
    }
}
