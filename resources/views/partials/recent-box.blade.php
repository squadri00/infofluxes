{{-- The right side of an article: the pages this visitor opened last. The list is kept in the visitor's own browser; nothing is sent to the server. --}}
<section class="recent" data-recent data-recent-max="5" data-current-url="{{ route('article.show', $article->slug) }}" data-current-title="{{ $title }}">
    <h2 class="recent__title">{{ __('site.recent_title') }}</h2>
    <ul class="recent__list" data-recent-list></ul>
    <p class="recent__none" data-recent-none>{{ __('site.recent_none') }}</p>
</section>
@push('scripts')
    <script src="{{ asset('js/recent.js') }}?v={{ @filemtime(public_path('js/recent.js')) ?: 1 }}" defer></script>
@endpush
