@extends('layouts.site')

@php
    $site = \App\Models\Setting::get('site_name', config('app.name'));
    $hasFilter = $list['filtered'] || $list['sort'] !== 'new';
@endphp

@section('title', __('site.articles_title').' | '.$site)
@section('description', __('site.articles_intro'))
@section('canonical', route('articles'))
@section('recent_title', __('site.articles_title'))
@section('recent_url', route('articles'))

@section('content')
<div class="wrap wrap--wide articles-page">
    <nav class="path" aria-label="Path">
        <ol>
            <li><a href="{{ route('home') }}">{{ __('site.home') }}</a></li>
            <li aria-current="page">{{ __('site.articles_title') }}</li>
        </ol>
    </nav>

    <h1 class="topic__title">{{ __('site.articles_title') }}</h1>
    <p class="topic__hook">{{ __('site.articles_intro') }}</p>

    <section class="block all-list">
        <form class="all-list__bar articles-bar" method="get" action="{{ route('articles') }}">
            <input class="all-list__search" type="search" name="q" value="{{ $list['q'] }}" maxlength="80" placeholder="{{ __('site.articles_search') }}" aria-label="{{ __('site.articles_search') }}">
            <select name="category" aria-label="{{ __('site.articles_category') }}" onchange="this.form.submit()">
                <option value="">{{ __('site.articles_every_category') }}</option>
                @foreach ($list['categories'] as $c)
                    <option value="{{ $c['id'] }}" @selected($list['category'] === $c['id'])>{{ $c['label'] }}</option>
                @endforeach
            </select>
            @if (count($list['contributors']) > 0)
                <select name="contributor" aria-label="{{ __('site.articles_contributor') }}" onchange="this.form.submit()">
                    <option value="">{{ __('site.articles_every_contributor') }}</option>
                    @foreach ($list['contributors'] as $p)
                        <option value="{{ $p['id'] }}" @selected($list['contributor'] === $p['id'])>{{ $p['name'] }}</option>
                    @endforeach
                </select>
            @endif
            <select name="sort" aria-label="{{ __('site.all_sort_new') }}" onchange="this.form.submit()">
                <option value="new" @selected($list['sort'] === 'new')>{{ __('site.all_sort_new') }}</option>
                <option value="old" @selected($list['sort'] === 'old')>{{ __('site.all_sort_old') }}</option>
                <option value="az" @selected($list['sort'] === 'az')>{{ __('site.all_sort_az') }}</option>
            </select>
            <button class="btn btn--small" type="submit">{{ __('site.all_go') }}</button>
            @if ($hasFilter)
                <a class="articles-bar__clear" href="{{ route('articles') }}">{{ __('site.articles_clear') }}</a>
            @endif
        </form>

        <p class="articles-count">{{ str_replace(':n', number_format($list['items']->total()), __('site.articles_found')) }}@if ($list['filtered']) <span class="articles-count__of">{{ str_replace(':n', number_format($list['total']), __('site.articles_of_all')) }}</span>@endif</p>

        @if ($list['items']->isEmpty())
            <p class="empty">{{ __('site.articles_none') }}</p>
        @else
            <ul class="mosaic all-list__grid">
                @foreach ($list['items'] as $article)
                    @include('partials.article-card', ['article' => $article, 'shape' => 'P'])
                @endforeach
            </ul>
            @include('partials.pager', ['paginator' => $list['items']])
        @endif
    </section>
</div>
@endsection
