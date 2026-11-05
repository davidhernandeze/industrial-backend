<?php

namespace App\Http\Middleware;

use App\Models\Branch;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rejects requests aimed at a branch the user is not assigned to. The branch comes from the
 * {branch} route parameter or the branch_id input.
 */
class RequireBranchAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $branch = $request->route('branch') ?? $request->input('branch_id');

        if ($branch !== null) {
            abort_unless(
                $request->user()?->belongsToBranch($branch instanceof Branch ? $branch : (int) $branch),
                403,
            );
        }

        return $next($request);
    }
}
