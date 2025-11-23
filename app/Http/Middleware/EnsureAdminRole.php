<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->user()) {
            return redirect('/login');
        }

        // Check if user has super_admin or admin role
        if (!$request->user()->hasAnyRole(['super_admin', 'admin'])) {
            abort(403, 'Unauthorized access. Admin role required.');
        }

        return $next($request);
    }
}

