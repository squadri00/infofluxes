{{-- Home page: "Continue where you left off". Hidden until the visitor's own browser has a list (see public/js/recent.js). --}}
<section class="block recent-strip" data-recent-strip data-recent-max="4" aria-label="{{ __('site.recent_continue') }}" hidden>
    <div class="recent-strip__head">
        <h2 class="recent-strip__title">{{ __('site.recent_continue') }}</h2>
        <button type="button" class="recent-menu__clear" data-recent-clear>{{ __('site.recent_clear') }}</button>
    </div>
    <ul class="recent-strip__list" data-recent-fill></ul>
</section>
