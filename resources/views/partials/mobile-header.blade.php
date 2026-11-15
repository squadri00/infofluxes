{{-- The header for small screens (phones): the logo, the light / dark button and the Menu button in one bar.
     The menu opens under the bar with the categories, then Log in / Sign up (or the account) and the language.
     Under the bar comes a full-width search. On bigger screens this whole part is hidden and the usual header is shown. --}}
@php
    $mLogo = \App\Models\Setting::get('logo');
    $mWithName = (bool) filter_var(\App\Models\Setting::get('logo_with_name', true), FILTER_VALIDATE_BOOLEAN);
    $mName = \App\Models\Setting::get('site_name', config('app.name'));
    $mMenu = \App\Support\SiteMenu::header();
    $mLangs = \App\Support\Languages::menu();
    $mStaff = auth()->check() && auth()->user()->canAccessPanel(\Filament\Facades\Filament::getPanel('admin'));
@endphp
<header class="m-header" data-m-header>
    <div class="m-header__bar">
        <a class="m-header__logo" href="{{ route('home') }}">
            @if ($mLogo)<img src="{{ asset('storage/'.$mLogo) }}" alt="{{ $mWithName ? '' : $mName }}">@endif
            @if (! $mLogo || $mWithName)<span>{{ $mName }}</span>@endif
        </a>
        @if (\App\Support\SiteControls::on('theme'))
            <button type="button" class="theme-toggle" data-theme-toggle data-label-to-dark="{{ __('site.theme_to_dark') }}" data-label-to-light="{{ __('site.theme_to_light') }}" aria-label="{{ __('site.theme_to_dark') }}">
                <svg class="theme-toggle__moon" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z" fill="currentColor"/></svg>
                <svg class="theme-toggle__sun" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="4.2" fill="currentColor"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3M4.9 4.9l2.1 2.1M17 17l2.1 2.1M4.9 19.1L7 17M17 7l2.1-2.1" stroke="currentColor" stroke-width="2" stroke-linecap="round" fill="none"/></svg>
            </button>
        @endif
        <button type="button" class="m-header__menu" data-m-toggle aria-expanded="false" aria-controls="m-drawer" hidden>{{ __('site.menu') }}</button>
    </div>

    <nav class="m-drawer" id="m-drawer" data-m-drawer aria-label="{{ __('site.main_menu') }}">
        @if (count($mMenu))
            <ul class="m-drawer__list">
                @foreach ($mMenu as $item)
                    <li>
                        @if (count($item['children']))
                            <details class="m-drawer__group">
                                <summary>{{ $item['label'] }}</summary>
                                <ul>
                                    <li><a href="{{ $item['url'] }}">{{ $item['label'] }}</a></li>
                                    @foreach ($item['children'] as $child)
                                        <li><a href="{{ $child['url'] }}">{{ $child['label'] }}</a></li>
                                    @endforeach
                                </ul>
                            </details>
                        @else
                            <a href="{{ $item['url'] }}">{{ $item['label'] }}</a>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif

        <ul class="m-drawer__list m-drawer__extras">
            @if (\App\Support\Challenge::anyOn())
                <li><a href="{{ route('challenge') }}">{{ __('site.challenge') }}</a></li>
            @endif
            @auth
                <li><a href="{{ route('account') }}">{{ auth()->user()->name }}</a></li>
                @if ($mStaff)<li><a href="{{ url('/admin') }}">{{ __('site.admin') }}</a></li>@endif
                <li><form method="post" action="{{ route('logout') }}">@csrf<button type="submit">{{ __('site.logout') }}</button></form></li>
            @else
                <li><a href="{{ route('login') }}">{{ __('site.login') }}</a></li>
                @if ((bool) \App\Models\Setting::get('signups_open', true))
                    <li><a href="{{ route('register') }}">{{ __('site.signup') }}</a></li>
                @endif
            @endauth
        </ul>

        @if (\App\Support\SiteControls::on('language') && count($mLangs) >= 1)
            <details class="m-drawer__group m-drawer__lang">
                <summary>
                    @php $mCurrent = collect($mLangs)->firstWhere('code', app()->getLocale()) ?? $mLangs[0]; @endphp
                    <img class="lang-flag" src="{{ \App\Support\Languages::flagUrl($mCurrent['flag']) }}" alt="" width="22" height="15"> {{ __('site.language') }}: {{ $mCurrent['native_name'] }}
                </summary>
                <ul>
                    @foreach ($mLangs as $l)
                        <li>
                            @if ($l['status'] === 'active')
                                <a href="{{ route('language.switch', $l['code']) }}" lang="{{ $l['code'] }}" @if ($l['code'] === app()->getLocale()) aria-current="true" @endif><img class="lang-flag" src="{{ \App\Support\Languages::flagUrl($l['flag']) }}" alt="" width="22" height="15"> {{ $l['native_name'] }}</a>
                            @else
                                <span class="m-drawer__soon" lang="{{ $l['code'] }}"><img class="lang-flag" src="{{ \App\Support\Languages::flagUrl($l['flag']) }}" alt="" width="22" height="15"> {{ $l['native_name'] }} <em>{{ __('site.language_soon') }}</em></span>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </details>
        @endif
    </nav>
</header>
<div class="m-search">
    <form action="{{ route('search') }}" method="get" role="search">
        <input type="search" name="q" maxlength="200" value="{{ request()->routeIs('search') ? request('q') : '' }}" placeholder="{{ __('site.search_placeholder') }}" aria-label="{{ __('site.search_placeholder') }}">
        <button type="submit" aria-label="{{ __('site.search_button') }}">
            <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false"><circle cx="10.5" cy="10.5" r="6.5" fill="none" stroke="currentColor" stroke-width="2.2"/><path d="M15.5 15.5 21 21" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/></svg>
        </button>
    </form>
</div>
