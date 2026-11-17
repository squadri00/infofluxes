@extends('layouts.site')

@php
    // The partials of the viewer, Listen and the book read the story under the name $article (they were made for articles first).
    $article = $story;
    $title = $story->title;
    $summary = trim(preg_replace('/\s+/', ' ', strip_tags((string) $story->short_detail)));
    $siteTitle = \App\Models\Setting::get('site_name', config('app.name'));
    $date = $story->publish_date ?: $story->created_at;
    $body = \App\Support\Html::clean((string) $story->long_detail);
    $explained = \App\Support\Explainers::render($body);   // @[word] becomes a tappable word
    $others = $story->extraCategories->filter(fn ($c) => $c->is_visible);
    $listenTracks = \App\Support\Listen::tracks($story, $storyItems ?? []);
    $storyLang = $storyLang ?? null;                  // set on /ur/stories/name and the like
    $storyRtl = $storyRtl ?? false;
    $languageLinks = $languageLinks ?? [];
    $hreflangs = $hreflangs ?? [];
    $hiddenLanguage = $hiddenLanguage ?? false;
    $showListen = ! $storyLang;                        // Listen and the PDF book are for English only
@endphp

@section('title', $story->page_title ?: $story->meta_title ?: $title.' | '.$siteTitle)
@section('description', $story->meta_description ?: \Illuminate\Support\Str::limit($summary ?: trim(preg_replace('/\s+/', ' ', strip_tags($body))), 160))
@section('og_type', 'article')
@if ($story->main_image)
    @section('og_image', asset('storage/'.$story->main_image))
@endif
@section('canonical', $storyLang ? $story->urlIn($storyLang) : $story->url())
@section('recent_title', $title)
@section('recent_url', $storyLang ? $story->urlIn($storyLang) : $story->url())
@if ($storyLang)
    @section('shell_ltr', '1')
@endif
@if ($preview || $hiddenLanguage)
    @section('robots')
        <meta name="robots" content="noindex">
    @endsection
@endif

@if (count($hreflangs) > 1)
    @push('head_links')
        @foreach ($hreflangs as $h)
            <link rel="alternate" hreflang="{{ $h['code'] }}" href="{{ $h['url'] }}">
        @endforeach
        <link rel="alternate" hreflang="x-default" href="{{ $story->url() }}">
    @endpush
@endif

@push('jsonld')
    {!! \App\Support\Seo::articleJsonLd($story, $storyLang ? $story->urlIn($storyLang) : $story->url(), \Illuminate\Support\Str::limit($summary ?: trim(preg_replace('/\s+/', ' ', strip_tags($body))), 160)) !!}
@endpush

@section('content')
@if ($preview)
    <div class="preview-banner"><div class="wrap">{{ __('site.draft_notice') }}</div></div>
@endif
@if ($hiddenLanguage)
    <div class="preview-banner"><div class="wrap">This language is hidden. Only people who work on the site can see it. Switch it on in Story translations.</div></div>
@endif

