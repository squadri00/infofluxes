{{-- The page header in three rows: a thin top bar, the name and main menu, and the search bar. --}}
@php
    $logo = \App\Models\Setting::get('logo');
    $withName = (bool) filter_var(\App\Models\Setting::get('logo_with_name', true), FILTER_VALIDATE_BOOLEAN);
    $headerName = \App\Models\Setting::get('site_name', config('app.name'));
    $menu = \App\Support\SiteMenu::header();
    $searchCategories = \App\Models\Category::publicChoices(1);
    $chosenCategory = request()->routeIs('search') ? (int) request('category') : 0;
    $staff = auth()->check() && auth()->user()->canAccessPanel(\Filament\Facades\Filament::getPanel('admin'));
    $locales = \App\Support\Languages::menu();
@endphp
<header class="site-header" data-site-header>
    {{-- Row 1: the tagline on the left, small tools on the right --}}
    <div class="site-header__top">
        <div class="wrap wrap--wide site-header__top-inner">
            <div class="site-header__left">
                <p class="site-header__tagline">{{ \App\Models\Setting::get('tagline', __('site.tagline')) }}</p>
                {{-- A temporary link, left of the logo. Switch it off in Site controls. --}}
                @if (\App\Support\Gallery::on() && \App\Support\SiteControls::on('gallery_header_link'))
                    <a class="site-header__gallery" data-gallery-link href="{{ route('gallery') }}" @if (request()->routeIs('gallery')) aria-current="page" @endif>&#128444; {{ __('site.gallery_nav') }}</a>
                @endif
            </div>
            {{-- The logo (or the name) sits in the middle of the top bar. --}}
            <a class="site-header__name site-header__name--top" href="{{ route('home') }}">
                @if ($logo)
                    <img class="site-header__logo" src="{{ asset('storage/'.$logo) }}" alt="{{ $withName ? '' : $headerName }}">
                @endif
                @if (! $logo || $withName)<span class="site-header__wordmark">{{ $headerName }}</span>@endif
            </a>
            <ul class="site-header__tools">
                {{-- The light / dark button comes first (far left of this row). --}}
                @if (\App\Support\SiteControls::on('theme'))
                <li class="menu__item">
                    <button type="button" class="theme-toggle" data-theme-toggle data-label-to-dark="{{ __('site.theme_to_dark') }}" data-label-to-light="{{ __('site.theme_to_light') }}" aria-label="{{ __('site.theme_to_dark') }}">
                        <svg class="theme-toggle__moon" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z" fill="currentColor"/></svg>
                        <svg class="theme-toggle__sun" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="4.2" fill="currentColor"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3M4.9 4.9l2.1 2.1M17 17l2.1 2.1M4.9 19.1L7 17M17 7l2.1-2.1" stroke="currentColor" stroke-width="2" stroke-linecap="round" fill="none"/></svg>
                    </button>
                </li>
                @endif
                @if (\App\Support\Challenge::anyOn())
                    <li class="menu__item menu__item--challenge"><a href="{{ route('challenge') }}" @if (request()->routeIs('challenge*')) aria-current="page" @endif>{{ __('site.challenge') }}</a></li>
                @endif
                @if (count($locales) >= 1 && \App\Support\SiteControls::on('language'))
                    @php $current = collect($locales)->firstWhere('code', app()->getLocale()) ?? $locales[0]; @endphp
                    <li class="menu__item">
                        <details class="menu__drop lang-menu">
                            <summary aria-label="{{ __('site.language') }}"><img class="lang-flag" src="{{ \App\Support\Languages::flagUrl($current['flag']) }}" alt="" width="22" height="15"><span class="lang-code">{{ strtoupper($current['code']) }}</span></summary>
                            <ul class="menu__sub lang-menu__list">
                                @foreach ($locales as $l)
                                    <li>
                                        @if ($l['status'] === 'active')
                                            <a href="{{ route('language.switch', $l['code']) }}" lang="{{ $l['code'] }}" @if ($l['code'] === app()->getLocale()) aria-current="true" @endif><img class="lang-flag" src="{{ \App\Support\Languages::flagUrl($l['flag']) }}" alt="" width="22" height="15"><span class="lang-name">{{ $l['native_name'] }}</span></a>
                                        @else
                                            <span class="lang-soon" lang="{{ $l['code'] }}" aria-disabled="true"><img class="lang-flag" src="{{ \App\Support\Languages::flagUrl($l['flag']) }}" alt="" width="22" height="15"><span class="lang-name">{{ $l['native_name'] }}</span><span class="lang-english">{{ __('site.language_soon') }}</span></span>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        </details>
                    </li>
                @endif
                @auth
                    @if (\App\Support\Notify::on())
                        @php $unreadNotes = \App\Support\Notify::unread(auth()->user()); @endphp
                        <li class="menu__item"><a class="bell" data-bell href="{{ route('account.notifications') }}" title="{{ __('account.bell') }}" aria-label="{{ __('account.bell') }}{{ $unreadNotes > 0 ? ' ('.$unreadNotes.')' : '' }}">&#128276;@if ($unreadNotes > 0)<span class="bell__count">{{ $unreadNotes > 99 ? '99+' : $unreadNotes }}</span>@endif</a></li>
                    @endif
                    @include('partials.account-menu', ['staff' => $staff])
                @else
                    <li class="menu__item"><a href="{{ route('login') }}">{{ __('site.login') }}</a></li>
                    @if ((bool) \App\Models\Setting::get('signups_open', true))
                        <li class="menu__item"><a class="signup-link" href="{{ route('register') }}">{{ __('site.signup') }}</a></li>
                    @endif
                @endauth
            </ul>
        </div>
    </div>

    {{-- Row 2: the main menu (the category tree). The logo is in the top bar; while scrolling the header is slim and has no logo. --}}
    <div class="site-header__main">
        <div class="wrap wrap--wide site-header__main-inner">
            <button type="button" class="site-header__search-toggle" data-search-toggle aria-expanded="false" aria-controls="header-search" aria-label="{{ __('site.search_button') }}" hidden>
                <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false"><circle cx="10.5" cy="10.5" r="6.5" fill="none" stroke="currentColor" stroke-width="2.2"/><path d="M15.5 15.5 21 21" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/></svg>
            </button>

            @if (count($menu))
                <button type="button" class="site-nav-toggle" data-nav-toggle aria-expanded="false" aria-controls="site-nav" hidden>{{ __('site.menu') }}</button>
                <nav class="menu menu--main" id="site-nav" aria-label="{{ __('site.main_menu') }}" data-site-nav data-more-label="{{ __('site.more') }}">
                    <ul class="menu__list">
                        @foreach ($menu as $item)
                            @include('partials.menu-item', ['item' => $item])
                        @endforeach
                    </ul>
                </nav>
            @endif
        </div>
    </div>

    {{-- Row 3: the search bar --}}
    <div class="site-header__search" id="header-search">
        <div class="wrap wrap--wide header-bar header-bar--search-only">
            <form class="header-search" action="{{ route('search') }}" method="get" role="search" data-search-form>
                <input type="search" name="q" class="header-search__input" maxlength="200" value="{{ request()->routeIs('search') ? request('q') : '' }}" placeholder="{{ __('site.search_placeholder') }}" aria-label="{{ __('site.search_placeholder') }}">
                @if (count($searchCategories))
                    <select name="category" class="header-search__select" aria-label="{{ __('site.search_in') }}">
                        <option value="">{{ __('site.all_categories') }}</option>
                        @foreach ($searchCategories as $id => $name)
                            <option value="{{ $id }}" @selected($chosenCategory === $id)>{{ $name }}</option>
                        @endforeach
                    </select>
                @endif
                <button type="submit" class="header-search__button" aria-label="{{ __('site.search_button') }}">
                    <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false"><circle cx="10.5" cy="10.5" r="6.5" fill="none" stroke="currentColor" stroke-width="2.2"/><path d="M15.5 15.5 21 21" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/></svg>
                </button>
            </form>
        </div>
    </div>
</header>
