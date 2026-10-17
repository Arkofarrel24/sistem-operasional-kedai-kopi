<?php

namespace App\Http\Middleware;

use App\Enums\Role;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    /**
     * Only allow users whose role is in the given list.
     *
     * Usage: ->middleware('role:owner') or ->middleware('role:owner,cashier')
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $allowed = array_map(fn (string $role) => Role::from($role), $roles);

        if (! $request->user()?->hasRole(...$allowed)) {
            abort(Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
