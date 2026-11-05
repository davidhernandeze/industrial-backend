<?php

namespace App\Models\Concerns;

use App\Models\Branch;
use App\Support\CurrentBranch;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * For records owned by exactly one branch, such as delivery routes. New records go to the
 * current branch unless branch_id is set, and a signed-in user cannot place a record in a
 * branch they are not assigned to. Use AssignedToBranch for records shared by branches.
 */
trait BelongsToBranch
{
    public static function bootBelongsToBranch(): void
    {
        static::creating(function (self $model): void {
            $model->branch_id ??= app(CurrentBranch::class)->get()?->getKey();
        });

        static::saving(function (self $model): void {
            $user = auth()->user();

            if ($user !== null && $model->isDirty('branch_id') && ! $user->belongsToBranch($model->branch_id)) {
                throw new AuthorizationException;
            }
        });
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    #[Scope]
    protected function forBranch(Builder $query, Branch|int $branch): void
    {
        $query->where($this->qualifyColumn('branch_id'), $branch instanceof Branch ? $branch->getKey() : $branch);
    }
}
