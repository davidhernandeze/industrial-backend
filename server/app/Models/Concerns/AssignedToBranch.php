<?php

namespace App\Models\Concerns;

use App\Models\Branch;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

/**
 * For records that can be assigned to several branches, such as users.
 * Use BelongsToBranch for records owned by exactly one branch.
 */
trait AssignedToBranch
{
    /**
     * @return MorphToMany<Branch, $this>
     */
    public function branches(): MorphToMany
    {
        return $this->morphToMany(Branch::class, 'branchable')->withTimestamps();
    }

    #[Scope]
    protected function forBranch(Builder $query, Branch|int $branch): void
    {
        $query->whereHas('branches', fn (Builder $branches) => $branches->whereKey($branch));
    }
}
