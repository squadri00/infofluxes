@extends('layouts.site')

@section('title', __('site.search_title').' | '.\App\Models\Setting::get('site_name', config('app.name')))
@section('robots')
    <meta name="robots" content="noindex">
@endsection

@section('content')
<div class="wrap wrap--wide">
    <h1 class="topic__title">{{ $q !== '' ? str_replace(':q', $q, __('site.search_results_for')) : ($within ? str_replace(':name', $within->name, __('site.search_in_category')) : __('site.search_title')) }}</h1>
    @if ($within && $q !== '')
        <p class="search__scope">{{ str_replace(':name', $within->name, __('site.search_only_in')) }} <a href="{{ route('search', ['q' => $q]) }}">{{ __('site.search_everywhere') }}</a></p>
    @endif

    @if ($q === '' && ! $within)
        <p class="empty">{{ __('site.search_empty') }}</p>
    @else
        @if ($categories->isNotEmpty())
            <section class="block">
                <h2 class="block__title">{{ __('site.search_categories_found') }}</h2>
                <ul class="tiles">
                    @foreach ($categories as $category)
                        @include('partials.category-tile', ['category' => $category])
                    @endforeach
                </ul>
            </section>
        @endif

        <section class="block">
            <h2 class="block__title">{{ __('site.search_articles_found') }}</h2>
            @if ($articles->isEmpty())
                <p class="empty">{{ __('site.search_none') }}</p>
            @else
                @include('partials.article-mosaic', ['articles' => $articles->getCollection()])
                @include('partials.pager', ['paginator' => $articles])
            @endif
        </section>
    @endif
</div>
@endsection
