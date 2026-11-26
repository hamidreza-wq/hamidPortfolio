<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Checks the signed-in administrator's role against App\Support\Roles for this route. */
class EnsureAdminPermission
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->canOpenRoute($request->route()?->getName()), 403, 'Your role does not include this part of the dashboard.');

        return $next($request);
    }
}
