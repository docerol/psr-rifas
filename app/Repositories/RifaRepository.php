<?php

namespace App\Repositories;

use App\Models\Rifa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\LengthAwarePaginator;

class RifaRepository implements RifaRepositoryInterface
{
    public function __construct(protected Rifa $model) {}

    /**
     * Encontra uma rifa pelo ID com bloqueio para atualização
     */
    public function findForUpdate(int $id): ?Rifa
    {
        return $this->model->lockForUpdate()->find($id);
    }

    public function findById(int $id): ?Rifa
    {
        return $this->model->with(['tickets', 'winner'])->find($id);
    }

    public function getActive(): Collection
    {
        return $this->model->where('status', 'active')
            ->where('draw_date', '>', now())
            ->orderBy('created_at', 'desc')
            ->get();
    }

    public function getBySlug(string $slug): ?Rifa
    {
        return $this->model->where('slug', $slug)
            ->with(['tickets' => function($query) {
                $query->where('status', 'available');
            }])
            ->first();
    }

    public function getAvailableTickets(int $rifaId): Collection
    {
        return $this->model->findOrFail($rifaId)
            ->tickets()
            ->where('status', 'available')
            ->get();
    }

    public function search(
        array $filters = [],
        string $sortField = 'created_at',
        string $sortOrder = 'desc',
        int $perPage = 15
    ): LengthAwarePaginator {
        $query = $this->model->newQuery();

        // Aplica filtros
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function (Builder $q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        return $query->orderBy($sortField, $sortOrder)
                    ->paginate($perPage);
    }

    /**
     * Atualiza o status de uma rifa
     */
    public function updateStatus(int $rifaId, string $status): bool
    {
        return $this->model->where('id', $rifaId)->update(['status' => $status]);
    }

    /**
     * Busca as principais rifas para a página inicial
     */
    public function getFeaturedRifas(int $limit = 5): Collection
    {
        return $this->model->query()
            ->where('status', Rifa::STATUS_PUBLISHED)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    public function findBySlug(string $slug, array $relations = []): ?Rifa
    {
        return $this->model->with($relations)
            ->where('slug', $slug)
            ->first();
    }
    
    public function __construct(protected Rifa $model) {}

    /**
     * Atualiza o ranking de compradores de uma rifa
     */
    public function updateRanking(int $rifaId, array $rankingData): bool
    {
        return $this->model->where('id', $rifaId)->update([
            'ranking_buyer' => $rankingData
        ]);
    }
}
