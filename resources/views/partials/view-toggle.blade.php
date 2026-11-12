{{-- Grid / list switch for lists of articles and categories. The choice is kept on the reader's device (public/js/view-toggle.js). --}}
<div class="view-toggle" role="group" aria-label="{{ __('site.view') }}" data-view-toggle>
    <button type="button" class="view-toggle__btn" data-view-btn="grid" aria-pressed="true" title="{{ __('site.grid') }}">
        <svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true" focusable="false"><path fill="currentColor" d="M3 3h8v8H3V3zm10 0h8v8h-8V3zM3 13h8v8H3v-8zm10 0h8v8h-8v-8z"/></svg>
        <span>{{ __('site.grid') }}</span>
    </button>
    <button type="button" class="view-toggle__btn" data-view-btn="list" aria-pressed="false" title="{{ __('site.list') }}">
        <svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true" focusable="false"><path fill="currentColor" d="M3 5h18v3H3V5zm0 5.5h18v3H3v-3zM3 16h18v3H3v-3z"/></svg>
        <span>{{ __('site.list') }}</span>
    </button>
</div>
