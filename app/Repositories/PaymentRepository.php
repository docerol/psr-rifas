<?php

namespace App\Repositories;

use App\Models\Payment;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class PaymentRepository
{
    public function __construct(protected Payment $model) {}

    /**
     * Cria um novo pagamento
     */
    public function createPayment(array $data): Payment
    {
        return DB::transaction(function () use ($data) {
            return $this->model->create($data);
        });
    }

    /**
     * Atualiza o status de um pagamento
     */
    public function updatePaymentStatus(string $paymentId, string $status, array $additionalData = []): bool
    {
        $updateData = array_merge(['status' => $status], $additionalData);
        
        if ($status === 'approved') {
            $updateData['date_approved'] = Carbon::now();
        }
        
        return $this->model->where('id', $paymentId)
            ->update($updateData);
    }

    /**
     * Encontra um pagamento pelo ID com suas relações
     */
    public function findWithRelations(string $id, array $relations = []): ?Payment
    {
        return $this->model->with($relations)->find($id);
    }

    /**
     * Busca pagamentos com base em filtros
     */
    public function search(
        array $filters = [],
        string $sortField = 'created_at',
        string $sortOrder = 'desc',
        int $perPage = 15
    ) {
        $query = $this->model->newQuery();

        // Aplica filtros
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['order_id'])) {
            $query->where('order_id', $filters['order_id']);
        }

        if (!empty($filters['date_from'])) {
            $query->where('created_at', '>=', Carbon::parse($filters['date_from']));
        }

        if (!empty($filters['date_to'])) {
            $query->where('created_at', '<=', Carbon::parse($filters['date_to']));
        }

        return $query->orderBy($sortField, $sortOrder)
                    ->paginate($perPage);
    }

    /**
     * Atualiza os dados de um pagamento
     */
    public function updatePayment(string $id, array $data): bool
    {
        return $this->model->where('id', $id)->update($data);
    }
}
