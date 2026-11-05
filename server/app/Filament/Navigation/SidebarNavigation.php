<?php

namespace App\Filament\Navigation;

use App\Filament\Resources\Concerns\ScopedToCurrentBranch;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationItem;

/**
 * Splits the sidebar into entities that exist on their own and modules of the current branch.
 * A resource moves to the branch section by using ScopedToCurrentBranch.
 */
class SidebarNavigation
{
    /**
     * @return list<NavigationItem>
     */
    public static function globalItems(): array
    {
        $panel = Filament::getCurrentOrDefaultPanel();

        $pages = collect($panel->getPages());
        $resources = collect($panel->getResources())
            ->reject(fn (string $resource): bool => self::isBranchScoped($resource));

        return self::itemsFor($pages->merge($resources)->all());
    }

    /**
     * @return list<NavigationItem>
     */
    public static function branchItems(): array
    {
        $resources = collect(Filament::getCurrentOrDefaultPanel()->getResources())
            ->filter(fn (string $resource): bool => self::isBranchScoped($resource));

        return self::itemsFor($resources->all());
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

    public static function isBranchScoped(string $resource): bool
    {
        return in_array(ScopedToCurrentBranch::class, class_uses_recursive($resource), true);
    }
}
