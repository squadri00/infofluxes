@extends('layouts.site')

@php
    $site = \App\Models\Setting::get('site_name', config('app.name'));
    $answer = \App\Support\Html::clean((string) $fact->body);
    $full = $fact->article && $fact->article->status === 'published' ? $fact->article : null;
@endphp

@section('title', $fact->title.' | '.$site)
@section('description', $fact->summary(160))
@section('canonical', $fact->url())
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

<article class="wrap topic fact">
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

    @if ($more->isNotEmpty())
        <section class="block">
            <h2 class="block__title">{{ __('site.dyk_more') }}</h2>
            @include('partials.article-mosaic', ['articles' => $more, 'card' => 'partials.fact-card'])
        </section>
    @endif
</article>
@endsection