<article class="wrap topic story-page"@if ($storyLang) lang="{{ $storyLang }}" dir="{{ $storyRtl ? 'rtl' : 'ltr' }}"@endif>
    <nav class="path" aria-label="Path">
        <ol>
            <li><a href="{{ route('home') }}">{{ __('site.home') }}</a></li>
            @forelse ($trail as $step)
                <li><a href="{{ route('category.show', $step->slug) }}">{{ $step->name }}</a></li>
            @empty
                <li><a href="{{ route('stories') }}">{{ __('site.stories_title') }}</a></li>
            @endforelse
            <li aria-current="page">{{ $title }}</li>
        </ol>
    </nav>

    @if ($languageLinks)
        <nav class="story-langs" aria-label="{{ __('site.story_read_in') }}">
            <span class="story-langs__label">{{ __('site.story_read_in') }}:</span>
            @foreach ($languageLinks as $link)
                <a class="story-langs__link" href="{{ $link['url'] }}" lang="{{ $link['code'] }}" hreflang="{{ $link['code'] }}"@if ($link['current']) aria-current="true"@endif>{{ $link['native'] }}@if ($link['hidden']) <small>(hidden)</small>@endif</a>
            @endforeach
        </nav>
    @endif

    {{-- The stage: the slide viewer in a frame. Its cover shows the title and the cover picture. Nothing of the story is written outside it. --}}
    @if (! empty($storyItems))
        <section class="story-theatre" aria-label="{{ $title }}">
            <div class="story-theatre__frame">
                <div class="story {{ \App\Support\ViewerStyle::widthClass(true) }}" style="--v-box: {{ \App\Support\ViewerStyle::boxWidth() }}px" data-story data-total="{{ count($storyItems) }}">
                    @include('partials.story-viewer', ['article' => $story, 'items' => $storyItems])
                </div>
            </div>
            @php
                $hasListen = $showListen && \App\Support\Listen::visible($listenTracks);
                $hasBook = $showListen && \App\Support\StoryBook::enabled();
            @endphp
            @if ($hasListen || $hasBook)
                <div class="story-tools">
                    @if ($hasListen)
                        <details class="story-tools__listen">
                            <summary class="btn btn--small">
                                <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor" aria-hidden="true"><path d="M3 10v4h4l5 4V6L7 10H3zm13.5 2a4.5 4.5 0 0 0-2.5-4.03v8.05A4.5 4.5 0 0 0 16.5 12zM14 3.23v2.06a7 7 0 0 1 0 13.42v2.06a9 9 0 0 0 0-17.54z"/></svg>
                                {{ __('site.story_listen_tab') }}
                            </summary>
                            @include('partials.listen')
                        </details>
                    @endif
                    @if ($hasBook)
                        <a class="btn btn--small story-tools__book" href="{{ route('story.book', $story->slug) }}" rel="nofollow">{{ __('site.story_book_button') }}</a>
                    @endif
                </div>
            @endif
        </section>
        <script>window.STORY_LABELS = {!! json_encode(['goto' => __('site.story_goto')], JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) !!};</script>
        <script src="{{ asset('js/story.js') }}?v={{ @filemtime(public_path('js/story.js')) ?: 1 }}" defer></script>
    @endif

    <h1 class="{{ empty($storyItems) ? 'topic__title' : 'visually-hidden' }}">{{ $title }} @include('partials.id-tag', ['code' => $story->code])</h1>

    {{-- One slim line under the stage: who made it and when, and the like, save and share buttons. --}}
    <div class="story-meta">
    <div class="story-meta__by">
    @if ($story->contributor)
        @include('partials.byline', ['person' => $story->contributor, 'date' => $date, 'class' => 'byline--topic'])
        @if (\App\Support\SiteControls::on('reviewed_by') && filled($story->reviewer_name))<p class="article__byline"><span>{{ __('site.reviewed_by') }} <strong>{{ $story->reviewer_name }}</strong></span></p>@endif
    @else
        <p class="article__byline">
            @if ($story->author)<span>{{ __('site.by') }} <strong>{{ $story->author->name }}</strong></span>@endif
            @if ($date)<time datetime="{{ $date->toDateString() }}">{{ __('site.published') }} {{ $date->format('j M Y') }}</time>@endif
            @if (\App\Support\SiteControls::on('reviewed_by') && filled($story->reviewer_name))<span>{{ __('site.reviewed_by') }} <strong>{{ $story->reviewer_name }}</strong></span>@endif
        </p>
    @endif
    </div>
    <div class="story-meta__engage">@include('partials.engage', ['item' => $story])</div>
    </div>

    {{-- A story with no slides yet shows its cover picture and the Listen bar like an article. --}}
    @if (empty($storyItems))
        @if ($story->main_image)
            <figure class="topic__master">
                <img src="{{ asset('storage/'.$story->main_image) }}" alt="{{ $story->main_image_alt }}">
                @if (filled($story->main_image_credit) || filled($story->main_image_licence))
                    <figcaption class="topic__master-credit">{{ __('site.story_picture_credit') }} {{ collect([$story->main_image_credit, $story->main_image_licence])->filter()->implode(' · ') }}</figcaption>
                @endif
            </figure>
        @endif
        @if ($showListen)@include('partials.listen')@endif
    @endif

    {{-- A story with slides shows its words only inside the stage. The short text and the long text below are for a story with no slides yet. --}}
    @if (empty($storyItems))
        @if ($summary !== '')
            <div class="topic__hook"><p>{{ $summary }}</p></div>
        @endif

        @if ($body !== '')
            <div class="article__body">{!! $explained['html'] !!}</div>
        @endif

        @include('partials.explainer-cards', ['used' => $explained['used']])

        @if (filled($story->source_line))
            <p class="topic__source"><strong>{{ __('site.source') }}:</strong> {{ $story->source_line }}</p>
        @endif
    @endif

    {{-- The "Audience": readers comment on the story here (the same moderation as the articles). --}}
    @include('partials.comments', ['article' => $story, 'comments' => $comments, 'heading' => __('site.story_audience'), 'hint' => __('site.story_audience_hint')])

    @if ($others->isNotEmpty())
        <p class="article__also">{{ __('site.also_in') }}:
            @foreach ($others as $other)<a href="{{ route('category.show', $other->slug) }}">{{ $other->name }}</a>@if (! $loop->last), @endif @endforeach
        </p>
    @endif

    @if (! empty($story->search_words))
        <p class="article__keywords">{{ __('site.keywords') }}:
            @foreach ($story->search_words as $word)<a class="article__keyword" href="{{ route('search', ['q' => $word]) }}">{{ $word }}</a>@endforeach
        </p>
    @endif

    @if ($previous || $next)
        <nav class="topic-nav" aria-label="Previous and next">
            <div class="topic-nav__row">
                @if ($previous)<a class="topic-nav__link" href="{{ $previous->urlFor($storyLang) }}" rel="prev"><span class="topic-nav__dir">&larr; {{ __('site.previous') }}</span><span class="topic-nav__title">{{ $previous->title }}</span></a>@endif
                @if ($next)<a class="topic-nav__link topic-nav__link--next" href="{{ $next->urlFor($storyLang) }}" rel="next"><span class="topic-nav__dir">{{ __('site.next') }} &rarr;</span><span class="topic-nav__title">{{ $next->title }}</span></a>@endif
            </div>
        </nav>
    @endif

    @if ($related->isNotEmpty())
        <section class="block">
            <h2 class="block__title">{{ __('site.stories_more') }}</h2>
            @include('partials.story-grid', ['stories' => $related->take(3)])
        </section>
    @endif
</article>

@if (empty($storyItems) && ! empty($explained['used']))
    @push('scripts')
        <script src="{{ asset('js/explainers.js') }}?v={{ @filemtime(public_path('js/explainers.js')) ?: 1 }}" defer></script>
    @endpush
@endif

@push('scripts')
    <script src="{{ asset('js/share.js') }}?v={{ @filemtime(public_path('js/share.js')) ?: 1 }}" defer></script>
@endpush

@if ($showListen && \App\Support\Listen::visible($listenTracks))
    @push('scripts')
        <script src="{{ asset('js/listen.js') }}?v={{ @filemtime(public_path('js/listen.js')) ?: 1 }}" defer></script>
    @endpush
@endif
@endsection
