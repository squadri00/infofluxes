@extends('layouts.site')

@php
    $site = \App\Models\Setting::get('site_name', config('app.name'));
@endphp

@section('title', __('site.clips_title').' | '.$site)
@section('description', __('site.clips_intro'))
@section('canonical', route('clips'))
@section('recent_title', __('site.clips_title'))
@section('recent_url', route('clips'))

@section('content')
<div class="wrap wrap--wide clips-page">
    <nav class="path" aria-label="Path">
        <ol>
            <li><a href="{{ route('home') }}">{{ __('site.home') }}</a></li>
            <li aria-current="page">{{ __('site.clips_title') }}</li>
        </ol>
    </nav>

    <h1 class="topic__title">{{ __('site.clips_title') }}</h1>

    @if ($clips === [])
        <p class="empty">{{ __('site.clips_empty') }}</p>
    @else
        <div class="clips" data-clips data-more="{{ __('site.clips_more') }}">
            <div class="clips__stage">
                <video class="clips__video" muted playsinline autoplay preload="metadata" aria-label="{{ $clips[0]['title'] }}"></video>
                <div class="clips__overlay" data-clips-overlay>
                    <p class="clips__title" data-clips-title></p>
                    <p class="clips__blurb" data-clips-blurb></p>
                    <a class="btn btn--primary clips__link" data-clips-link href="#" hidden></a>
                </div>
                <button type="button" class="clips__arrow clips__arrow--prev" data-clips-prev aria-label="{{ __('site.clips_prev') }}">&#8249;</button>
                <button type="button" class="clips__arrow clips__arrow--next" data-clips-next aria-label="{{ __('site.clips_next') }}">&#8250;</button>
                <button type="button" class="clips__sound" data-clips-sound data-on="{{ __('site.clips_sound_on') }}" data-off="{{ __('site.clips_sound_off') }}">{{ __('site.clips_sound_on') }}</button>
            </div>

            <div class="clips__bar">
                <span class="clips__count" data-clips-count></span>
                @if (count($clips) > 6)
                    <span class="clips__pager">
                        <button type="button" data-clips-page-prev aria-label="{{ __('site.clips_earlier') }}">&#8249;</button>
                        <button type="button" data-clips-page-next aria-label="{{ __('site.clips_more') }}">&#8250;</button>
                    </span>
                @endif
            </div>

            <ul class="clips__thumbs">
                @foreach ($clips as $i => $c)
                    <li class="clips__thumb-item" data-clips-item="{{ $i }}" @if ($i >= 6) hidden @endif>
                        <button type="button" class="clips__thumb" data-clips-thumb="{{ $i }}" aria-label="{{ $c['title'] }}">
                            <video muted playsinline preload="none" data-src="{{ $c['src'] }}" tabindex="-1"></video>
                            <span class="clips__thumb-title">{{ $c['title'] }}</span>
                        </button>
                    </li>
                @endforeach
            </ul>
        </div>
        <script type="application/json" id="clips-data">@json($clips)</script>
    @endif
</div>
@if ($clips !== [])
    @push('scripts')
        <script src="{{ asset('js/clips.js') }}?v={{ @filemtime(public_path('js/clips.js')) ?: 1 }}" defer></script>
    @endpush
@endif
@endsection
