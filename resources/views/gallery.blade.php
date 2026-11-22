@extends('layouts.site')

@php
    $site = \App\Models\Setting::get('site_name', config('app.name'));
    $keep = fn (array $change = []) => array_filter(array_merge(['kind' => $kind, 'section' => $sectionSlug, 'per' => $per !== \App\Support\Gallery::DEFAULT_PER ? $per : null], $change), fn ($v) => $v !== null);
    // A link to this page with one filter changed (and the first page).
    $link = fn (array $change = []) => route('gallery', $keep($change));
    // A tab: the same, and a new random order (shuffle=1: the page sends a new order and the address is cleaned).
    $tab = fn (?string $key) => route('gallery', $keep(['kind' => $key]) + ['shuffle' => 1]);
@endphp

@section('title', __('site.gallery_title').' | '.$site)
@section('description', __('site.gallery_intro'))
@section('canonical', route('gallery'))
@section('recent_title', __('site.gallery_title'))
@section('recent_url', route('gallery'))

@section('content')
<div class="wrap wrap--wide gallery">
    <nav class="path" aria-label="Path">
        <ol>
            <li><a href="{{ route('home') }}">{{ __('site.home') }}</a></li>
            <li aria-current="page">{{ __('site.gallery_title') }}</li>
        </ol>
    </nav>

    {{-- The banner: a collage of pictures behind the title, the numbers, and a button for a new mix. --}}
    <header class="gallery-hero">
        @if ($pictures !== [])
            <div class="gallery-hero__collage" aria-hidden="true">
                @foreach (array_slice($pictures, 0, 6) as $p)
                    <img src="{{ \App\Support\Thumbnail::url($p['src']) }}" alt="" decoding="async">
                @endforeach
            </div>
        @endif
        <div class="gallery-hero__shade" aria-hidden="true"></div>
        <div class="gallery-hero__inner">
            <h1 class="topic__title gallery-hero__title">{{ __('site.gallery_title') }}</h1>
            <p class="gallery-hero__text">{{ __('site.gallery_intro') }}</p>
            @if ($all > 0)
                <ul class="gallery-hero__stats">
                    <li><span class="gallery-hero__stat">{{ str_replace(':n', $all, __('site.gallery_count')) }}</span></li>
                    @if (count($counts['sections']) > 0)
                        <li><span class="gallery-hero__stat">{{ str_replace(':n', count($counts['sections']), __('site.gallery_hero_sections')) }}</span></li>
                    @endif
                </ul>
                <div class="gallery-hero__actions">
                    <a class="btn btn--primary gallery-hero__shuffle" href="{{ route('gallery', $keep() + ['shuffle' => 1]) }}" rel="nofollow">&#8635; {{ __('site.gallery_new_mix') }}</a>
                </div>
            @endif
        </div>
    </header>

    @if ($all > 0)
    {{-- The same tabs as the stories: a pill slides to the pressed tab and the pictures glide into new places. Every press gives a new random order. --}}
    <section class="block" data-topic-tabs data-param="kind" data-strip="shuffle" data-nocache>
        <nav class="topic-tabs" aria-label="{{ __('site.gallery_kinds') }}" data-topic-nav>
            <a class="topic-tabs__tab{{ $kind === null ? ' is-current' : '' }}" href="{{ $tab(null) }}" rel="nofollow" @if ($kind === null) aria-current="page" @endif>{{ __('site.gallery_all') }} <span class="topic-tabs__count">{{ $all }}</span></a>
            @foreach ($counts['kinds'] as $key => $n)
                <a class="topic-tabs__tab{{ $kind === $key ? ' is-current' : '' }}" href="{{ $tab($key) }}" rel="nofollow" @if ($kind === $key) aria-current="page" @endif>{{ __('site.gallery_kind_'.$key) }} <span class="topic-tabs__count">{{ $n }}</span></a>
            @endforeach
        </nav>

        <div class="topic-panel" data-topic-panel>
            <div class="gallery-bar" data-gallery-bar>
                @if (count($counts['sections']) > 1)
                    <nav class="gallery-chips gallery-chips--sections" aria-label="{{ __('site.gallery_sections') }}">
                        <a class="gchip{{ $sectionSlug === null ? ' is-current' : '' }}" data-topic-link href="{{ $link(['section' => null, 'page' => null]) }}" @if ($sectionSlug === null) aria-current="true" @endif>{{ __('site.gallery_every_section') }}</a>
                        @foreach ($counts['sections'] as $s)
                            <a class="gchip{{ $sectionSlug === $s['slug'] ? ' is-current' : '' }}" data-topic-link href="{{ $link(['section' => $s['slug'], 'page' => null]) }}" @if ($sectionSlug === $s['slug']) aria-current="true" @endif>{{ $s['name'] }} <span class="gchip__n">{{ $s['count'] }}</span></a>
                        @endforeach
                    </nav>
                @endif
                <div class="gallery-tools">
                    <span class="gallery-tools__label">{{ __('site.gallery_per') }}:</span>
                    @foreach (\App\Support\Gallery::PER_PAGE as $n)
                        <a class="gchip gchip--small{{ $per === $n ? ' is-current' : '' }}" data-topic-link href="{{ $link(['per' => $n === \App\Support\Gallery::DEFAULT_PER ? null : $n, 'page' => null]) }}" @if ($per === $n) aria-current="true" @endif>{{ $n }}</a>
                    @endforeach
                    <a class="gchip gchip--small gallery-tools__shuffle" data-topic-link href="{{ route('gallery', $keep() + ['shuffle' => 1]) }}" rel="nofollow">&#8635; {{ __('site.gallery_shuffle') }}</a>
                    <span class="gallery-tools__count">{{ str_replace(':n', $total, __('site.gallery_count')) }}</span>
                </div>
            </div>

            @if ($pictures === [])
                <p class="empty">{{ __('site.gallery_empty') }}</p>
            @else
                @include('partials.pager', ['paginator' => $paginator])
                @php
                    $viewerLabels = ['dialog' => __('site.gallery_viewer'), 'close' => __('site.gallery_close'), 'prev' => __('site.gallery_prev'), 'next' => __('site.gallery_next'), 'go' => __('site.gallery_go')];
                @endphp
                <ul class="gallery-grid" data-gallery-grid data-labels="{{ json_encode($viewerLabels, JSON_UNESCAPED_UNICODE) }}">
                    @foreach ($pictures as $p)
                        <li class="gtile" data-key="I-{{ $p['id'] }}">
                            <a class="gtile__link" href="{{ $p['url'] }}" data-id="I-{{ $p['id'] }}" data-full="{{ asset('storage/'.$p['src']) }}" data-title="{{ $p['title'] }}" data-parent="{{ $p['parent'] }}" data-kind="{{ __('site.gallery_label_'.$p['kind']) }}" data-alt="{{ $p['alt'] }}">
                                <span class="gtile__id" aria-hidden="true" title="{{ __('site.gallery_id_title') }}">I-{{ $p['id'] }}</span>
                                <img src="{{ \App\Support\Thumbnail::url($p['src']) }}" alt="{{ $p['alt'] }}" width="800" height="533" loading="lazy" decoding="async">
                                <span class="gtile__cap" aria-hidden="true">
                                    <span class="gtile__kind">{{ __('site.gallery_label_'.$p['kind']) }}</span>
                                    <span class="gtile__title">{{ $p['title'] }}</span>
                                    @if ($p['parent'] !== $p['title'])<span class="gtile__parent">{{ $p['parent'] }}</span>@endif
                                </span>
                            </a>
                        </li>
                    @endforeach
                </ul>
                @include('partials.pager', ['paginator' => $paginator])
            @endif
        </div>
    </section>
    <script src="{{ asset('js/topic-tabs.js') }}?v={{ @filemtime(public_path('js/topic-tabs.js')) ?: 1 }}" defer></script>
    <script src="{{ asset('js/gallery.js') }}?v={{ @filemtime(public_path('js/gallery.js')) ?: 1 }}" defer></script>
    @else
        <p class="empty">{{ __('site.gallery_empty') }}</p>
    @endif
</div>
@endsection
