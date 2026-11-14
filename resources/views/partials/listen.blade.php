{{-- The Listen bar of a topic: it reads the story aloud with the voice of the visitor's device. Switched on and set in Settings > Listen.
     The buttons are made visible by listen.js (they would do nothing without it). --}}
@php
    $listenTracks = \App\Support\Listen::tracks($article ?? null, $storyItems ?? []);
@endphp
@if (\App\Support\Listen::visible($listenTracks))
    <div class="listen" data-listen='{!! \App\Support\Listen::json($listenTracks) !!}' hidden>
        <div class="listen__buttons">
            <button type="button" class="btn btn--small btn--primary listen__play" data-listen-play>
                <svg viewBox="0 0 24 24" width="16" height="16" fill="currentColor" aria-hidden="true"><path d="M3 10v4h4l5 4V6L7 10H3zm13.5 2a4.5 4.5 0 0 0-2.5-4.03v8.05A4.5 4.5 0 0 0 16.5 12zM14 3.23v2.06a7 7 0 0 1 0 13.42v2.06a9 9 0 0 0 0-17.54z"/></svg>
                <span data-listen-play-text>{{ ($article ?? null) instanceof \App\Models\Story ? __('site.listen_button') : __('site.listen_button_article') }}</span>
            </button>
            <button type="button" class="btn btn--small listen__pause" data-listen-pause data-pause="{{ __('site.listen_pause') }}" data-resume="{{ __('site.listen_resume') }}" hidden>{{ __('site.listen_pause') }}</button>
            <button type="button" class="btn btn--small listen__prev" data-listen-prev aria-label="{{ __('site.listen_back') }}" title="{{ __('site.listen_back') }}" hidden>&#9198; {{ __('site.listen_back') }}</button>
            <button type="button" class="btn btn--small listen__next" data-listen-next aria-label="{{ __('site.listen_forward') }}" title="{{ __('site.listen_forward') }}" hidden>{{ __('site.listen_forward') }} &#9197;</button>
            <button type="button" class="btn btn--small listen__stop" data-listen-stop hidden>{{ __('site.listen_stop') }}</button>
            <label class="listen__speed">{{ __('site.listen_speed') }}
                <select data-listen-speed>
                    @foreach ([0.8, 0.9, 1, 1.1, 1.25, 1.5] as $r)
                        <option value="{{ $r }}">{{ $r }}x</option>
                    @endforeach
                </select>
            </label>
            <label class="listen__voice">{{ __('site.listen_voice') }}
                <select data-listen-voice></select>
            </label>
        </div>
        <p class="listen__status" data-listen-status role="status" aria-live="polite"></p>
    </div>
@endif
