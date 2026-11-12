{{-- The page header in three rows: a thin top bar, the name and main menu, and the search bar. --}}
@php
    $logo = \App\Models\Setting::get('logo');
    $headerName = \App\Models\Setting::get('site_name', config('app.name'));
    $menu = \App\Support\SiteMenu::header();
    $searchCategories = \App\Models\Category::publicChoices();
    $chosenCategory = request()->routeIs('search') ? (int) request('category') : 0;
    $staff = auth()->check() && auth()->user()->canAccessPanel(\Filament\Facades\Filament::getPanel('admin'));
    $locales = config('infofluxes.locales');
@endphp
<header class="site-header" data-site-header>
    {{-- Row 1: the tagline on the left, small tools on the right --}}
    <div class="site-header__top">
        <div class="wrap wrap--wide site-header__top-inner">
            <p class="site-header__tagline">{{ \App\Models\Setting::get('tagline', __('site.tagline')) }}</p>
            <ul class="site-header__tools">
                @if (count($locales) > 1)
                    <li class="menu__item">
                        <details class="menu__drop lang-menu">
                            <summary aria-label="{{ __('site.language') }}"><span class="lang-code">{{ strtoupper(app()->getLocale()) }}</span></summary>
                            <ul class="menu__sub lang-menu__list">
                                @foreach ($locales as $code => $name)
                                    <li><a href="{{ route('language.switch', $code) }}" lang="{{ $code }}" @if ($code === app()->getLocale()) aria-current="true" @endif><span class="lang-name">{{ $name }}</span></a></li>
                                @endforeach
                            </ul>
                        </details>
                    </li>
                @endif
                <li class="menu__item">
                    <button type="button" class="theme-toggle" data-theme-toggle data-label-to-dark="{{ __('site.theme_to_dark') }}" data-label-to-light="{{ __('site.theme_to_light') }}" aria-label="{{ __('site.theme_to_dark') }}">
                        <svg class="theme-toggle__moon" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z" fill="currentColor"/></svg>
                        <svg class="theme-toggle__sun" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="4.2" fill="currentColor"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3M4.9 4.9l2.1 2.1M17 17l2.1 2.1M4.9 19.1L7 17M17 7l2.1-2.1" stroke="currentColor" stroke-width="2" stroke-linecap="round" fill="none"/></svg>
                    </button>
                </li>
                @auth
                    <li class="menu__item"><a href="{{ route('account') }}">{{ auth()->user()->name }}</a></li>
                    @if ($staff)
                        <li class="menu__item"><a href="{{ url('/admin') }}">{{ __('site.admin') }}</a></li>
                    @endif
                    <li class="menu__item">
                        <form method="post" action="{{ route('logout') }}">@csrf<button type="submit" class="linklike">{{ __('site.logout') }}</button></form>
                    </li>
                @else
                    <li class="menu__item"><a href="{{ route('login') }}">{{ __('site.login') }}</a></li>
                    @if ((bool) \App\Models\Setting::get('signups_open', true))
                        <li class="menu__item"><a class="signup-link" href="{{ route('register') }}">{{ __('site.signup') }}</a></li>
                    @endif
                @endauth
            </ul>
        </div>
    </div>

    {{-- Row 2: the name (or logo) and the main menu (the category tree) --}}
    <div class="site-header__main">
        <div class="wrap wrap--wide site-header__main-inner">
            <a class="site-header__name" href="{{ route('home') }}">
                @if ($logo)
                    <img class="site-header__logo" src="{{ asset('storage/'.$logo) }}" alt="{{ $headerName }}">
                @else
                    {{ $headerName }}
                @endif
            </a>

            <button type="button" class="site-header__search-toggle" data-search-toggle aria-expanded="false" aria-controls="header-search" aria-label="{{ __('site.search_button') }}" hidden>
                <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false"><circle cx="10.5" cy="10.5" r="6.5" fill="none" stroke="currentColor" stroke-width="2.2"/><path d="M15.5 15.5 21 21" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/></svg>
            </button>

            @if (count($menu))
                <button type="button" class="site-nav-toggle" data-nav-toggle aria-expanded="false" aria-controls="site-nav" hidden>{{ __('site.menu') }}</button>
                <nav class="menu menu--main" id="site-nav" aria-label="{{ __('site.main_menu') }}" data-site-nav>
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
