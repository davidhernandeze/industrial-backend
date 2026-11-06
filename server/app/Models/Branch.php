<?php

namespace App\Models;

use App\Enums\Role;
use App\Models\Concerns\Auditable;
use Database\Factories\BranchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    /**
     * @return HasMany<BranchSettingValue, $this>
     */
    public function settingValues(): HasMany
    {
        return $this->hasMany(BranchSettingValue::class);
    }

    /**
     * Get this branch's value of a setting parsed by its type, or null if it has none. All
     * of the branch's values load once, so reading several settings costs a single query.
     */
    public function setting(string $key): mixed
    {
        $this->loadMissing('settingValues.branchSetting');

        return $this->settingValues
            ->first(fn (BranchSettingValue $row): bool => $row->branchSetting->key === $key)
            ?->parsed_value;
    }

    private function assignToAdmins(): void
    {
        User::query()->where('role', Role::Admin)->each(
            fn (User $admin) => $admin->branches()->syncWithoutDetaching($this),
        );
    }
}
