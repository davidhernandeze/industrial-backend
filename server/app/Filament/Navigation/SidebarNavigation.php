<?php

namespace App\Filament\Navigation;

use App\Enums\Role;
use App\Filament\Concerns\ShownInBranchSection;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationItem;
use Illuminate\Support\Collection;

/**
 * Splits the sidebar into entities that exist on their own and modules of the current branch.
 * A resource moves to the branch section through ScopedToCurrentBranch, and a page through
 * ShownInBranchSection.
 */
class SidebarNavigation
{
    /**
     * @return list<NavigationItem>
     */
    public static function globalItems(): array
    {
        if (auth()->user()?->hasRole(Role::Cashier)) {
            return [];
        }

        return self::itemsFor(self::navigable()->reject(fn (string $class): bool => self::isBranchScoped($class))->all());
    }

    /**
     * @return list<NavigationItem>
     */
    public static function branchItems(): array
    {
        return self::itemsFor(self::navigable()->filter(fn (string $class): bool => self::isBranchScoped($class))->all());
    }

    /**
     * @return Collection<int, class-string>
     */
    private static function navigable(): Collection
    {
        $panel = Filament::getCurrentOrDefaultPanel();

        return collect($panel->getPages())->merge($panel->getResources());
    }

    /**
     * @param  array<class-string>  $classes
     * @return list<NavigationItem>
     */
    private static function itemsFor(array $classes): array
    {
        return collect($classes)
            ->filter(fn (string $class): bool => $class::canAccess() && $class::shouldRegisterNavigation())
            ->flatMap(fn (string $class): array => $class::getNavigationItems())
            ->sortBy(fn (NavigationItem $item): int => $item->getSort())
            ->values()
            ->all();
    }

    public static function isBranchScoped(string $class): bool
    {
        return in_array(ShownInBranchSection::class, class_uses_recursive($class), true);
    }
}
