{{-- "Recent" in the header: a drop-down with the last pages this visitor opened. The list is kept in the visitor's own browser (see public/js/recent.js).
     $mode = 'header' (drop-down in the top bar) or 'drawer' (a group in the phone menu). --}}
@php $mode = $mode ?? 'header'; @endphp
<details class="{{ $mode === 'drawer' ? 'm-drawer__group' : 'menu__drop' }} recent-menu" data-recent-menu>
    <summary aria-label="{{ __('site.recent_title') }}">
        <svg viewBox="0 0 24 24" width="16" height="16" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="9" fill="none" stroke="currentColor" stroke-width="2"/><path d="M12 7v5l3 2" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
        <span class="recent-menu__label">{{ __('site.recent_menu') }}</span>
    </summary>
    <div class="{{ $mode === 'drawer' ? 'recent-menu__panel recent-menu__panel--drawer' : 'menu__sub recent-menu__panel' }}">
        <p class="recent-menu__title">{{ __('site.recent_title') }}</p>
        <ul class="recent-menu__list" data-recent-fill></ul>
        <p class="recent__none" data-recent-empty>{{ __('site.recent_none') }}</p>
        <button type="button" class="recent-menu__clear" data-recent-clear hidden>{{ __('site.recent_clear') }}</button>
    </div>
</details>
