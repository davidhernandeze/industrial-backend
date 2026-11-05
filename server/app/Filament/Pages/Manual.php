<?php

namespace App\Filament\Pages;

use App\Enums\Role;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

/**
 * The user manual. Its text lives in lang/es/manual.php, so updating it needs no change here.
 */
class Manual extends Page
{
    protected string $view = 'filament.pages.manual';

    protected static string|array $routeMiddleware = 'role:branch_manager';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static ?int $navigationSort = 6;

    protected static ?string $slug = 'manual';

    public static function getNavigationLabel(): string
    {
        return __('manual.title');
    }

    public function getTitle(): string|Htmlable
    {
        return __('manual.title');
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->canActAs(Role::BranchManager) === true;
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return ['sections' => trans('manual.sections')];
    }
}
