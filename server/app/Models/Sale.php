<?php

namespace App\Models;

use App\Enums\MovementType;
use App\Enums\SaleStatus;
use App\Enums\SaleType;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use Database\Factories\SaleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * A sale groups the inventory movements that make up its lines. Create and cancel sales
 * through App\Services\Sales.
 */
#[Fillable(['uuid', 'folio', 'user_id', 'client_id', 'status', 'type', 'total', 'meta', 'cancelled_at', 'cancelled_by', 'cancellation_reason'])]
class Sale extends Model
{
    /** @use HasFactory<SaleFactory> */
    use Auditable, BelongsToBranch, HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SaleStatus::class,
            'type' => SaleType::class,
            'total' => 'decimal:2',
            'meta' => 'array',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    /**
     * A removed client stays on the sales it was part of.
     *
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class)->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by')->withTrashed();
    }

    /**
     * @return HasMany<SalePayment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(SalePayment::class);
    }

    /**
     * The lines of the sale, without the movements that reversed them on cancellation.
     *
     * @return MorphMany<InventoryMovement, $this>
     */
    public function lines(): MorphMany
    {
        return $this->morphMany(InventoryMovement::class, 'reference')->where('type', MovementType::Sale);
    }

    public function isCancelled(): bool
    {
        return $this->status === SaleStatus::Cancelled;
    }
}
