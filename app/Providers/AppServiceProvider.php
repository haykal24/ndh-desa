<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Illuminate\Pagination\Paginator;
use Filament\Http\Responses\Auth\Contracts\LogoutResponse as LogoutResponseContract;
use App\Http\Responses\LogoutResponse;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register custom logout response
        $this->app->bind(LogoutResponseContract::class, LogoutResponse::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Super Admin bypass all permission checks
        Gate::before(function ($user, $ability) {
            error_log(sprintf('[Gate::before] Ability: %s, User: %s', $ability, $user?->email ?? 'null'));
            
            if ($user && $user->hasRole('super_admin')) {
                error_log('[Gate::before] Super admin - allowing access');
                return true;
            }
            
            return null; // Continue to other gates
        });

        // Define Gate for Filament panel access
        Gate::define('viewFilamentAdminPanel', function ($user) {
            error_log(sprintf('[Gate::viewFilamentAdminPanel] Checking access for user: %s', $user?->email ?? 'null'));
            
            if (!$user) {
                error_log('[Gate::viewFilamentAdminPanel] No user - denying access');
                return false;
            }
            
            $roles = $user->roles->pluck('name')->toArray();
            $hasAccess = $user->hasAnyRole(['super_admin', 'admin']);
            
            error_log(sprintf(
                '[Gate::viewFilamentAdminPanel] User %s (ID: %d) - Roles: %s - Has Access: %s',
                $user->email,
                $user->id,
                implode(', ', $roles),
                $hasAccess ? 'YES' : 'NO'
            ));
            
            return $hasAccess;
        });

        // Set default pagination view
        Paginator::defaultView('vendor.pagination.card');
        Paginator::defaultSimpleView('vendor.pagination.simple-tailwind');
    }
}