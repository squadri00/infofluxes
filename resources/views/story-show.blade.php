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
@endphp

@section('title', $story->page_title ?: $story->meta_title ?: $title.' | '.$siteTitle)
@section('description', $story->meta_description ?: \Illuminate\Support\Str::limit($summary ?: trim(preg_replace('/\s+/', ' ', strip_tags($body))), 160))
@section('og_type', 'article')
@if ($story->main_image)
    @section('og_image', asset('storage/'.$story->main_image))
@endif
@section('canonical', $story->url())
@if ($preview)
    @section('robots')
        <meta name="robots" content="noindex">
    @endsection
@endif

@push('jsonld')
    {!! \App\Support\Seo::articleJsonLd($story, $story->url(), \Illuminate\Support\Str::limit($summary ?: trim(preg_replace('/\s+/', ' ', strip_tags($body))), 160)) !!}
@endpush

@section('content')
@if ($preview)
    <div class="preview-banner"><div class="wrap">{{ __('site.draft_notice') }}</div></div>
@endif

<article class="wrap topic">
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

    {{-- The slide viewer comes first, above the text. Its cover shows the title and the cover picture. --}}
    @if (! empty($storyItems))
        <div class="story {{ \App\Support\ViewerStyle::widthClass(true) }}" style="--v-box: {{ \App\Support\ViewerStyle::boxWidth() }}px" data-story data-total="{{ count($storyItems) }}">
            @include('partials.story-viewer', ['article' => $story, 'items' => $storyItems])
            @if (\App\Support\StoryBook::enabled())
                <p class="story__pdf"><a class="btn btn--small" href="{{ route('story.book', $story->slug) }}" rel="nofollow">{{ __('site.story_book_button') }}</a></p>
            @endif
            @include('partials.listen')
        </div>
        <script>window.STORY_LABELS = {!! json_encode(['goto' => __('site.story_goto')], JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) !!};</script>
        <script src="{{ asset('js/story.js') }}?v={{ @filemtime(public_path('js/story.js')) ?: 1 }}" defer></script>
    @endif

    <h1 class="{{ empty($storyItems) ? 'topic__title' : 'visually-hidden' }}">{{ $title }} @include('partials.id-tag', ['code' => $story->code])</h1>

    @if ($story->contributor)
        @include('partials.byline', ['person' => $story->contributor, 'date' => $date, 'class' => 'byline--topic'])
        @if (filled($story->reviewer_name))<p class="article__byline"><span>{{ __('site.reviewed_by') }} <strong>{{ $story->reviewer_name }}</strong></span></p>@endif
    @else
        <p class="article__byline">
            @if ($story->author)<span>{{ __('site.by') }} <strong>{{ $story->author->name }}</strong></span>@endif
            @if ($date)<time datetime="{{ $date->toDateString() }}">{{ __('site.published') }} {{ $date->format('j M Y') }}</time>@endif
            @if (filled($story->reviewer_name))<span>{{ __('site.reviewed_by') }} <strong>{{ $story->reviewer_name }}</strong></span>@endif
        </p>
    @endif

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
        @include('partials.listen')
    @endif

    @if ($summary !== '')
        <div class="topic__hook"><p>{{ $summary }}</p></div>
    @endif

    @include('partials.engage', ['item' => $story])

    @if ($body !== '')
        <div class="article__body">{!! $explained['html'] !!}</div>
    @endif

    @include('partials.explainer-cards', ['used' => $explained['used']])

    @if (filled($story->source_line))
        <p class="topic__source"><strong>{{ __('site.source') }}:</strong> {{ $story->source_line }}</p>
    @endif

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
                @if ($previous)<a class="topic-nav__link" href="{{ $previous->url() }}" rel="prev"><span class="topic-nav__dir">&larr; {{ __('site.previous') }}</span><span class="topic-nav__title">{{ $previous->title }}</span></a>@endif
                @if ($next)<a class="topic-nav__link topic-nav__link--next" href="{{ $next->url() }}" rel="next"><span class="topic-nav__dir">{{ __('site.next') }} &rarr;</span><span class="topic-nav__title">{{ $next->title }}</span></a>@endif
            </div>
        </nav>
    @endif

    @if ($related->isNotEmpty())
        <section class="block">
            <h2 class="block__title">{{ __('site.stories_more') }}</h2>
            @include('partials.story-grid', ['stories' => $related])
        </section>
    @endif
</article>

@if (! empty($explained['used']))
    @push('scripts')
        <script src="{{ asset('js/explainers.js') }}?v={{ @filemtime(public_path('js/explainers.js')) ?: 1 }}" defer></script>
    @endpush
@endif

@push('scripts')
    <script src="{{ asset('js/share.js') }}?v={{ @filemtime(public_path('js/share.js')) ?: 1 }}" defer></script>
@endpush

@if (\App\Support\Listen::visible($listenTracks))
    @push('scripts')
        <script src="{{ asset('js/listen.js') }}?v={{ @filemtime(public_path('js/listen.js')) ?: 1 }}" defer></script>
    @endpush
@endif
@endsection
