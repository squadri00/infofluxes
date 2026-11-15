{{-- Grid / list / index switch for lists of articles and categories. Grid and list are kept on the reader's device (public/js/view-toggle.js).
     On a category page, Index is a link to ?view=index (the whole branch as a quick list). Needs nothing; optional: $indexUrl, $plainUrl, $mode. --}}
@if (\App\Support\SiteControls::on('grid_list'))
@php
    $indexUrl = $indexUrl ?? null;
    $plainUrl = $plainUrl ?? null;
    $inIndex = ($mode ?? null) === 'index';
@endphp
<div class="view-toggle" role="group" aria-label="{{ __('site.view') }}" data-view-toggle @if ($inIndex) data-active="index" @endif>
    @if ($inIndex && $plainUrl)
        <a class="view-toggle__btn" href="{{ $plainUrl }}" data-view-link="grid" aria-pressed="false" title="{{ __('site.grid') }}">
            <svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true" focusable="false"><path fill="currentColor" d="M3 3h8v8H3V3zm10 0h8v8h-8V3zM3 13h8v8H3v-8zm10 0h8v8h-8v-8z"/></svg>
            <span>{{ __('site.grid') }}</span>
        </a>
        <a class="view-toggle__btn" href="{{ $plainUrl }}" data-view-link="list" aria-pressed="false" title="{{ __('site.list') }}">
            <svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true" focusable="false"><path fill="currentColor" d="M3 5h18v3H3V5zm0 5.5h18v3H3v-3zM3 16h18v3H3v-3z"/></svg>
            <span>{{ __('site.list') }}</span>
        </a>
    @else
        <button type="button" class="view-toggle__btn" data-view-btn="grid" aria-pressed="true" title="{{ __('site.grid') }}">
            <svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true" focusable="false"><path fill="currentColor" d="M3 3h8v8H3V3zm10 0h8v8h-8V3zM3 13h8v8H3v-8zm10 0h8v8h-8v-8z"/></svg>
            <span>{{ __('site.grid') }}</span>
        </button>
        <button type="button" class="view-toggle__btn" data-view-btn="list" aria-pressed="false" title="{{ __('site.list') }}">
            <svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true" focusable="false"><path fill="currentColor" d="M3 5h18v3H3V5zm0 5.5h18v3H3v-3zM3 16h18v3H3v-3z"/></svg>
            <span>{{ __('site.list') }}</span>
        </button>
    @endif
    @if ($indexUrl)
        <a class="view-toggle__btn" href="{{ $indexUrl }}" data-view-link="index" aria-pressed="{{ $inIndex ? 'true' : 'false' }}" title="{{ __('site.index_view') }}">
            <svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true" focusable="false"><path fill="currentColor" d="M4 4h3v3H4V4zm5 .5h11v2H9v-2zM4 10.500h3v3H4v-3zm5 .5h11v2H9v-2zM4 17h3v3H4v-3zm5 .5h11v2H9v-2z"/></svg>
            <span>{{ __('site.index_view') }}</span>
        </a>
    @endif
</div>
@endif
