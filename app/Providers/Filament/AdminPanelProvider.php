<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            // The admin lists use the whole width: long names scroll inside their own cell instead of pushing the other columns away.
            ->renderHook(\Filament\View\PanelsRenderHook::HEAD_END, fn () => new \Illuminate\Support\HtmlString('<style>.if-scroll{overflow-x:auto;white-space:nowrap;scrollbar-width:thin;padding-bottom:3px}.if-scroll--name{max-width:20rem}.if-scroll--path{max-width:15rem}.if-scroll--title{max-width:15rem}.if-scroll--cats{max-width:14rem}.fi-ta-text-input{max-width:5rem}.fi-ta-actions{flex-wrap:nowrap;white-space:nowrap}.fi-ta-content{overflow-x:auto}.fi-ta-table{min-width:0}</style>'))
            // A Help button at the top of every screen, for staff.
            ->renderHook(\Filament\View\PanelsRenderHook::USER_MENU_BEFORE, fn () => auth()->user()?->isStaff() ? new \Illuminate\Support\HtmlString('<a href="'.e(\App\Filament\Pages\Help::getUrl()).'" style="display:inline-flex;align-items:center;gap:.35rem;padding:.35rem .8rem;border:1px solid rgba(128,128,128,.5);border-radius:999px;font-weight:600;font-size:.9rem;text-decoration:none;color:inherit">&#63; Help</a>') : '')
            ->brandName(fn () => config('app.name'))
            ->colors([
                'primary' => Color::Amber,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
