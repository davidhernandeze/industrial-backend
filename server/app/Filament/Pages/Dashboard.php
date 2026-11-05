<?php

namespace App\Filament\Pages;

use App\Enums\Role;
use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Cashiers have no dashboard: their whole layout is the till.
 */
class Dashboard extends BaseDashboard
{
    public function mount(): void
    {
        if (auth()->user()?->hasRole(Role::Cashier)) {
            $this->redirect(PointOfSale::getUrl());
        }
    }

    public function getColumns(): int|array
    {
        return 3;
    }

    public function getTitle(): string|Htmlable
    {
        return __('dashboard.title');
    }
}
