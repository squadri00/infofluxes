{{-- The left side of an article or a fact: the branch it belongs to, every sub-category opening to its topics (a bird's-eye view).
     Needs $root, $tree and $current (the article being read, or null); $currentFact (the fact being read) and $sideFacts (the facts by category) are optional. --}}
@php
    $current = $current ?? null;
    $currentFact = $currentFact ?? null;
    $sideFacts = $sideFacts ?? collect();
@endphp
<nav class="side-tree" aria-label="{{ __('site.related_topics') }}">
    <h2 class="side-tree__title"><a href="{{ route('category.show', $root->slug) }}">{{ $root->name }}</a></h2>
    <p class="side-tree__links">
        <a href="{{ route('category.show', ['slug' => $root->slug, 'view' => 'index']) }}">{{ __('site.index_view') }} &rarr;</a>
    </p>

    @if (! empty($tree['root']['articles']) || $sideFacts->has($root->id))
        <ul class="side-tree__list">
            @foreach ($sideFacts->get($root->id, collect()) as $fact)
                @include('partials.side-tree-fact', ['fact' => $fact, 'currentFact' => $currentFact])
            @endforeach
            @foreach ($tree['root']['articles'] as $line)
                @include('partials.side-tree-line', ['line' => $line, 'current' => $current])
            @endforeach
        </ul>
    @endif

    @foreach ($tree['root']['children'] as $child)
        @include('partials.side-tree-node', ['node' => $child, 'current' => $current, 'currentFact' => $currentFact, 'sideFacts' => $sideFacts])
    @endforeach
</nav>
