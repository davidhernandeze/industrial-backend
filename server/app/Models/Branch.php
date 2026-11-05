<?php

namespace App\Models;

use App\Enums\Role;
use App\Models\Concerns\Auditable;
use Database\Factories\BranchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'address', 'google_maps_url', 'opening_date'])]
class Branch extends Model
{
    /** @use HasFactory<BranchFactory> */
    use Auditable, HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'opening_date' => 'date:Y-m-d',
        ];
    }

    protected static function booted(): void
    {
        static::created(fn (self $branch) => $branch->assignToAdmins());
        static::restored(fn (self $branch) => $branch->assignToAdmins());
    }

    private function assignToAdmins(): void
    {
        User::query()->where('role', Role::Admin)->each(
            fn (User $admin) => $admin->branches()->syncWithoutDetaching($this),
        );
    }
}
