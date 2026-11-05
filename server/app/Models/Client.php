<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Database\Factories\ClientFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A customer of the company, shared by every branch.
 */
#[Fillable(['name', 'phone', 'email', 'tax_id', 'address', 'notes'])]
class Client extends Model
{
    /** @use HasFactory<ClientFactory> */
    use Auditable, HasFactory;

    /**
     * @return HasMany<Sale, $this>
     */
    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    #[Scope]
    protected function matching(Builder $query, string $term): void
    {
        $query->where(fn (Builder $matches) => $matches
            ->where('name', 'like', "%{$term}%")
            ->orWhere('phone', 'like', "%{$term}%")
            ->orWhere('email', 'like', "%{$term}%")
            ->orWhere('tax_id', 'like', "%{$term}%"));
    }

    /**
     * The phone tells apart clients who share a name.
     */
    protected function selectLabel(): Attribute
    {
        return Attribute::get(fn (): string => filled($this->phone) ? "{$this->name} · {$this->phone}" : $this->name);
    }

    protected function taxId(): Attribute
    {
        return Attribute::set(fn (?string $value): ?string => filled($value) ? mb_strtoupper(trim($value)) : null);
    }
}
