<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminRole
{
    public function __construct()
    {
        error_log('[EnsureAdminRole] Middleware class instantiated');
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Log to both file and stderr (visible in Docker logs)
        $logMessage = sprintf(
            '[EnsureAdminRole] Path: %s, User: %s, Authenticated: %s',
            $request->path(),
            $request->user()?->email ?? 'null',
            $request->user() ? 'yes' : 'no'
        );
        
        error_log($logMessage);
        Log::info('EnsureAdminRole middleware called', [
            'path' => $request->path(),
            'user_id' => $request->user()?->id,
            'user_email' => $request->user()?->email,
        ]);

        if (!$request->user()) {
            $msg = '[EnsureAdminRole] No authenticated user - redirecting to login';
            error_log($msg);
            Log::warning('EnsureAdminRole: No authenticated user');
            return redirect('/login');
        }

        $user = $request->user();
        $roles = $user->roles->pluck('name')->toArray();
        
        $logMessage = sprintf(
            '[EnsureAdminRole] User: %s (ID: %d), Roles: %s',
            $user->email,
            $user->id,
            implode(', ', $roles)
        );
        error_log($logMessage);
        
        Log::info('EnsureAdminRole: Checking roles', [
            'user_id' => $user->id,
            'user_email' => $user->email,
            'roles' => $roles,
        ]);

        // Check if user has super_admin or admin role
        if (!$user->hasAnyRole(['super_admin', 'admin'])) {
            $msg = sprintf(
                '[EnsureAdminRole] Access DENIED - User %s does not have required role. Current roles: %s',
                $user->email,
                implode(', ', $roles)
            );
            error_log($msg);
            Log::warning('EnsureAdminRole: User does not have required role', [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'user_roles' => $roles,
            ]);
            abort(403, 'Unauthorized access. Admin role required.');
        }

        $msg = sprintf('[EnsureAdminRole] Access GRANTED for user %s', $user->email);
        error_log($msg);
        Log::info('EnsureAdminRole: Access granted', [
            'user_id' => $user->id,
            'user_email' => $user->email,
        ]);

        return $next($request);
    }
}