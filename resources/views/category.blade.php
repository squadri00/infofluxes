@extends('layouts.site')

@php
    $name = $category->name;
    $descriptionText = trim(preg_replace('/\s+/', ' ', strip_tags((string) $category->description)));
@endphp

@section('title', $category->page_title ?: $name.' | '.\App\Models\Setting::get('site_name', config('app.name')))
@if ($category->meta_description || $descriptionText)
    @section('description', $category->meta_description ?: \Illuminate\Support\Str::limit($descriptionText, 160))
@endif
@if ($category->image)
    @section('og_image', asset('storage/'.$category->image))
@endif
@section('canonical', route('category.show', $category->slug))
@if ($preview)
    @section('robots')
        <meta name="robots" content="noindex">
    @endsection
@endif

@section('content')
@if ($preview)
    <div class="preview-banner"><div class="wrap">{{ __('site.draft_notice') }}</div></div>
@endif

<div class="wrap wrap--wide category">
    <nav class="path" aria-label="Path">
        <ol>
            <li><a href="{{ route('home') }}">{{ __('site.home') }}</a></li>
            @foreach ($trail->slice(0, -1) as $step)
                <li><a href="{{ route('category.show', $step->slug) }}">{{ $step->name }}</a></li>
            @endforeach
            <li aria-current="page">{{ $name }}</li>
        </ol>
    </nav>

    <h1 class="topic__title">{{ $name }} @include('partials.id-tag', ['code' => $category->code])</h1>

    @if ($category->image)
        <figure class="topic__master topic__master--banner"><img src="{{ asset('storage/'.$category->image) }}" alt="{{ $category->image_alt }}"></figure>
    @endif

    @if ($descriptionText !== '')
        <div class="topic__hook">{!! nl2br(e((string) $category->description)) !!}</div>
    @endif
    @if (filled($category->header_text))
        <div class="topic__context">{!! nl2br(e((string) $category->header_text)) !!}</div>
    @endif

    @if ($children->isNotEmpty())
        <nav class="category-menu" aria-label="{{ __('site.sub_categories') }}">
            @foreach ($children as $child)
                <a class="btn btn--small category-menu__btn" href="{{ route('category.show', $child->slug) }}">{{ $child->name }}</a>
            @endforeach
        </nav>
    @endif

    <div class="view-bar">@include('partials.view-toggle')</div>

    @if ($children->isNotEmpty())
        <section class="block">
            <h2 class="block__title">{{ __('site.sub_categories') }}</h2>
            <ul class="tiles">
                @foreach ($children as $child)
                    @include('partials.category-tile', ['category' => $child])
                @endforeach
            </ul>
        </section>
    @endif

    <section class="block">
        <h2 class="block__title">{{ __('site.articles') }}</h2>
        @if ($articles->isEmpty())
            <p class="empty">{{ __('site.no_articles') }}</p>
        @else
            @include('partials.article-mosaic', ['articles' => $articles->getCollection()])
            @include('partials.pager', ['paginator' => $articles])
        @endif
    </section>
</div>
@endsection
