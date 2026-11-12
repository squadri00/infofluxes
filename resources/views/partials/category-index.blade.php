{{-- The Index view: the branch below a category as a quick list. Needs $category and $index (see App\Support\CategoryIndex). --}}
@php $rootNode = $index['root']; @endphp
<div class="qi" data-qi data-found="{{ __('site.index_found') }}">
    <p class="qi__total">{{ str_replace(':n', $index['subjects'], __('site.index_subjects')) }}</p>

    <div class="qi__tools" hidden data-qi-tools>
        <input type="search" class="qi__search" data-qi-search placeholder="{{ __('site.index_search') }}" aria-label="{{ __('site.index_search') }}" autocomplete="off">
        <button type="button" class="btn btn--small" data-qi-open>{{ __('site.index_open_all') }}</button>
        <button type="button" class="btn btn--small" data-qi-close>{{ __('site.index_close_all') }}</button>
        <span class="qi__count" data-qi-count aria-live="polite"></span>
    </div>
    <p class="empty" data-qi-none hidden>{{ __('site.index_none') }}</p>

    @if (empty($rootNode['children']) && empty($rootNode['articles']))
        <p class="empty">{{ __('site.no_articles') }}</p>
    @endif

    @if (! empty($rootNode['articles']))
        <ul class="qi__list">
            @foreach ($rootNode['articles'] as $line)
                @include('partials.index-line', ['line' => $line, 'trail' => $category->name])
            @endforeach
        </ul>
    @endif

    @foreach ($rootNode['children'] as $child)
        @include('partials.index-node', ['node' => $child, 'level' => 1, 'trail' => $category->name])
    @endforeach
</div>