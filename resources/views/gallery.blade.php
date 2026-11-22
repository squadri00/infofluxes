@extends('layouts.site')

@php
    $site = \App\Models\Setting::get('site_name', config('app.name'));
    // A link to this page with one filter changed (and the first page).
    $link = fn (array $change = []) => route('gallery', array_filter(array_merge(['kind' => $kind, 'section' => $sectionSlug, 'per' => $per !== \App\Support\Gallery::DEFAULT_PER ? $per : null], $change), fn ($v) => $v !== null));
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

    <h1 class="topic__title">{{ __('site.gallery_title') }}</h1>
    <p class="topic__hook">{{ __('site.gallery_intro') }}</p>

    @if ($all > 0)
        <div class="gallery-bar" data-gallery-bar>
            <nav class="gallery-chips" aria-label="{{ __('site.gallery_kinds') }}">
                <a class="gchip{{ $kind === null ? ' is-current' : '' }}" href="{{ $link(['kind' => null, 'page' => null]) }}" @if ($kind === null) aria-current="true" @endif>{{ __('site.gallery_all') }} <span class="gchip__n">{{ $all }}</span></a>
                @foreach ($counts['kinds'] as $key => $n)
                    <a class="gchip{{ $kind === $key ? ' is-current' : '' }}" href="{{ $link(['kind' => $key, 'page' => null]) }}" @if ($kind === $key) aria-current="true" @endif>{{ __('site.gallery_kind_'.$key) }} <span class="gchip__n">{{ $n }}</span></a>
                @endforeach
            </nav>
            @if (count($counts['sections']) > 1)
                <nav class="gallery-chips gallery-chips--sections" aria-label="{{ __('site.gallery_sections') }}">
                    <a class="gchip{{ $sectionSlug === null ? ' is-current' : '' }}" href="{{ $link(['section' => null, 'page' => null]) }}" @if ($sectionSlug === null) aria-current="true" @endif>{{ __('site.gallery_every_section') }}</a>
                    @foreach ($counts['sections'] as $s)
                        <a class="gchip{{ $sectionSlug === $s['slug'] ? ' is-current' : '' }}" href="{{ $link(['section' => $s['slug'], 'page' => null]) }}" @if ($sectionSlug === $s['slug']) aria-current="true" @endif>{{ $s['name'] }} <span class="gchip__n">{{ $s['count'] }}</span></a>
                    @endforeach
                </nav>
            @endif
            <div class="gallery-tools">
                <span class="gallery-tools__label">{{ __('site.gallery_per') }}:</span>
                @foreach (\App\Support\Gallery::PER_PAGE as $n)
                    <a class="gchip gchip--small{{ $per === $n ? ' is-current' : '' }}" href="{{ $link(['per' => $n === \App\Support\Gallery::DEFAULT_PER ? null : $n, 'page' => null]) }}" @if ($per === $n) aria-current="true" @endif>{{ $n }}</a>
                @endforeach
                <a class="gchip gchip--small gallery-tools__shuffle" href="{{ route('gallery', array_filter(['shuffle' => 1, 'kind' => $kind, 'section' => $sectionSlug, 'per' => $per !== \App\Support\Gallery::DEFAULT_PER ? $per : null])) }}" rel="nofollow">&#8635; {{ __('site.gallery_shuffle') }}</a>
                <span class="gallery-tools__count">{{ str_replace(':n', $total, __('site.gallery_count')) }}</span>
            </div>
        </div>
    @endif

    @if ($pictures === [])
        <p class="empty">{{ __('site.gallery_empty') }}</p>
    @else
        @include('partials.pager', ['paginator' => $paginator])
        <ul class="gallery-grid" data-gallery-grid>
            @foreach ($pictures as $p)
                <li class="gtile">
                    <a class="gtile__link" href="{{ $p['url'] }}">
                        <img src="{{ \App\Support\Thumbnail::url($p['src']) }}" alt="{{ $p['alt'] }}" width="480" height="320" loading="lazy" decoding="async">
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
@endsection
