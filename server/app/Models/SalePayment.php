<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Models\Concerns\Auditable;
use Database\Factories\SalePaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Today a sale has one payment. The table exists so sales can be split across methods later.
 */
#[Fillable(['method', 'amount', 'amount_received', 'reference'])]
class SalePayment extends Model
{
    /** @use HasFactory<SalePaymentFactory> */
    use Auditable, HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'method' => PaymentMethod::class,
            'amount' => 'decimal:2',
            'amount_received' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Sale, $this>
     */
    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    /**
     * The change given back, when the amount received was recorded.
     */
    protected function change(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->amount_received === null
            ? null
            : bcsub($this->amount_received, $this->amount, 2));
    }
}
