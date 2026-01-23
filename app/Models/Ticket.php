<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Ticket extends Model
{
    use HasFactory;

    protected $fillable = [
        'rifa_id',
        'order_id',
        'number',
        'status',
        'price',
    ];

    protected $casts = [
        'price' => 'decimal:2',
    ];

    public function rifa(): BelongsTo
    {
        return $this->belongsTo(Rifa::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function scopeAvailable($query)
    {
        return $query->where('status', 'available');
    }

    public function scopeForRifa($query, $rifaId)
    {
        return $query->where('rifa_id', $rifaId);
    }

    public function isAvailable(): bool
    {
        return $this->status === 'available';
    }

    public function reserveForOrder(Order $order): void
    {
        $this->update([
            'status' => 'reserved',
            'order_id' => $order->id,
        ]);
    }

    public function markAsPaid(): void
    {
        $this->update(['status' => 'paid']);
    }

    public function markAsDrawn(): void
    {
        $this->update(['status' => 'drawn']);
    }
}
