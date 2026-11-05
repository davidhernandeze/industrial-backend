<?php

namespace App\Models;

use App\Enums\ProductUnit;
use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBranch;
use App\Support\CurrentBranch;
use Database\Factories\BranchProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Arr;
use LogicException;

/**
 * A product as offered by one branch, with that branch's unit and price.
 */
#[Fillable(['product_id', 'unit', 'price'])]
#[Table('branch_product')]
class BranchProduct extends Model
{
    /** @use HasFactory<BranchProductFactory> */
    use Auditable, BelongsToBranch, HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unit' => ProductUnit::class,
            'price' => 'decimal:2',
            'stock' => 'decimal:3',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $branchProduct): void {
            throw_if($branchProduct->isDirty('unit'), LogicException::class, 'A unit is final once created.');
        });
    }

    /**
     * Soft-deleted products stay readable on the rows that reference them.
     *
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    /**
     * The row left behind when a product was removed from the current branch.
     */
    public static function removedFromCurrentBranch(mixed $productId): ?self
    {
        return static::onlyTrashed()
            ->forBranch(app(CurrentBranch::class)->get())
            ->where('product_id', $productId)
            ->first();
    }

    /**
     * Add a product to the current branch, restoring its row if it was removed before.
     * A restored row keeps its unit, which is final, and its stock.
     *
     * @param  array<string, mixed>  $data
     */
    public static function addToCurrentBranch(array $data): self
    {
        $removed = static::removedFromCurrentBranch($data['product_id']);

        if ($removed === null) {
            return static::create($data);
        }

        $removed->restore();
        $removed->update(Arr::except($data, 'unit'));

        return $removed;
    }
}
