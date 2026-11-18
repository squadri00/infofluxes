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
@section('recent_title', $name)
@section('recent_url', route('category.show', $category->slug))
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

    @if ($children->isNotEmpty() && $mode !== 'index' && ! $topics)
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

    {{-- The list of this category: its sub-categories, its Visual Stories, its Did You Know? facts and its articles, in the same cards and the same Grid / List / Index views as every other category.
         The switch "Do not list its articles" leaves the articles out. A category with facts but no articles shows only the facts (an empty list would only say "nothing here"). --}}
    @php
        $hideArticles = (bool) ($category->custom_data['hide_articles'] ?? false);
        $stories = $stories ?? collect();
        $otherItems = $facts->isNotEmpty() || $stories->isNotEmpty();
        $factsOnly = $articles->total() === 0 && $otherItems;
        $showBar = ! $hideArticles || $otherItems;
    @endphp

    @if ($showBar)
    <div class="view-bar">@include('partials.view-toggle', ['indexUrl' => route('category.show', ['slug' => $category->slug, 'view' => 'index']), 'plainUrl' => route('category.show', $category->slug), 'mode' => $mode])</div>
    @endif

    @if ($mode === 'index' && $showBar)
        @php
            // The index counts the facts too; with the articles switched off it lists only the facts.
            $indexShown = $hideArticles ? ['root' => ['children' => [], 'articles' => []], 'subjects' => 0] : $index;
            $indexShown['subjects'] += $facts->count() + $stories->count();
        @endphp
        @include('partials.category-index', ['category' => $category, 'index' => $indexShown, 'facts' => $facts, 'stories' => $stories])
    @else
    @if ($children->isNotEmpty() && ! $topics)
        <section class="block">
            <h2 class="block__title">{{ __('site.sub_categories') }}</h2>
            <ul class="tiles">
                @foreach ($children as $child)
                    @include('partials.category-tile', ['category' => $child])
                @endforeach
            </ul>
        </section>
    @endif

    @if ($topics)
    <section class="block" data-topic-tabs>
        <nav class="topic-tabs" aria-label="{{ __('site.topics_label') }}" data-topic-nav>
            @foreach ($topics['tabs'] as $tab)
                <a class="topic-tabs__tab{{ $tab['current'] ? ' is-current' : '' }}" href="{{ $tab['url'] }}" @if ($tab['current']) aria-current="page" @endif>{{ $tab['name'] }} <span class="topic-tabs__count">{{ $tab['count'] }}</span></a>
            @endforeach
        </nav>
        <div class="topic-panel" data-topic-panel>
            @if ($topics['current'] && filled(trim(strip_tags((string) $topics['current']->description))))
                <p class="topic-tabs__about">{{ trim(strip_tags((string) $topics['current']->description)) }}</p>
            @endif
            @if ($stories->isNotEmpty())
                @include('partials.story-grid', ['stories' => $stories])
                @include('partials.pager', ['paginator' => $topics['stories']])
            @else
                <p class="empty">{{ __('site.no_articles') }}</p>
            @endif
        </div>
    </section>
    <script src="{{ asset('js/topic-tabs.js') }}?v={{ @filemtime(public_path('js/topic-tabs.js')) ?: 1 }}" defer></script>
    @elseif ($stories->isNotEmpty())
    <section class="block">
        <h2 class="block__title">{{ __('site.stories_title') }}</h2>
        @include('partials.story-grid', ['stories' => $stories])
    </section>
    @endif

    @if ($facts->isNotEmpty())
    <section class="block">
        <h2 class="block__title">{{ __('site.dyk_title') }}</h2>
        @include('partials.article-mosaic', ['articles' => $facts, 'card' => 'partials.fact-card'])
    </section>
    @endif

    @if (! $hideArticles && ! $factsOnly)
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
