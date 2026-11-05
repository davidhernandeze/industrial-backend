<?php

namespace App\Http\Middleware;

use App\Enums\Role;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lists the roles allowed to use a service: role:cashier,driver. Admins are always allowed
 * unless the list starts with "exclusive": role:exclusive,driver.
 */
class RequireRole
{
    public const string EXCLUSIVE = 'exclusive';

    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        abort_unless($request->user() !== null && self::allows($request->user(), $roles), 403);

        return $next($request);
    }

    /**
     * @param  list<string>  $roles
     */
    public static function allows(User $user, array $roles): bool
    {
        $exclusive = ($roles[0] ?? null) === self::EXCLUSIVE;

        $allowed = array_map(
            fn (string $role): Role => Role::from($role),
            array_values(array_diff($roles, [self::EXCLUSIVE])),
        );

        return $exclusive ? $user->hasRole(...$allowed) : $user->canActAs(...$allowed);
    }
}
