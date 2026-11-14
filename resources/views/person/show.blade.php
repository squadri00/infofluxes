@extends('layouts.site')

@php
    $siteName = \App\Models\Setting::get('site_name', config('app.name'));
    $description = \Illuminate\Support\Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags((string) ($person->bio ?: $person->qualification_note)))), 160);
    $url = route('people.show', $person->slug);
@endphp

@section('title', $person->name.' | '.$siteName)
@if ($description !== '')
    @section('description', $description)
@endif
@if ($person->photo)
    @section('og_image', asset('storage/'.$person->photo))
@endif
@section('canonical', $url)
@if ($preview)
    @section('robots')
        <meta name="robots" content="noindex">
    @endsection
@endif

@section('content')
@if ($preview)
    <div class="preview-banner"><div class="wrap">{{ __('site.people_preview') }}</div></div>
@endif

<div class="wrap person">
    <nav class="path" aria-label="Path">
        <ol>
            <li><a href="{{ route('home') }}">{{ __('site.home') }}</a></li>
            <li><a href="{{ route('people.index') }}">{{ __('site.people_title') }}</a></li>
            <li aria-current="page">{{ $person->name }}</li>
        </ol>
    </nav>

    <header class="person__head">
        @if ($person->photo)
            <img class="person__photo" src="{{ asset('storage/'.$person->photo) }}" alt="{{ $person->photo_alt ?: $person->name }}">
        @else
            <span class="person__photo person__photo--letter" style="--h: {{ crc32($person->name) % 360 }}" aria-hidden="true">{{ mb_strtoupper(mb_substr($person->name, 0, 1)) }}</span>
        @endif
        <div>
            <h1 class="topic__title person__name">{{ $person->name }}</h1>
            @if ($person->role_title)<p class="person__role">{{ $person->role_title }}</p>@endif
            @if ($person->tradition)<p class="person__meta">{{ __('site.people_tradition') }}: {{ $person->tradition }}</p>@endif
            @if ($person->link && \App\Support\Social::safeUrl($person->link))
                <p class="person__meta"><a href="{{ \App\Support\Social::safeUrl($person->link) }}" target="_blank" rel="noopener noreferrer me">{{ __('site.people_link') }}</a></p>
            @endif
        </div>
    </header>

    @if (filled($person->bio))
        <div class="topic__hook">{!! nl2br(e((string) $person->bio)) !!}</div>
    @endif
    @if (filled($person->qualification_note))
        <p class="person__qual"><strong>{{ __('site.people_qualifications') }}:</strong> {{ $person->qualification_note }}</p>
    @endif
    @if (filled($person->page_body))
        <div class="person__body article__body">{!! $person->safeBody() !!}</div>
    @endif

    @if ($stories->isNotEmpty())
        <section class="block">
            <h2 class="block__title">{{ __('site.stories_title') }}</h2>
            @include('partials.story-grid', ['stories' => $stories])
        </section>
    @endif

    @if ($articles->isNotEmpty())
        <section class="block">
            <h2 class="block__title">{{ __('site.people_articles') }}</h2>
            @include('partials.article-mosaic', ['articles' => $articles])
        </section>
    @elseif ($stories->isEmpty() && blank($person->page_body) && blank($person->bio))
        <p class="empty">{{ __('site.people_nothing_yet') }}</p>
    @endif
</div>
@endsection
