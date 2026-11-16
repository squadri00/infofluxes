@php
    $siteName = \App\Models\Setting::get('site_name', config('app.name'));
    $locale = app()->getLocale();
    // On a translated story only the story is in another language: the header and the footer stay English and left to right.
    $shellLtr = trim((string) $__env->yieldContent('shell_ltr')) !== '';
    $rtl = ! $shellLtr && \App\Support\Languages::isRtl($locale);
    $htmlLang = $shellLtr ? config('infofluxes.default_locale', 'en') : $locale;
    $ver = fn (string $file) => asset($file).'?v='.(@filemtime(public_path($file)) ?: 1);
    // The title, description and share picture: each page gives its own; the SEO settings fill what is missing.
    $pageTitle = \App\Support\Seo::title((string) $__env->yieldContent('title'));
    $pageDescription = \App\Support\Seo::description((string) $__env->yieldContent('description'));
    $pageImage = \App\Support\Seo::ogImage((string) $__env->yieldContent('og_image'));
@endphp
<!DOCTYPE html>
<html lang="{{ $htmlLang }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32.png') }}">
    <link rel="icon" type="image/png" sizes="192x192" href="{{ asset('favicon-192.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    {!! \App\Support\Seo::trackingHead() !!}
    <title>{{ $pageTitle }}</title>
    @if ($pageDescription !== '')
        <meta name="description" content="{{ $pageDescription }}">
        <meta property="og:description" content="{{ $pageDescription }}">
        <meta name="twitter:description" content="{{ $pageDescription }}">
    @endif
    <meta property="og:title" content="{{ $pageTitle }}">
    <meta property="og:site_name" content="{{ $siteName }}">
    <meta property="og:type" content="@yield('og_type', 'website')">
    <meta property="og:locale" content="{{ str_replace('-', '_', $locale) }}">
    @hasSection('canonical')
        <link rel="canonical" href="@yield('canonical')">
        <meta property="og:url" content="@yield('canonical')">
    @endif
    @stack('head_links')
    @if ($pageImage !== '')
        <meta property="og:image" content="{{ $pageImage }}">
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:image" content="{{ $pageImage }}">
    @else
        <meta name="twitter:card" content="summary">
    @endif
    @hasSection('robots')
        @yield('robots')
    @elseif (\App\Support\Seo::siteHidden())
        <meta name="robots" content="noindex, nofollow">
    @endif
    {!! \App\Support\Seo::verificationTags() !!}
    @if (request()->routeIs('home'))
        {!! \App\Support\Seo::homeJsonLd() !!}
    @endif
    @stack('jsonld')
    {{-- Put the chosen light / dark mode on before the page is drawn, so it does not flash. --}}
    <script>try{var t=localStorage.getItem('theme');if(t==='dark'||t==='light'){document.documentElement.setAttribute('data-theme',t);}}catch(e){}</script>
    @if (\App\Support\SiteControls::on('grid_list'))
    <script>try{if(localStorage.getItem('infofluxes.view')==='list'){document.documentElement.setAttribute('data-view','list');}}catch(e){}</script>
    @endif
    <link rel="stylesheet" href="{{ $ver('css/site.css') }}">
    {!! \App\Support\CardPictures::css() !!}
    {!! \App\Support\Seo::custom('head') !!}
</head>
<body>
    {!! \App\Support\Seo::trackingBody() !!}
    {!! \App\Support\Seo::custom('body_start') !!}
    <a class="skip-link" href="#content">{{ __('site.skip_to_content') }}</a>

    @include('partials.mobile-header')
    @include('partials.site-header')

    <main id="content">
        @if (session('status'))
            <div class="flash" role="status"><div class="wrap wrap--wide">{{ session('status') }}</div></div>
        @endif
        @yield('content')
    </main>

    @include('partials.site-footer')

    <button type="button" class="to-top" data-to-top hidden aria-label="{{ __('site.back_to_top') }}" title="{{ __('site.back_to_top') }}">
        <svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true" focusable="false"><path d="M6 14l6-6 6 6" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
    </button>
    <script src="{{ $ver('js/header.js') }}"></script>
    <script src="{{ $ver('js/mobile-header.js') }}"></script>
    <script src="{{ $ver('js/search-form.js') }}"></script>
    <script src="{{ $ver('js/to-top.js') }}"></script>
    <script src="{{ $ver('js/view-toggle.js') }}"></script>
    <script src="{{ $ver('js/theme.js') }}"></script>
    @stack('scripts')
    @if (\App\Support\Newsletter::enabled() && \App\Support\SiteControls::on('newsletter_popup') && ! request()->routeIs('newsletter.*'))
        @include('partials.newsletter-popup')
        <script src="{{ $ver('js/newsletter-popup.js') }}" defer></script>
    @endif
    @if (\App\Support\Seo::consentOn())
        @include('partials.consent-banner')
        <script src="{{ $ver('js/consent.js') }}"></script>
    @endif
    {!! \App\Support\Seo::custom('body_end') !!}
</body>
</html>
