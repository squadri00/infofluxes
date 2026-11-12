{{-- Previous / Next with the page number. $paginator is required. --}}
@if ($paginator->hasPages())
    <nav class="pager" aria-label="Pages">
        @if ($paginator->onFirstPage())<span class="pager__link pager__link--off">&larr; {{ __('site.previous') }}</span>@else<a class="pager__link" href="{{ $paginator->previousPageUrl() }}" rel="prev">&larr; {{ __('site.previous') }}</a>@endif
        <span class="pager__page">{{ str_replace([':page', ':pages'], [$paginator->currentPage(), $paginator->lastPage()], __('site.page_of')) }}</span>
        @if ($paginator->hasMorePages())<a class="pager__link" href="{{ $paginator->nextPageUrl() }}" rel="next">{{ __('site.next') }} &rarr;</a>@else<span class="pager__link pager__link--off">{{ __('site.next') }} &rarr;</span>@endif
    </nav>
@endif
