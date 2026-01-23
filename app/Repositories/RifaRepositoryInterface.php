<?php

namespace App\Repositories;

use App\Models\Rifa;
use Illuminate\Database\Eloquent\Collection;

interface RifaRepositoryInterface
{
    public function findById(int $id): ?Rifa;
    public function getActive(): Collection;
    public function getBySlug(string $slug): ?Rifa;
    public function getAvailableTickets(int $rifaId): Collection;
    public function findForUpdate(int $id): ?Rifa;
    public function search(
        array $filters = [],
        string $sortField = 'created_at',
        string $sortOrder = 'desc',
        int $perPage = 15
    );
    public function updateStatus(int $rifaId, string $status): bool;
    public function getFeaturedRifas(int $limit = 5): Collection;
    public function findBySlug(string $slug, array $relations = []): ?Rifa;
    public function updateRanking(int $rifaId, array $rankingData): bool;
}
