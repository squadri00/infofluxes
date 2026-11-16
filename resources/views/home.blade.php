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

    <div class="view-bar">@include('partials.view-toggle')</div>

    @if ($stories->isNotEmpty())
        <section class="block">
            <h2 class="block__title">{{ __('site.stories_title') }} <a class="block__more" href="{{ route('stories') }}">{{ __('site.stories_all') }} &rarr;</a></h2>
            @include('partials.story-grid', ['stories' => $stories])
        </section>
    @endif

    @if ($featured->isNotEmpty())
        <section class="block">
            <h2 class="block__title">{{ __('site.featured') }}</h2>
            @include('partials.article-mosaic', ['articles' => $featured])
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
