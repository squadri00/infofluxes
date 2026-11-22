@extends('layouts.site')

@php
    $site = \App\Models\Setting::get('site_name', config('app.name'));
    $answer = \App\Support\Html::clean((string) $fact->body);
    $full = $fact->article && $fact->article->status === 'published' ? $fact->article : null;
    // The same page as an article: the branch on the left, the article text on the right.
    $sidebars = (bool) $sideTree;
@endphp

@section('title', $fact->title.' | '.$site)
@section('description', $fact->summary(160))
@section('canonical', $fact->url())
@section('recent_title', $fact->title)
@section('recent_url', $fact->url())
@if ($fact->image)
    @section('og_image', asset('storage/'.$fact->image))
@endif
@if ($preview)
    @section('robots')
        <meta name="robots" content="noindex">
    @endsection
@endif

@section('content')
@if ($preview)
    <div class="preview-banner"><div class="wrap">{{ __('site.draft_notice') }}</div></div>
@endif

<div class="{{ $sidebars ? 'wrap wrap--wide reading reading--two' : 'wrap topic' }}">
@if ($sidebars)
    <aside class="reading__side reading__side--left" aria-label="{{ __('site.related_topics') }}">
        @include('partials.side-tree', ['root' => $sideRoot, 'tree' => $sideTree, 'current' => null, 'currentFact' => $fact, 'sideFacts' => $sideFacts])
    </aside>
@endif
<article class="{{ $sidebars ? 'topic reading__main fact' : 'topic fact' }}">
    <nav class="path" aria-label="Path">
        <ol>
            <li><a href="{{ route('home') }}">{{ __('site.home') }}</a></li>
            @forelse ($trail as $step)
                <li><a href="{{ route('category.show', $step->slug) }}">{{ $step->name }}</a></li>
            @empty
                <li><a href="{{ route('did-you-know') }}">{{ __('site.dyk_title') }}</a></li>
            @endforelse
            <li aria-current="page">{{ $fact->title }}</li>
        </ol>
    </nav>

    <h1 class="topic__title">{{ $fact->title }} @include('partials.id-tag', ['code' => $fact->code])</h1>

    @if ($fact->image)
        <figure class="topic__master"><img src="{{ asset('storage/'.$fact->image) }}" alt="{{ $fact->image_alt }}"></figure>
    @endif

    <div class="article__body fact__answer">{!! $answer !!}</div>

    @if ($full)
        <p><a class="btn btn--primary" href="{{ route('article.show', $full->slug) }}">{{ __('site.dyk_read_article') }} &rarr;</a></p>
    @endif

    <p class="fact__meta">
        @if ($fact->sourceUrl())
            {{ __('site.dyk_source') }}: <a href="{{ $fact->sourceUrl() }}" target="_blank" rel="noopener noreferrer nofollow">{{ $fact->source }}</a>
        @elseif (filled($fact->source))
            {{ __('site.dyk_source') }}: {{ $fact->source }}
        @endif
        @if (filled($fact->reviewer_name)) &middot; {{ __('site.reviewed_by') }} {{ $fact->reviewer_name }}@endif
        @if ($fact->ai_assisted) &middot; {{ __('site.dyk_ai') }}@endif
    </p>

    @if ($previous || $next)
        <nav class="fact-nav" aria-label="{{ __('site.dyk_title') }}">
            @if ($previous)
                <a class="fact-nav__link fact-nav__link--prev" href="{{ $previous->url() }}" rel="prev">
                    <span class="fact-nav__dir"><span class="fact-nav__arrow" aria-hidden="true">&larr;</span> {{ __('site.previous') }}</span>
                    <span class="fact-nav__title">{{ $previous->title }}</span>
                </a>
            @endif
            @if ($next)
                <a class="fact-nav__link fact-nav__link--next" href="{{ $next->url() }}" rel="next">
                    <span class="fact-nav__dir">{{ __('site.next') }} <span class="fact-nav__arrow" aria-hidden="true">&rarr;</span></span>
                    <span class="fact-nav__title">{{ $next->title }}</span>
                </a>
            @endif
        </nav>
    @endif

    @if ($more->isNotEmpty())
        <section class="block">
            <h2 class="block__title">{{ __('site.dyk_more') }}</h2>
            @include('partials.article-mosaic', ['articles' => $more, 'card' => 'partials.fact-card'])
        </section>
    @endif
</article>
</div>
@endsection
