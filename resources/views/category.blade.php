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

    {{-- The top of the page: the words on the left, the picture on the right, two equal columns (one column when there is no picture). --}}
    <header class="category-hero {{ $category->image ? 'category-hero--split' : '' }}">
    <div class="category-hero__text">
    <h1 class="topic__title">{{ $name }} @include('partials.id-tag', ['code' => $category->code])</h1>

    @if ($descriptionText !== '')
        <div class="topic__hook">{!! nl2br(e((string) $category->description)) !!}</div>
    @endif
    @if (filled($category->header_text))
        <div class="topic__context">{!! nl2br(e((string) $category->header_text)) !!}</div>
    @endif

    @if ($children->isNotEmpty() && $mode !== 'index')
        <nav class="category-menu" aria-label="{{ __('site.sub_categories') }}">
            @foreach ($children as $child)
                <a class="btn btn--small category-menu__btn" href="{{ route('category.show', $child->slug) }}">{{ $child->name }}</a>
            @endforeach
        </nav>
    @endif
    </div>

    @if ($category->image)
        <figure class="category-hero__picture"><img src="{{ asset('storage/'.$category->image) }}" alt="{{ $category->image_alt }}"></figure>
    @endif
    </header>

    @if ($facts->isNotEmpty())
        <section class="block">
            <h2 class="block__title">{{ __('site.dyk_title') }}</h2>
            @include('partials.did-you-know-list', ['entries' => $facts])
        </section>
    @endif

    @php $hideArticles = (bool) ($category->custom_data['hide_articles'] ?? false); @endphp

    @if (! $hideArticles)
    <div class="view-bar">@include('partials.view-toggle', ['indexUrl' => route('category.show', ['slug' => $category->slug, 'view' => 'index']), 'plainUrl' => route('category.show', $category->slug), 'mode' => $mode])</div>
    @endif

    @if ($mode === 'index' && ! $hideArticles)
        @include('partials.category-index', ['category' => $category, 'index' => $index])
    @else
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

    @if (! $hideArticles)
    <section class="block">
        <h2 class="block__title">{{ __('site.articles') }}</h2>
        @if ($articles->isEmpty())
            <p class="empty">{{ __('site.no_articles') }}</p>
        @else
            @include('partials.article-mosaic', ['articles' => $articles->getCollection()])
            @include('partials.pager', ['paginator' => $articles])
        @endif
    </section>
    @endif
    @endif
</div>
@if ($mode === 'index')
    <script src="{{ asset('js/subject-index.js') }}?v={{ @filemtime(public_path('js/subject-index.js')) ?: 1 }}" defer></script>
@endif
@endsection
