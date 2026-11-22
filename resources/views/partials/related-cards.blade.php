{{-- The "More facts" and "More in this category" blocks: equal cards in a tidy grid.
     $items: articles or Did You Know? facts. $kind: 'article' or 'fact'. A card is a picture on top (or a coloured panel with the first letter),
     a small label, the title, a short line and a "Read" line. The whole card is one link. --}}
@php $kind = $kind ?? 'article'; @endphp
<ul class="related">
    @foreach ($items as $item)
        @php
            if ($kind === 'fact') {
                $url = $item->url();
                $picture = $item->image;
                $label = __('site.dyk_title');
                $summary = $item->summary(130);
                $read = __('site.dyk_read_more');
            } else {
                $url = route('article.show', $item->slug);
                $picture = $item->main_image;
                $label = $item->category?->name ?? '';
                $summary = \Illuminate\Support\Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags((string) $item->short_detail))), 130);
                $read = __('site.read_article');
            }
        @endphp
        <li class="rcard">
            <div class="rcard__media" aria-hidden="true">
                @if ($picture)
                    <img src="{{ asset('storage/'.$picture) }}" alt="" loading="lazy">
                @else
                    <span class="rcard__blank">{{ mb_strtoupper(mb_substr(trim((string) $item->title), 0, 1)) }}</span>
                @endif
            </div>
            <div class="rcard__body">
                @if ($label !== '')<span class="rcard__label">{{ $label }}</span>@endif
                <h3 class="rcard__title"><a href="{{ $url }}">{{ $item->title }}</a></h3>
                @if ($summary !== '')<p class="rcard__hook">{{ $summary }}</p>@endif
                <span class="rcard__read">{{ $read }} <span class="rcard__arrow" aria-hidden="true">&rarr;</span></span>
            </div>
        </li>
    @endforeach
</ul>
