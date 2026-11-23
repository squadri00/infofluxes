@extends('layouts.site')

@php $homeName = \App\Models\Setting::get('site_name', config('app.name')); @endphp

@section('title', $homeName)
@section('description', \App\Models\Setting::get('tagline', __('site.tagline')))
@section('canonical', route('home'))

@section('content')
<div class="wrap wrap--wide home">
    @if ($hero)
        @include('partials.hero', ['hero' => $hero])
        @if ($hero['slides']->count() > 1 || $hero['slides']->contains(fn ($s) => $s->isVideo()))
            @push('scripts')
                <script src="{{ asset('js/hero.js') }}?v={{ @filemtime(public_path('js/hero.js')) ?: 1 }}" defer></script>
            @endpush
        @endif
    @endif

    @php
        $headline = \App\Models\Setting::get('home_headline');
        $intro = \App\Models\Setting::get('home_intro');
        $message = \App\Models\Setting::get('home_message');
    @endphp
    @if (! $hero || filled($intro) || filled($message))
        <section class="home__hero">
            @if (! $hero && filled($headline))
                <h1 class="home__headline">{{ $headline }}</h1>
            @else
                <h1 class="visually-hidden">{{ $homeName }}</h1>
            @endif
            @if (filled($intro))<div class="home__intro">{!! \App\Support\Html::fromPlain($intro) !!}</div>@endif
            @if (filled($message))<div class="home__message">{!! \App\Support\Html::fromPlain($message) !!}</div>@endif
        </section>
    @endif

    @if (\App\Support\SiteControls::on('recent_menu'))
        @include('partials.recent-strip')
    @endif

    <div class="view-bar">@include('partials.view-toggle')</div>

    @if ($stories->isNotEmpty())
        <section class="block">
            <h2 class="block__title">{{ __('site.stories_title') }} <a class="block__more" href="{{ route('stories') }}">{{ __('site.stories_all') }} &rarr;</a></h2>
            {{-- The same small cards in the same layout as the Featured articles. --}}
            @include('partials.article-mosaic', ['articles' => $stories, 'card' => 'partials.story-pcard', 'force' => 'c'])
        </section>
    @endif

    @if (\App\Support\Gallery::on() && \App\Support\Gallery::pictures() !== [])
        <p class="gallery-more"><a class="btn btn--small" href="{{ route('gallery') }}">&#128444; {{ __('site.gallery_more') }} &rarr;</a></p>
    @endif

    @if (\App\Support\SiteControls::on('categories_page'))
        <p class="gallery-more"><a class="btn btn--small" href="{{ route('categories') }}">&#9776; {{ __('site.categories_all') }} &rarr;</a></p>
    @endif

    @if ($featured->isNotEmpty())
        <section class="block">
            <h2 class="block__title">{{ __('site.featured') }}</h2>
            @include('partials.article-mosaic', ['articles' => $featured])
        </section>
    @endif

    @if ($facts->isNotEmpty())
        <section class="block" data-home-facts>
            <h2 class="block__title">{{ __('site.dyk_title') }} <a class="block__more" href="{{ route('did-you-know') }}">{{ __('site.dyk_all') }} &rarr;</a></h2>
            {{-- The same cards and the same layout as the Featured articles. --}}
            @include('partials.article-mosaic', ['articles' => $facts, 'card' => 'partials.fact-card', 'force' => 'c'])
        </section>
    @endif

    @if ($articles->isNotEmpty())
        <section class="block">
            <h2 class="block__title">{{ __('site.latest') }}</h2>
            @include('partials.article-mosaic', ['articles' => $articles])
        </section>
    @endif

    @if ($featured->isEmpty() && $articles->isEmpty())
        <p class="empty">{{ __('site.no_articles') }}</p>
    @endif

    @if ($categories->isNotEmpty())
        <section class="block">
            <h2 class="block__title">{{ __('site.browse_topics') }}</h2>
            <ul class="tiles">
                @foreach ($categories as $category)
                    @include('partials.category-tile', ['category' => $category])
                @endforeach
            </ul>
        </section>
    @endif
</div>
@endsection
