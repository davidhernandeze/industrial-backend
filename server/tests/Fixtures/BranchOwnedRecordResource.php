<?php

namespace Tests\Fixtures;

use App\Filament\Resources\Concerns\ScopedToCurrentBranch;
use App\Filament\Resources\RoleGuardedResource;
use Filament\Navigation\NavigationItem;

class BranchOwnedRecordResource extends RoleGuardedResource
{
    use ScopedToCurrentBranch;

    protected static ?string $model = BranchOwnedRecord::class;

    protected static string|array $routeMiddleware = 'role:cashier,driver';

    protected static ?int $navigationSort = 1;

    public static function getPages(): array
    {
        return [];
    }

    public static function shouldRegisterNavigation(): bool
    {
        return true;
    }

    public static function getNavigationItems(): array
    {
        return [NavigationItem::make('Fixture module')->url('/admin/fixture')];
    }
}
