@extends('layouts.site')

@php
    $title = $article->title;
    $summary = trim(preg_replace('/\s+/', ' ', strip_tags((string) $article->short_detail)));
    $siteTitle = \App\Models\Setting::get('site_name', config('app.name'));
    $date = $article->publish_date ?: $article->created_at;
    $body = \App\Support\Html::clean((string) $article->long_detail);
    $videos = $article->videos->filter(fn ($v) => filled($v->youtube_id));
    $others = $article->extraCategories->filter(fn ($c) => $c->is_visible);
@endphp

@section('title', $article->page_title ?: $title.' | '.$siteTitle)
@section('description', $article->meta_description ?: \Illuminate\Support\Str::limit($summary ?: trim(preg_replace('/\s+/', ' ', strip_tags($body))), 160))
@section('og_type', 'article')
@if ($article->main_image)
    @section('og_image', asset('storage/'.$article->main_image))
@endif
@section('canonical', route('article.show', $article->slug))
@if ($preview)
    @section('robots')
        <meta name="robots" content="noindex">
    @endsection
@endif

@php $listenTracks = \App\Support\Listen::tracks($article, []); @endphp
@section('content')
@if ($preview)
    <div class="preview-banner"><div class="wrap">{{ __('site.draft_notice') }}</div></div>
@endif

@php $sidebars = (bool) $sideTree; @endphp
<div class="{{ $sidebars ? 'wrap wrap--wide reading' : 'wrap topic' }}">
@if ($sidebars)
    <aside class="reading__side reading__side--left" aria-label="{{ __('site.related_topics') }}">
        @include('partials.side-tree', ['root' => $sideRoot, 'tree' => $sideTree, 'current' => $article, 'sideFacts' => $sideFacts])
    </aside>
@endif
<article class="{{ $sidebars ? 'topic reading__main' : 'topic-inner' }}">
    <nav class="path" aria-label="Path">
        <ol>
            <li><a href="{{ route('home') }}">{{ __('site.home') }}</a></li>
            @foreach ($trail as $step)
                <li><a href="{{ route('category.show', $step->slug) }}">{{ $step->name }}</a></li>
            @endforeach
            <li aria-current="page">{{ $title }}</li>
        </ol>
    </nav>

    <h1 class="topic__title">{{ $title }} @include('partials.id-tag', ['code' => $article->code])</h1>

    @if ($article->contributor)
        {{-- A credited contributor: a small picture, the name (a link when they have a page), their role and the date. --}}
        @include('partials.byline', ['person' => $article->contributor, 'date' => $date, 'class' => 'byline--topic'])
        @if (filled($article->reviewer_name))<p class="article__byline"><span>{{ __('site.reviewed_by') }} <strong>{{ $article->reviewer_name }}</strong></span></p>@endif
    @else
        <p class="article__byline">
            @if ($article->author)<span>{{ __('site.by') }} <strong>{{ $article->author->name }}</strong></span>@endif
            @if ($date)<time datetime="{{ $date->toDateString() }}">{{ __('site.published') }} {{ $date->format('j M Y') }}</time>@endif
            @if (filled($article->reviewer_name))<span>{{ __('site.reviewed_by') }} <strong>{{ $article->reviewer_name }}</strong></span>@endif
        </p>
    @endif

    @if ($article->main_image)
        <figure class="topic__master">
            <img src="{{ asset('storage/'.$article->main_image) }}" alt="{{ $article->main_image_alt }}">
            @if (filled($article->main_image_credit) || filled($article->main_image_licence))
                <figcaption class="topic__master-credit">{{ __('site.story_picture_credit') }} {{ collect([$article->main_image_credit, $article->main_image_licence])->filter()->implode(' · ') }}</figcaption>
            @endif
        </figure>
    @endif

    @include('partials.listen')

    @if ($summary !== '')
        <div class="topic__hook"><p>{{ $summary }}</p></div>
    @endif

    @if ($body !== '')
        <div class="article__body">{!! $body !!}</div>
    @endif

    @if ($videos->isNotEmpty())
        <section class="article__videos" aria-label="{{ __('site.videos') }}">
            <h2 class="block__title">{{ __('site.videos') }}</h2>
            @foreach ($videos as $video)
                <figure class="article__video">
                    <div class="article__video-frame">
                        <iframe src="{{ \App\Support\YouTube::embedUrl($video->youtube_id) }}" title="{{ $video->title ?: $title }}" loading="lazy" allow="accelerometer; encrypted-media; gyroscope; picture-in-picture" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>
                    </div>
                    @if (filled($video->title))<figcaption>{{ $video->title }}</figcaption>@endif
                </figure>
            @endforeach
        </section>
    @endif

    @if ($article->images->isNotEmpty())
        <section class="article__pictures" aria-label="{{ __('site.pictures') }}">
            <h2 class="block__title">{{ __('site.pictures') }}</h2>
            <div class="article__picture-grid">
                @foreach ($article->images as $image)
                    <figure>
                        <img src="{{ asset('storage/'.$image->image) }}" alt="{{ $image->alt }}" loading="lazy">
                        @if (filled($image->caption))<figcaption>{{ $image->caption }}</figcaption>@endif
                    </figure>
                @endforeach
            </div>
        </section>
    @endif

    @if (filled($article->source_line))
        <p class="topic__source"><strong>{{ __('site.source') }}:</strong> {{ $article->source_line }}</p>
    @endif

    @if ($others->isNotEmpty())
        <p class="article__also">{{ __('site.also_in') }}:
            @foreach ($others as $other)<a href="{{ route('category.show', $other->slug) }}">{{ $other->name }}</a>@if (! $loop->last), @endif @endforeach
        </p>
    @endif

    @if (! empty($article->search_words))
        <p class="article__keywords">{{ __('site.keywords') }}:
            @foreach ($article->search_words as $word)<a class="article__keyword" href="{{ route('search', ['q' => $word]) }}">{{ $word }}</a>@endforeach
        </p>
    @endif

    @if ($previous || $next)
        <nav class="topic-nav" aria-label="Previous and next">
            <div class="topic-nav__row">
                @if ($previous)<a class="topic-nav__link" href="{{ route('article.show', $previous->slug) }}" rel="prev"><span class="topic-nav__dir">&larr; {{ __('site.previous') }}</span><span class="topic-nav__title">{{ $previous->title }}</span></a>@endif
                @if ($next)<a class="topic-nav__link topic-nav__link--next" href="{{ route('article.show', $next->slug) }}" rel="next"><span class="topic-nav__dir">{{ __('site.next') }} &rarr;</span><span class="topic-nav__title">{{ $next->title }}</span></a>@endif
            </div>
        </nav>
    @endif

    @include('partials.comments', ['article' => $article, 'comments' => $comments])

    @if ($related->isNotEmpty())
        <section class="block">
            <h2 class="block__title">{{ __('site.related') }}</h2>
            @include('partials.article-mosaic', ['articles' => $related])
        </section>
    @endif
@if (\App\Support\Listen::visible($listenTracks))
    @push('scripts')
        <script src="{{ asset('js/listen.js') }}?v={{ @filemtime(public_path('js/listen.js')) ?: 1 }}" defer></script>
    @endpush
@endif
</article>
@if ($sidebars)
    <aside class="reading__side reading__side--right" aria-label="{{ __('site.recent_title') }}">
        @include('partials.recent-box', ['article' => $article, 'title' => $title])
    </aside>
@endif
</div>
@endsection
