<?php

namespace App\Http\Responses;

use App\Enums\Role;
use App\Filament\Pages\PointOfSale;
use Filament\Auth\Http\Responses\Contracts\LoginResponse as LoginResponseContract;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Livewire\Features\SupportRedirects\Redirector;

/**
 * Cashiers go straight to the till.
 */
class LoginResponse implements LoginResponseContract
{
    /**
     * @param  Request  $request
     */
    public function toResponse($request): RedirectResponse|Redirector
    {
        return $request->user()?->hasRole(Role::Cashier)
            ? redirect()->to(PointOfSale::getUrl())
            : redirect()->intended(Filament::getUrl());
    }
}
