<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;
use App\Exceptions\InsufficientNumbersException;

/**
 * @property-read int|null $numbers_reserved_total
 */
class Order extends Model
{
    use HasFactory;

    /** @var string */
    public const STATUS_PAID = 'paid';

    /** @var string */
    public const STATUS_RESERVED = 'reserved';

    protected $fillable = [
        'rifa_id',
        'user_id',
        'customer_fullname',
        'customer_email',
        'customer_telephone',
        'status',
        'total',
        'expires_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected $dates = [
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'total' => 'decimal:2',
            'expires_at' => 'datetime',
        ];
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function rifa(): BelongsTo
    {
        return $this->belongsTo(Rifa::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }

    public function customerTelephone(): Attribute
    {
        return Attribute::make(
            get: fn (string $value) => preg_replace('/\D/', '', $value),
            set: fn (string $value) => preg_replace('/\D/', '', $value),
        );
    }

    public function rifa(): BelongsTo
    {
        return $this->belongsTo(Rifa::class);
    }

    public static function createWithTickets(int $rifaId, array $ticketNumbers, array $customerData, ?int $userId = null): self
    {
        return DB::transaction(function () use ($rifaId, $ticketNumbers, $customerData, $userId) {
            $rifa = Rifa::findOrFail($rifaId);
            
            // Verifica se os números estão disponíveis e aplica lock
            $tickets = Ticket::where('rifa_id', $rifaId)
                ->whereIn('number', $ticketNumbers)
                ->where('status', 'available')
                ->lockForUpdate()
                ->get();

            if ($tickets->count() !== count($ticketNumbers)) {
                throw new InsufficientNumbersException('Alguns bilhetes selecionados já foram vendidos ou reservados.');
            }

            // Cria o pedido
            $order = self::create([
                'rifa_id' => $rifaId,
                'user_id' => $userId,
                'customer_fullname' => $customerData['name'],
                'customer_email' => $customerData['email'],
                'customer_telephone' => $customerData['phone'],
                'total' => $rifa->price * count($ticketNumbers),
                'status' => self::STATUS_RESERVED,
                'expires_at' => now()->addMinutes(config('rifa.reservation_expires_after', 30)),
            ]);

            // Associa os bilhetes ao pedido
            $tickets->each(function ($ticket) use ($order) {
                $ticket->reserveForOrder($order);
            });

            return $order;
        });
    }

    public function markAsPaid(): void
    {
        $this->update(['status' => self::STATUS_PAID]);
        $this->tickets()->update(['status' => 'paid']);
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<', now())
                    ->where('status', self::STATUS_RESERVED);
    }

    public function winners(): HasMany
    {
        return $this->hasMany(Winner::class);
    }
}
