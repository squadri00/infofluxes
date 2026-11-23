@extends('layouts.site')

@php
    $site = \App\Models\Setting::get('site_name', config('app.name'));
@endphp

@section('title', __('site.categories_title').' | '.$site)
@section('description', __('site.categories_intro'))
@section('canonical', route('categories'))
@section('recent_title', __('site.categories_title'))
@section('recent_url', route('categories'))

@section('content')
<div class="wrap wrap--wide topics-page">
    <nav class="path" aria-label="Path">
        <ol>
            <li><a href="{{ route('home') }}">{{ __('site.home') }}</a></li>
            <li aria-current="page">{{ __('site.categories_title') }}</li>
        </ol>
    </nav>

    {{-- The banner: one picture behind the title and the numbers. --}}
    <header class="gallery-hero topics-hero" @if ($heroImage) style="--gallery-hero: url('{{ $heroImage }}')" @endif>
        <div class="gallery-hero__shade" aria-hidden="true"></div>
        <div class="gallery-hero__inner">
            <h1 class="topic__title gallery-hero__title">{{ __('site.categories_title') }}</h1>
            <p class="gallery-hero__text">{{ __('site.categories_intro') }}</p>
            @if ($masters !== [])
                <ul class="gallery-hero__stats">
                    <li><span class="gallery-hero__stat">{{ str_replace(':n', count($masters), __('site.categories_masters')) }}</span></li>
                    @if ($subTotal > 0)<li><span class="gallery-hero__stat">{{ str_replace(':n', $subTotal, __('site.categories_subs')) }}</span></li>@endif
                    @if ($articleTotal > 0)<li><span class="gallery-hero__stat">{{ str_replace(':n', $articleTotal, __('site.categories_articles')) }}</span></li>@endif
                </ul>
            @endif
        </div>
    </header>

    @if ($masters === [])
        <p class="empty">{{ __('site.categories_empty') }}</p>
    @else
        <div class="topics-tools">
            <button type="button" class="gchip gchip--small" data-topics-open>{{ __('site.categories_open_all') }}</button>
            <button type="button" class="gchip gchip--small" data-topics-close>{{ __('site.categories_close_all') }}</button>
        </div>
        <div class="topics-grid" data-topics>
            @foreach ($masters as $m)
                @php $c = $m['category']; @endphp
                <article class="topics-card">
                    <a class="topics-card__media" href="{{ route('category.show', $c->slug) }}" tabindex="-1" aria-hidden="true">
                        @if ($m['picture'])
                            <img src="{{ asset('storage/'.$m['picture']['path']) }}" alt="" loading="lazy" decoding="async">
                        @endif
                    </a>
                    <div class="topics-card__body">
                        <h2 class="topics-card__title"><a href="{{ route('category.show', $c->slug) }}">{{ $c->name }}</a> @include('partials.id-tag', ['code' => $c->code])</h2>
                        @if (filled($c->description))<p class="topics-card__text">{{ \Illuminate\Support\Str::limit(trim(strip_tags((string) $c->description)), 170) }}</p>@endif
                        @if ($m['articles'] > 0 || $m['subs'] === [])
                            <p class="topics-card__meta">
                                {{ $m['subs'] === [] ? trans_choice('site.categories_sub_count', 0, ['n' => 0]) : '' }}
                                @if ($m['articles'] > 0) {{ $m['subs'] === [] ? '·' : '' }} {{ str_replace(':n', $m['articles'], __('site.categories_articles')) }}@endif
                            </p>
                        @endif
                        @if ($m['subs'] !== [])
                            <details class="topics-card__subs" open data-topics-details>
                                <summary>{{ trans_choice('site.categories_sub_count', count($m['subs']), ['n' => count($m['subs'])]) }}</summary>
                                <ul>
                                    @foreach ($m['subs'] as $s)
                                        <li>
                                            <a href="{{ route('category.show', $s['category']->slug) }}">{{ $s['category']->name }}</a>
                                            @if ($s['articles'] > 0)<span class="topics-card__n">{{ $s['articles'] }}</span>@endif
                                        </li>
                                    @endforeach
                                </ul>
                            </details>
                        @endif
                        <p class="topics-card__links">
                            <a class="btn btn--small" href="{{ route('category.show', $c->slug) }}">{{ __('site.categories_open_page', ['name' => $c->name]) }} &rarr;</a>
                            @if ($m['subs'] !== [])<a class="topics-card__index" href="{{ route('category.show', ['slug' => $c->slug, 'view' => 'index']) }}">{{ __('site.categories_full_index') }}</a>@endif
                        </p>
                    </div>
                </article>
            @endforeach
        </div>
        <script src="{{ asset('js/categories.js') }}?v={{ @filemtime(public_path('js/categories.js')) ?: 1 }}" defer></script>
    @endif
</div>
@endsection
