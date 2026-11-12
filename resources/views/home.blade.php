@extends('layouts.site')

@php $homeName = \App\Models\Setting::get('site_name', config('app.name')); @endphp

@section('title', $homeName)
@section('description', \App\Models\Setting::get('tagline', __('site.tagline')))
@section('canonical', route('home'))

@section('content')
<div class="wrap wrap--wide">
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

    <section class="block">
        <h2 class="block__title">{{ __('site.latest') }}</h2>
        @if ($articles->isEmpty())
            <p class="empty">{{ __('site.no_articles') }}</p>
        @else
            @include('partials.article-mosaic', ['articles' => $articles])
        @endif
    </section>
</div>
@endsection
