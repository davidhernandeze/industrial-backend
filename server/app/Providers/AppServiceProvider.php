<?php

namespace App\Providers;

use App\Http\Middleware\RequireBranchAccess;
use App\Http\Middleware\RequireRole;
use App\Http\Responses\LoginResponse;
use App\Support\CurrentBranch;
use BladeUI\Icons\Factory;
use Filament\Auth\Http\Responses\Contracts\LoginResponse as LoginResponseContract;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(CurrentBranch::class);
        $this->app->bind(LoginResponseContract::class, LoginResponse::class);

        $this->callAfterResolving(Factory::class, function (Factory $icons): void {
            $icons->add('app', ['path' => resource_path('svg'), 'prefix' => 'app']);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Livewire::addPersistentMiddleware([RequireRole::class, RequireBranchAccess::class]);
    }
}
