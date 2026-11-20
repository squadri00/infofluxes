{{-- One heading of the Index view and everything below it.
     Level 1 with sub-headings is a section title; level 1 or 2 is a row that opens; deeper is a sub-title inside the row.
     Needs $node, $level and $trail (the words of its place, for the quick search). --}}
@php
    $cat = $node['category'];
    $hasKids = count($node['children']) > 0;
    $code = ($node['virtual'] ?? false) ? '' : \App\Support\CategoryIndex::codeOf($cat);
    $dom = $node['dom'] ?? $cat->id;
    $name = $cat->name;
    $note = trim(strip_tags((string) $cat->description));
    $nextTrail = $name.' '.$trail;
@endphp
@if ($node['truncated'])
    <p class="qi__more">
        <span class="qi__code">{{ $code }}</span>
        <a href="{{ route('category.show', ['slug' => $cat->slug, 'view' => 'index']) }}">{{ $name }}</a>
        <span class="qi__soon">{{ str_replace(':n', $node['subjects'], __('site.index_subjects')) }}</span>
    </p>
@elseif ($level === 1 && $hasKids)
    <section class="qi__part" data-qi-part>
        <h2 class="qi__part-title"><span class="qi__code">{{ $code }}</span> {{ $name }}</h2>
        @if ($note !== '')<p class="qi__note">{{ \Illuminate\Support\Str::limit($note, 200) }}</p>@endif
        {{-- Subjects that sit directly in a Part come first, then its rows. --}}
        @if (! empty($node['articles']))
            <ul class="qi__list">
                @foreach ($node['articles'] as $line)
                    @include('partials.index-line', ['line' => $line, 'trail' => $nextTrail])
                @endforeach
            </ul>
        @endif
        @foreach ($node['children'] as $child)
            @include('partials.index-node', ['node' => $child, 'level' => 2, 'trail' => $nextTrail])
        @endforeach
    </section>
@elseif ($level <= 2)
    <details class="qi__cat" data-qi-cat id="e-{{ $dom }}">
        <summary><span class="qi__code">{{ $code }}</span> {{ $name }}</summary>
        @if ($note !== '')<p class="qi__note">{{ \Illuminate\Support\Str::limit($note, 200) }}</p>@endif
        @foreach ($node['children'] as $child)
            @include('partials.index-node', ['node' => $child, 'level' => 3, 'trail' => $nextTrail])
        @endforeach
        @if (! empty($node['articles']))
            <ul class="qi__list">
                @foreach ($node['articles'] as $line)
                    @include('partials.index-line', ['line' => $line, 'trail' => $nextTrail])
                @endforeach
            </ul>
        @endif
    </details>
@else
    <div class="qi__sub" data-qi-sub id="e-{{ $dom }}">
        <h3 class="qi__sub-title"><span class="qi__code">{{ $code }}</span> {{ $name }}</h3>
        @foreach ($node['children'] as $child)
            @include('partials.index-node', ['node' => $child, 'level' => $level + 1, 'trail' => $nextTrail])
        @endforeach
        @if (! empty($node['articles']))
            <ul class="qi__list">
                @foreach ($node['articles'] as $line)
                    @include('partials.index-line', ['line' => $line, 'trail' => $nextTrail])
                @endforeach
            </ul>
        @endif
    </div>
@endif