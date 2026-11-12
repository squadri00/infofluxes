{{-- The page footer: who we are, where to browse, and a few useful links. --}}
@php
    $footerName = \App\Models\Setting::get('site_name', config('app.name'));
    $footerLogo = \App\Models\Setting::get('logo');
    $browse = \App\Models\Category::query()->whereNull('parent_id')->where('is_visible', true)->orderBy('sort_order')->orderBy('id')->limit(8)->get();
    $copyright = str_replace([':year', ':site'], [now()->year, $footerName], \App\Models\Setting::get('copyright', __('site.copyright')));
@endphp
<footer class="site-footer">
    <div class="wrap wrap--wide">
        <div class="site-footer__grid">
            <div class="site-footer__brand">
                <a class="site-footer__name" href="{{ route('home') }}">
                    @if ($footerLogo)<img class="site-footer__logo" src="{{ asset('storage/'.$footerLogo) }}" alt="{{ $footerName }}">@else{{ $footerName }}@endif
                </a>
                <p class="site-footer__tagline">{{ \App\Models\Setting::get('tagline', __('site.tagline')) }}</p>
                @if (filled(\App\Models\Setting::get('footer_about')))<p class="site-footer__about">{{ \App\Models\Setting::get('footer_about') }}</p>@endif
                @include('partials.social-links', ['class' => 'social--footer'])
            </div>

            @if ($browse->isNotEmpty())
                <nav class="site-footer__col" aria-labelledby="footer-browse">
                    <h2 class="site-footer__title" id="footer-browse">{{ __('site.footer_browse') }}</h2>
                    <ul class="site-footer__list">
                        @foreach ($browse as $category)
                            <li><a href="{{ route('category.show', $category->slug) }}">{{ $category->name }}</a></li>
                        @endforeach
                    </ul>
                </nav>
            @endif

            <nav class="site-footer__col" aria-labelledby="footer-take-part">
                <h2 class="site-footer__title" id="footer-take-part">{{ __('site.footer_take_part') }}</h2>
                <ul class="site-footer__list">
                    <li><a href="{{ route('search') }}">{{ __('site.footer_search') }}</a></li>
                    <li><a href="{{ route('sitemap') }}">{{ __('site.footer_sitemap') }}</a></li>
                </ul>
            </nav>
        </div>

        <div class="site-footer__bar">
            <p class="site-footer__copy">{{ $copyright }}</p>
        </div>
    </div>
</footer>
