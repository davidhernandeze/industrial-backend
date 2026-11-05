<?php

namespace App\Filament\Resources\Concerns;

use App\Filament\Concerns\ShownInBranchSection;
use App\Support\CurrentBranch;
use Illuminate\Database\Eloquent\Builder;

/**
 * For resources whose model uses BelongsToBranch or AssignedToBranch. Limits every query to
 * the current branch and places the resource below the branch selector in the sidebar.
 */
trait ScopedToCurrentBranch
{
    use ShownInBranchSection;

    public static function getEloquentQuery(): Builder
    {
        $branch = app(CurrentBranch::class)->get();
        $query = parent::getEloquentQuery();

        return $branch === null ? $query->whereRaw('1 = 0') : $query->forBranch($branch);
    }

    public static function canAccess(): bool
    {
        return app(CurrentBranch::class)->get() !== null && parent::canAccess();
    }
}
