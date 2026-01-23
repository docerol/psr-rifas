<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class RifaResource extends JsonResource
{
    /**
     * Transforma o recurso em um array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'description' => $this->description,
            'price' => (float) $this->price,
            'price_formatted' => 'R$ ' . number_format($this->price, 2, ',', '.'),
            'total_tickets' => (int) $this->total_tickets,
            'tickets_sold' => (int) $this->whenCounted('orders'),
            'tickets_available' => $this->when(
                $this->relationLoaded('orders'),
                fn () => $this->total_tickets - $this->orders_count
            ),
            'progress_percentage' => $this->when(
                $this->relationLoaded('orders') && $this->total_tickets > 0,
                fn () => (int) (($this->orders_count / $this->total_tickets) * 100)
            ),
            'thumbnail' => $this->when(
                $this->thumbnail,
                fn () => Storage::url($this->thumbnail),
                null
            ),
            'thumbnail_path' => $this->when($request->user()?->can('view', $this->resource), $this->thumbnail),
            'status' => $this->status,
            'status_label' => $this->getStatusLabel(),
            'draw_date' => $this->draw_date?->format('Y-m-d H:i:s'),
            'draw_date_formatted' => $this->draw_date?->format('d/m/Y H:i'),
            'expired_at' => $this->expired_at?->format('Y-m-d H:i:s'),
            'expired_at_formatted' => $this->expired_at?->format('d/m/Y H:i'),
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
            'can' => [
                'update' => $request->user()?->can('update', $this->resource) ?? false,
                'delete' => $request->user()?->can('delete', $this->resource) ?? false,
            ],
        ];
    }

    /**
     * Obtém o label do status da rifa
     */
    protected function getStatusLabel(): string
    {
        return match ($this->status) {
            'draft' => 'Rascunho',
            'published' => 'Publicada',
            'finished' => 'Finalizada',
            'canceled' => 'Cancelada',
            default => 'Desconhecido',
        };
    }
}
