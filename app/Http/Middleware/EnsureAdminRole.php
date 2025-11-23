<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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
        Log::info('EnsureAdminRole middleware called', [
            'path' => $request->path(),
            'user_id' => $request->user()?->id,
            'user_email' => $request->user()?->email,
        ]);

        if (!$request->user()) {
            Log::warning('EnsureAdminRole: No authenticated user');
            return redirect('/login');
        }

        $user = $request->user();
        $roles = $user->roles->pluck('name')->toArray();
        
        Log::info('EnsureAdminRole: Checking roles', [
            'user_id' => $user->id,
            'user_email' => $user->email,
            'roles' => $roles,
        ]);

        // Check if user has super_admin or admin role
        if (!$user->hasAnyRole(['super_admin', 'admin'])) {
            Log::warning('EnsureAdminRole: User does not have required role', [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'user_roles' => $roles,
            ]);
            abort(403, 'Unauthorized access. Admin role required.');
        }

        Log::info('EnsureAdminRole: Access granted', [
            'user_id' => $user->id,
            'user_email' => $user->email,
        ]);

        return $next($request);
    }
}