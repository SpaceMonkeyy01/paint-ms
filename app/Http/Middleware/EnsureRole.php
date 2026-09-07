<?php

namespace App\Http\Middleware;

use App\Enums\Role;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate a route group by role: ->middleware('role:store').
 * Admin passes every gate; everyone else must match one of the listed roles.
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $role = $request->user()?->role;

        if ($role === Role::Admin || in_array($role?->value, $roles, true)) {
            return $next($request);
        }

        abort(403);
    }
}
