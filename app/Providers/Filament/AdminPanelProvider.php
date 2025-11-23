<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use App\Http\Middleware\EnsureAdminRole;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Navigation\MenuItem;
use Filament\Enums\ThemeMode;
use Filament\Navigation\NavigationItem;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        // Log panel registration
        error_log('[AdminPanelProvider] Registering admin panel');
        
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->profile()
            ->defaultThemeMode(ThemeMode::Light)
            ->colors([
                'primary' => Color::Emerald,
                'success' => Color::Green,
                'warning' => Color::Amber,
                'danger' => Color::Rose,
                'info' => Color::Blue,
                'gray' => Color::Gray,
                'secondary' => Color::Indigo
            ])
            ->navigationItems([
                NavigationItem::make('Halaman Depan')
                    ->url('/', shouldOpenInNewTab: false)
                    ->icon('heroicon-o-home')
                    ->group('Dashboard')
                    ->sort(2),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                \App\Filament\Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->sidebarCollapsibleOnDesktop()
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                // Temporarily disable EnsureAdminRole to debug
                // EnsureAdminRole::class,
            ])
            ->canAccessPanelUsing(function ($user) {
                error_log(sprintf('[AdminPanelProvider] canAccessPanelUsing called for user: %s', $user?->email ?? 'null'));
                
                if (!$user) {
                    error_log('[AdminPanelProvider] No user - denying access');
                    return false;
                }
                
                $roles = $user->roles->pluck('name')->toArray();
                $hasAccess = $user->hasAnyRole(['super_admin', 'admin']);
                
                error_log(sprintf(
                    '[AdminPanelProvider] User %s (ID: %d) - Roles: %s - Has Access: %s',
                    $user->email,
                    $user->id,
                    implode(', ', $roles),
                    $hasAccess ? 'YES' : 'NO'
                ));
                
                return $hasAccess;
            })
            ->plugins([
                FilamentShieldPlugin::make()
            ])
            ->userMenuItems([
                'logout' => MenuItem::make()
                              ->label('Keluar')
                              ->postAction('/admin-logout-redirect'),
            ])
            ->brandName('Desa Digital')
            ->navigationGroups([
                'Dashboard',
                'Desa',
                'Kependudukan',
                'Bantuan Sosial',
                'Layanan Warga',
                'Administrasi Sistem',
            ])

            ->renderHook(
                'panels::head.end',
                fn () => '
                <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>
                <script>
                    window.chart = function(config) {
                        return {
                            chart: null,
                            init() {
                                setTimeout(() => {
                                    const canvas = this.$refs.canvas;
                                    if (canvas) {
                                        try {
                                            this.chart = new Chart(canvas.getContext("2d"), {
                                                type: config.type,
                                                data: config.cachedData,
                                                options: config.options
                                            });
                                        } catch(e) {
                                            console.error("Error initializing chart:", e);
                                        }
                                    }
                                }, 100);
                            }
                        };
                    };
                </script>
                '
            );
    }
}