<?php

namespace App\Providers\Filament;

use App\Enums\Role;
use App\Filament\Navigation\SidebarNavigation;
use App\Filament\Pages\PointOfSale;
use App\Http\Controllers\CurrentBranchController;
use App\Models\Setting;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationBuilder;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->databaseNotifications(fn (): bool => ! self::isCashier())
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->topNavigation(fn (): bool => self::isCashier())
            ->homeUrl(fn (): ?string => self::isCashier() ? PointOfSale::getUrl() : null)
            ->brandName(fn (): string => Setting::valueOf('company_name') ?: config('app.name'))
            ->navigation(fn (NavigationBuilder $navigation): NavigationBuilder => $navigation->items(SidebarNavigation::globalItems()))
            ->renderHook(PanelsRenderHook::SIDEBAR_NAV_END, fn (): string => self::isCashier() ? '' : view('filament.sidebar.branch-section')->render())
            ->authenticatedRoutes(function (): void {
                Route::post('current-branch', CurrentBranchController::class)
                    ->middleware('branch.access')
                    ->name('current-branch');
            })
            ->colors([
                'primary' => Color::Amber,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }

    private static function isCashier(): bool
    {
        return auth()->user()?->hasRole(Role::Cashier) === true;
    }
}
