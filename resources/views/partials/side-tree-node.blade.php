{{-- One sub-category of the side tree: a row that opens to its topics and deeper sub-categories. The row holding the article being read is open. --}}
@php
    $cat = $node['category'];
    $holds = \App\Support\CategoryIndex::contains($node, (int) $current->id);
@endphp
@if ($node['truncated'])
    <p class="side-tree__more"><a href="{{ route('category.show', ['slug' => $cat->slug, 'view' => 'index']) }}">{{ $cat->name }} &rarr;</a></p>
@else
    <details class="side-tree__cat" @if ($holds) open @endif>
        <summary>{{ $cat->name }}</summary>
        @foreach ($node['children'] as $child)
            @include('partials.side-tree-node', ['node' => $child, 'current' => $current])
        @endforeach
        @if (! empty($node['articles']))
            <ul class="side-tree__list">
                @foreach ($node['articles'] as $line)
                    @include('partials.side-tree-line', ['line' => $line, 'current' => $current])
                @endforeach
            </ul>
        @endif
    </details>
@endif
