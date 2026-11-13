{{-- One sub-category of the side tree: a row that opens to its topics and deeper sub-categories. The row holding the article being read is open. --}}
@php
    $cat = $node['category'];
    $currentFact = $currentFact ?? null;
    $sideFacts = $sideFacts ?? collect();
    $holds = ($current && \App\Support\CategoryIndex::contains($node, (int) $current->id))
        || ($currentFact && \App\Support\CategoryIndex::containsCategory($node, (int) $currentFact->category_id));
@endphp
@if ($node['truncated'])
    <p class="side-tree__more"><a href="{{ route('category.show', ['slug' => $cat->slug, 'view' => 'index']) }}">{{ $cat->name }} &rarr;</a></p>
@else
    <details class="side-tree__cat" @if ($holds) open @endif>
        <summary>{{ $cat->name }}</summary>
        @foreach ($node['children'] as $child)
            @include('partials.side-tree-node', ['node' => $child, 'current' => $current, 'currentFact' => $currentFact, 'sideFacts' => $sideFacts])
        @endforeach
        @if (! empty($node['articles']) || $sideFacts->has($cat->id))
            <ul class="side-tree__list">
                @foreach ($sideFacts->get($cat->id, collect()) as $fact)
                    @include('partials.side-tree-fact', ['fact' => $fact, 'currentFact' => $currentFact])
                @endforeach
                @foreach ($node['articles'] as $line)
                    @include('partials.side-tree-line', ['line' => $line, 'current' => $current])
                @endforeach
            </ul>
        @endif
    </details>
@endif
