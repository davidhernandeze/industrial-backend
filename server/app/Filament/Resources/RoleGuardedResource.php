<?php

namespace App\Filament\Resources;

use App\Http\Middleware\RequireRole;
use Filament\Resources\Resource;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * Every resource lists its allowed roles in $routeMiddleware. The middleware guards the
 * routes, and the same declaration decides whether the resource shows in the interface.
 */
abstract class RoleGuardedResource extends Resource
{
    protected static string|array $routeMiddleware = 'role:admin';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        if ($user === null) {
            return false;
        }

        foreach (Arr::wrap(static::$routeMiddleware) as $middleware) {
            if (str_starts_with($middleware, 'role:')
                && ! RequireRole::allows($user, explode(',', Str::after($middleware, 'role:')))) {
                return false;
            }
        }

        return parent::canAccess();
    }
}
