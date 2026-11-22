@extends('layouts.site')

@section('title', __('site.search_title').' | '.\App\Models\Setting::get('site_name', config('app.name')))
@section('robots')
    <meta name="robots" content="noindex">
@endsection

@section('content')
<div class="wrap wrap--wide search">
    <h1 class="topic__title">{{ $q !== '' ? str_replace(':q', $q, __('site.search_results_for')) : ($within ? str_replace(':name', $within->name, __('site.search_in_category')) : __('site.search_title')) }}</h1>
    @if ($q !== '' && mb_strlen($q) <= 60)
        <p class="follow-row">@include('partials.follow-button', ['kind' => 'keyword', 'keyword' => $q])</p>
    @endif
    @if ($within && $q !== '')
        <p class="search__scope">{{ str_replace(':name', $within->name, __('site.search_only_in')) }} <a href="{{ route('search', ['q' => $q]) }}">{{ __('site.search_everywhere') }}</a></p>
    @endif

    @if ($q === '' && ! $within)
        <p class="empty">{{ __('site.search_empty') }}</p>
        <p class="search__hint">{{ __('site.search_hint') }}</p>
    @else
        {{-- How many were found, and where: every kind of result has its own part below, and none is left out. --}}
        @if (! empty($also))
            <p class="search__also">{{ __('site.search_also') }} {{ implode(', ', $also) }}</p>
        @endif
        <p class="search__count" role="status">
            <strong>{{ $total === 1 ? __('site.search_one_result') : str_replace(':n', $total, __('site.search_n_results')) }}</strong>
            @if ($total > 0)
                @php
                    $parts = array_filter([
                        'categories' => [$categories->count(), __('site.search_categories_found')],
                        'stories' => [$stories->count(), __('site.stories_title')],
                        'facts' => [$facts->count(), __('site.dyk_title')],
                        'articles' => [$articles->total(), __('site.search_articles_found')],
                        'subjects' => [$subjects->count(), __('site.search_subjects_found')],
                        'pages' => [$pages->count(), __('site.search_pages_found')],
                        'people' => [$people->count(), __('site.people_title')],
                    ], fn ($p) => $p[0] > 0);
                @endphp
                &middot;
                @foreach ($parts as $key => [$n, $label])
                    <a href="#found-{{ $key }}">{{ $label }} ({{ $n }})</a>@if (! $loop->last), @endif
                @endforeach
            @endif
        </p>

        @if ($total === 0)
            <p class="empty">{{ __('site.search_none') }}</p>
            <p class="search__hint">{{ __('site.search_hint') }}</p>
        @endif

        @if ($categories->isNotEmpty())
            <section class="block" id="found-categories">
                <h2 class="block__title">{{ __('site.search_categories_found') }} <span class="block__n">({{ $categories->count() }})</span></h2>
                <ul class="tiles">
                    @foreach ($categories as $found)
                        @include('partials.category-tile', ['category' => $found])
                    @endforeach
                </ul>
            </section>
        @endif

        @if ($stories->isNotEmpty())
            <section class="block" id="found-stories">
                <h2 class="block__title">{{ __('site.stories_title') }} <span class="block__n">({{ $stories->count() }})</span></h2>
                @include('partials.story-grid', ['stories' => $stories])
            </section>
        @endif

        @if ($facts->isNotEmpty())
            <section class="block" id="found-facts">
                <h2 class="block__title">{{ __('site.dyk_title') }} <span class="block__n">({{ $facts->count() }})</span></h2>
                @include('partials.article-mosaic', ['articles' => $facts, 'card' => 'partials.fact-card'])
            </section>
        @endif

        @if ($articles->total() > 0)
            <section class="block" id="found-articles">
                <h2 class="block__title">{{ __('site.search_articles_found') }} <span class="block__n">({{ $articles->total() }})</span></h2>
                @include('partials.article-mosaic', ['articles' => $articles->getCollection()])
                @include('partials.pager', ['paginator' => $articles])
                @if (! $showAll && $articles->hasPages())
                    <p class="search__all"><a class="btn btn--small" href="{{ request()->fullUrlWithQuery(['all' => 1, 'page' => null]) }}">{{ __('site.search_show_all') }}</a></p>
                @endif
            </section>
        @endif

        @if ($subjects->isNotEmpty())
            {{-- Subjects of the indexes that have no article yet: found by their ID (QR-1.1.001) or their words. --}}
            <section class="block" id="found-subjects">
                <h2 class="block__title">{{ __('site.search_subjects_found') }} <span class="block__n">({{ $subjects->count() }})</span></h2>
                <p class="search__note">{{ __('site.search_subjects_note') }}</p>
                <ul class="qi__list search__subjects">
                    @foreach ($subjects as $subject)
                        <li class="qi__item">
                            <span class="qi__code">{{ \App\Support\CategoryIndex::codeOf($subject) }}</span>
                            {{ $subject->title }}
                            <a class="qi__also" href="{{ route('category.show', ['slug' => $subject->category->slug, 'view' => 'index']) }}#a-{{ $subject->id }}">{{ $subject->category->name }}</a>
                        </li>
                    @endforeach
                </ul>
                @if ($cut)
                    <p class="search__note">{{ str_replace(':n', \App\Http\Controllers\SearchController::ALL_CAP, __('site.search_cut')) }}</p>
                @endif
            </section>
        @endif

        @if ($pages->isNotEmpty())
            <section class="block" id="found-pages">
                <h2 class="block__title">{{ __('site.search_pages_found') }} <span class="block__n">({{ $pages->count() }})</span></h2>
                <ul class="search__list">
                    @foreach ($pages as $found)
                        <li><a href="{{ route('article.show', $found->slug) }}">{{ $found->title }}</a> @include('partials.id-tag', ['code' => $found->code])</li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if ($people->isNotEmpty())
            <section class="block" id="found-people">
                <h2 class="block__title">{{ __('site.people_title') }} <span class="block__n">({{ $people->count() }})</span></h2>
                <ul class="search__list">
                    @foreach ($people as $found)
                        <li><a href="{{ route('people.show', $found->slug) }}">{{ $found->name }}</a> @include('partials.id-tag', ['code' => $found->code]) @if ($found->role_title)<span class="search__meta">{{ $found->role_title }}</span>@endif</li>
                    @endforeach
                </ul>
            </section>
        @endif
    @endif
</div>
@endsection
