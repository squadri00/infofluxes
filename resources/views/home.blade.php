@extends('layouts.site')

@php $homeName = \App\Models\Setting::get('site_name', config('app.name')); @endphp

@section('title', $homeName)
@section('description', \App\Models\Setting::get('tagline', __('site.tagline')))
@section('canonical', route('home'))

@section('content')
<div class="wrap wrap--wide">
    <div class="view-bar">@include('partials.view-toggle')</div>

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
