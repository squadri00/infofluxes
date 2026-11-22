{{-- The page footer: who we are, where to browse, and a few useful links. --}}
@php
    $footerName = \App\Models\Setting::get('site_name', config('app.name'));
    $footerLogo = \App\Models\Setting::get('logo');
    $footerWithName = (bool) filter_var(\App\Models\Setting::get('logo_with_name', true), FILTER_VALIDATE_BOOLEAN);
    $browse = \App\Models\Category::query()->whereNull('parent_id')->where('is_visible', true)->orderBy('sort_order')->orderBy('id')->limit(8)->get();
    $footerPages = \App\Models\Page::query()->published()->where('in_footer', true)->orderBy('sort_order')->orderBy('id')->get();
    $copyright = str_replace([':year', ':site'], [now()->year, $footerName], \App\Models\Setting::get('copyright', __('site.copyright')));
@endphp
<footer class="site-footer">
    <div class="wrap wrap--wide">
        <div class="site-footer__grid">
            <div class="site-footer__brand">
                <a class="site-footer__name" href="{{ route('home') }}">
                    @if ($footerLogo)<img class="site-footer__logo" src="{{ asset('storage/'.$footerLogo) }}" alt="{{ $footerWithName ? '' : $footerName }}">@endif
                    @if (! $footerLogo || $footerWithName)<span>{{ $footerName }}</span>@endif
                </a>
                <p class="site-footer__tagline">{{ \App\Models\Setting::get('tagline', __('site.tagline')) }}</p>
                @if (filled(\App\Models\Setting::get('footer_about')))<p class="site-footer__about">{{ \App\Models\Setting::get('footer_about') }}</p>@endif
                @include('partials.social-links', ['class' => 'social--footer'])
                @if (\App\Support\Newsletter::enabled())
                    <p class="site-footer__subscribe">
                        <a class="newsletter-btn" href="{{ route('newsletter.show') }}" data-nl-open>
                            <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false"><rect x="3.5" y="6" width="17" height="12" rx="2.2" fill="none" stroke="currentColor" stroke-width="2"/><path d="M4.5 7.5l7.5 5.7 7.5-5.7" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                            {{ __('site.footer_subscribe') }}
                        </a>
                    </p>
                @endif
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

            @if ($footerPages->isNotEmpty())
                <nav class="site-footer__col" aria-labelledby="footer-about">
                    <h2 class="site-footer__title" id="footer-about">{{ __('site.footer_about') }}</h2>
                    <ul class="site-footer__list">
                        @foreach ($footerPages as $footerPage)
                            <li><a href="{{ route('article.show', $footerPage->slug) }}" @if (request()->path() === $footerPage->slug) aria-current="page" @endif>{{ $footerPage->title }}</a></li>
                        @endforeach
                    </ul>
                </nav>
            @endif

            <nav class="site-footer__col" aria-labelledby="footer-take-part">
                <h2 class="site-footer__title" id="footer-take-part">{{ __('site.footer_take_part') }}</h2>
                <ul class="site-footer__list">
                    @if (\App\Support\Challenge::anyOn())
                        <li><a href="{{ route('challenge') }}">{{ __('site.challenge') }}</a></li>
                    @endif
                    @if (\App\Models\Story::published()->exists())
                        <li><a href="{{ route('stories') }}">{{ __('site.stories_title') }}</a></li>
                    @endif
                    @if (\App\Support\Gallery::on())
                        <li><a href="{{ route('gallery') }}">{{ __('site.gallery_nav') }}</a></li>
                    @endif
                    @if (\App\Models\Faq::active()->exists())
                        <li><a href="{{ route('faq') }}">{{ __('site.faq_short') }}</a></li>
                    @endif
                    @if (\App\Models\Explainer::query()->published()->exists())
                        <li><a href="{{ route('words') }}">{{ __('site.words_title') }}</a></li>
                    @endif
                    @if (\App\Models\DidYouKnow::query()->published()->whereNotNull('source')->exists())
                        <li><a href="{{ route('did-you-know') }}">{{ __('site.dyk_title') }}</a></li>
                    @endif
                    @if (\App\Models\Person::query()->where('show_page', true)->exists())
                        <li><a href="{{ route('people.index') }}">{{ __('site.people_title') }}</a></li>
                    @endif
                    @if (\App\Support\Newsletter::enabled())
                        <li><a href="{{ route('newsletter.show') }}">{{ __('site.footer_subscribe') }}</a></li>
                    @endif
                    @auth
                        <li><a href="{{ route('account') }}">{{ __('site.my_account') }}</a></li>
                        <li><a href="{{ route('submit') }}">{{ __('site.submit_article') }}</a></li>
                    @else
                        <li><a href="{{ route('login') }}">{{ __('site.login') }}</a></li>
                        @if ((bool) \App\Models\Setting::get('signups_open', true))
                            <li><a href="{{ route('register') }}">{{ __('site.signup') }}</a></li>
                        @endif
                    @endauth
                    <li><a href="{{ route('search') }}">{{ __('site.footer_search') }}</a></li>
                    <li><a href="{{ route('sitemap') }}">{{ __('site.footer_sitemap') }}</a></li>
                </ul>
            </nav>
        </div>

        <div class="site-footer__bar">
            <p class="site-footer__copy">{{ $copyright }}
                @if (\App\Support\Seo::consentOn())
                    &middot; <button type="button" class="linklike" style="padding:0" data-consent-open>{{ __('consent.footer_button') }}</button>
                @endif
            </p>
        </div>
    </div>
</footer>
