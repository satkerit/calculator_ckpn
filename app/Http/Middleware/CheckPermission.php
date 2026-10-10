<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if (!$user || !$user->role) {
            abort(403, 'Access denied');
        }

        if (!$user->role->hasPermission($permission)) {
            abort(403, 'You do not have permission to access this resource');
        }

        return $next($request);
    }
}
