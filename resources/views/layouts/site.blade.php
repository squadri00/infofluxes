@php
    $siteName = \App\Models\Setting::get('site_name', config('app.name'));
    $locale = app()->getLocale();
    $rtl = \App\Support\Languages::isRtl($locale);
    $ver = fn (string $file) => asset($file).'?v='.(@filemtime(public_path($file)) ?: 1);
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $rtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', $siteName)</title>
    @hasSection('description')
        <meta name="description" content="@yield('description')">
        <meta property="og:description" content="@yield('description')">
        <meta name="twitter:description" content="@yield('description')">
    @endif
    <meta property="og:title" content="@yield('title', $siteName)">
    <meta property="og:site_name" content="{{ $siteName }}">
    <meta property="og:type" content="@yield('og_type', 'website')">
    @hasSection('canonical')
        <link rel="canonical" href="@yield('canonical')">
        <meta property="og:url" content="@yield('canonical')">
    @endif
    @hasSection('og_image')
        <meta property="og:image" content="@yield('og_image')">
        <meta name="twitter:card" content="summary_large_image">
    @else
        <meta name="twitter:card" content="summary">
    @endif
    @yield('robots')
    @stack('jsonld')
    {{-- Put the chosen light / dark mode on before the page is drawn, so it does not flash. --}}
    <script>try{var t=localStorage.getItem('theme');if(t==='dark'||t==='light'){document.documentElement.setAttribute('data-theme',t);}}catch(e){}</script>
    <script>try{if(localStorage.getItem('infofluxes.view')==='list'){document.documentElement.setAttribute('data-view','list');}}catch(e){}</script>
    <link rel="stylesheet" href="{{ $ver('css/site.css') }}">
</head>
<body>
    <a class="skip-link" href="#content">{{ __('site.skip_to_content') }}</a>

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
    <script src="{{ $ver('js/search-form.js') }}"></script>
    <script src="{{ $ver('js/to-top.js') }}"></script>
    <script src="{{ $ver('js/view-toggle.js') }}"></script>
    <script src="{{ $ver('js/theme.js') }}"></script>
    @stack('scripts')
</body>
</html>
