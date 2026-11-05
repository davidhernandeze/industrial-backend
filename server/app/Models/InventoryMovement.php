<?php

namespace App\Models;

use App\Enums\MovementType;
use App\Models\Concerns\BelongsToBranch;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

/**
 * One entry of the append-only inventory ledger. Write through App\Services\Inventory.
 *
 * This is the one business model without Auditable: rows never change or disappear, and each
 * one already records who did what, so soft deletes and entity logs would add nothing.
 */
#[Fillable(['branch_id', 'branch_product_id', 'type', 'quantity', 'balance_after', 'unit_price', 'reference_type', 'reference_id', 'note', 'user_id'])]
class InventoryMovement extends Model
{
    use BelongsToBranch;

    public const UPDATED_AT = null;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => MovementType::class,
            'quantity' => 'decimal:3',
            'balance_after' => 'decimal:3',
            'unit_price' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        $refuse = fn () => throw new LogicException('Inventory movements are append-only. Record a new movement instead.');

        static::updating($refuse);
        static::deleting($refuse);
    }

    /**
     * Rows stay readable after their product is removed from the branch.
     *
     * @return BelongsTo<BranchProduct, $this>
     */
    public function branchProduct(): BelongsTo
    {
        return $this->belongsTo(BranchProduct::class)->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    /**
     * The document behind this movement, such as a future sale.
     *
     * @return MorphTo<Model, $this>
     */
    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Sales recorded without enough stock. They are allowed, and flagged here.
     */
    #[Scope]
    protected function oversold(Builder $query): void
    {
        $query->where('type', MovementType::Sale)->where('balance_after', '<', 0);
    }

    /**
     * Whether this movement is the one that took the product from zero or more to below zero.
     */
    public function wentNegative(): bool
    {
        return bccomp($this->balance_after, '0', 3) < 0
            && bccomp(bcsub($this->balance_after, $this->quantity, 3), '0', 3) >= 0;
    }

    public function isOversold(): bool
    {
        return $this->type === MovementType::Sale && bccomp($this->balance_after, '0', 3) < 0;
    }
}
