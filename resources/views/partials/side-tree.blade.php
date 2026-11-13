{{-- The left side of an article: the branch it belongs to, every sub-category opening to its topics (a bird's-eye view). Needs $root, $tree, $current. --}}
<nav class="side-tree" aria-label="{{ __('site.related_topics') }}">
    <h2 class="side-tree__title"><a href="{{ route('category.show', $root->slug) }}">{{ $root->name }}</a></h2>
    <p class="side-tree__links">
        <a href="{{ route('category.show', ['slug' => $root->slug, 'view' => 'index']) }}">{{ __('site.index_view') }} &rarr;</a>
    </p>

    @if (! empty($tree['root']['articles']))
        <ul class="side-tree__list">
            @foreach ($tree['root']['articles'] as $line)
                @include('partials.side-tree-line', ['line' => $line, 'current' => $current])
            @endforeach
        </ul>
    @endif

    @foreach ($tree['root']['children'] as $child)
        @include('partials.side-tree-node', ['node' => $child, 'current' => $current])
    @endforeach
</nav>
