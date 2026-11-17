{{-- "Recent": a small tab stuck to the right edge of the window. Pressing it slides out a card with the pages this visitor opened last.
     The list is kept in the visitor's own browser (see public/js/recent.js). --}}
<aside class="recent-tab" data-recent-tab aria-label="{{ __('site.recent_title') }}">
    <button type="button" class="recent-tab__btn" data-recent-tab-btn aria-expanded="false" aria-controls="recent-card">
        <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="9" fill="none" stroke="currentColor" stroke-width="2"/><path d="M12 7v5l3 2" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
        <span class="recent-tab__label">{{ __('site.recent_menu') }}</span>
    </button>
    <div class="recent-tab__card recent-menu" id="recent-card" data-recent-menu role="dialog" aria-label="{{ __('site.recent_title') }}">
        <div class="recent-tab__head">
            <p class="recent-menu__title">{{ __('site.recent_title') }}</p>
            <button type="button" class="recent-tab__close" data-recent-tab-close aria-label="{{ __('site.recent_close') }}">&times;</button>
        </div>
        <ul class="recent-menu__list" data-recent-fill></ul>
        <p class="recent__none" data-recent-empty>{{ __('site.recent_none') }}</p>
        <button type="button" class="recent-menu__clear" data-recent-clear hidden>{{ __('site.recent_clear') }}</button>
    </div>
</aside>
