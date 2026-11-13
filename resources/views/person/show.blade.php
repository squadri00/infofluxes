@extends('layouts.site')

@section('title', $person->name.' | '.\App\Models\Setting::get('site_name', config('app.name')))
@section('description', \Illuminate\Support\Str::limit((string) $person->bio, 160))
@section('canonical', route('people.show', $person->slug))
@if ($preview)
    @section('robots')
        <meta name="robots" content="noindex">
    @endsection
@endif

@section('content')
@if ($preview)
    <div class="preview-banner"><div class="wrap">{{ __('site.people_preview') }}</div></div>
@endif

<div class="wrap wrap--wide person-page">
    <nav class="path" aria-label="Path">
        <ol>
            <li><a href="{{ route('home') }}">{{ __('site.home') }}</a></li>
            <li><a href="{{ route('people.index') }}">{{ __('site.people_title') }}</a></li>
            <li aria-current="page">{{ $person->name }}</li>
        </ol>
    </nav>

    <header class="person-head">
        @if ($person->photo)
            <img class="person-head__photo" src="{{ asset('storage/'.$person->photo) }}" alt="{{ $person->photo_alt ?: $person->name }}">
        @endif
        <div>
            <h1 class="topic__title">{{ $person->name }}</h1>
            @if ($person->role_title)<p class="person-head__role">{{ $person->role_title }}</p>@endif
            @if ($person->bio)<p class="person-head__bio">{{ $person->bio }}</p>@endif
            @if ($person->qualification_note)<p class="person-head__note">{{ $person->qualification_note }}</p>@endif
            @if ($person->link && \App\Support\Social::safeUrl($person->link))
                <p><a href="{{ \App\Support\Social::safeUrl($person->link) }}" target="_blank" rel="noopener noreferrer me">{{ __('site.people_link') }}</a></p>
            @endif
        </div>
    </header>

    @if (filled($person->page_body))
        <div class="person-body article__body">{!! $person->safeBody() !!}</div>
    @endif

    <section class="block">
        <h2 class="block__title">{{ __('site.people_articles') }}</h2>
        @if ($articles->isEmpty())
            <p class="empty">{{ __('site.people_no_articles') }}</p>
        @else
            @include('partials.article-mosaic', ['articles' => $articles])
        @endif
    </section>
</div>
@endsection
