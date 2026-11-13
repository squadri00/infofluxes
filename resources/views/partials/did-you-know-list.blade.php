{{-- The facts in four columns: each heading is a card that opens to its short answer, its source and who checked it.
     Opening one closes the one that was open, so a reader clicks, reads, and clicks another.
     A fact with a table, or a long answer, opens across the whole width (a table does not fit in one quarter of the page). Needs $entries. --}}
@if ($entries->isEmpty())
    <p class="empty">{{ __('site.dyk_none') }}</p>
@else
    <div class="dyk" data-dyk>
        @foreach ($entries as $entry)
            @php
                $answer = \App\Support\Html::clean((string) $entry->body);
                $wide = str_contains($answer, '<table') || mb_strlen(strip_tags($answer)) > 700;
                $full = $entry->article && $entry->article->status === 'published' ? $entry->article : null;
            @endphp
            <details class="{{ implode(' ', array_filter(['dyk__item', $wide ? 'dyk__item--wide' : null])) }}" id="d-{{ $entry->id }}"{!! $wide ? ' data-wide' : '' !!}>
                <summary>
                    <span class="dyk__num" aria-hidden="true">{{ $loop->iteration }}</span>
                    <span class="dyk__title">{{ $entry->title }}</span>
                </summary>
                <div class="dyk__answer article__body">{!! $answer !!}</div>
                @if ($full)
                    <p class="dyk__more"><a class="btn btn--small" href="{{ route('article.show', $full->slug) }}">{{ __('site.dyk_read_article') }} &rarr;</a></p>
                @endif
                <p class="dyk__meta">
                    @if ($entry->sourceUrl())
                        {{ __('site.dyk_source') }}: <a href="{{ $entry->sourceUrl() }}" target="_blank" rel="noopener noreferrer nofollow">{{ \Illuminate\Support\Str::limit($entry->source, 60) }}</a>
                    @else
                        {{ __('site.dyk_source') }}: {{ $entry->source }}
                    @endif
                    @if (filled($entry->reviewer_name)) &middot; {{ __('site.reviewed_by') }} {{ $entry->reviewer_name }}@endif
                    @if ($entry->ai_assisted) &middot; {{ __('site.dyk_ai') }}@endif
                </p>
            </details>
        @endforeach
    </div>
    <script>
        (function () {
            var box = document.currentScript.previousElementSibling;
            if (!box || !box.hasAttribute('data-dyk')) return;
            var items = Array.prototype.slice.call(box.querySelectorAll('details'));

            function columns() {
                return Math.max(1, getComputedStyle(box).gridTemplateColumns.split(' ').length);
            }

            // Cards keep their own order. A wide card that is open moves to the line just under its row, across the whole width,
            // so the cards of that row stay together and nothing else jumps.
            function arrange() {
                var cols = columns();
                items.forEach(function (d, i) {
                    d.style.order = String(i * 2);
                    if (d.open && d.hasAttribute('data-wide') && cols > 1) {
                        var rowEnd = Math.min(items.length - 1, (Math.floor(i / cols) + 1) * cols - 1);
                        d.style.order = String(rowEnd * 2 + 1);
                    }
                });
            }

            items.forEach(function (d) {
                d.addEventListener('toggle', function () {
                    if (d.open) {
                        items.forEach(function (o) { if (o !== d) o.open = false; });
                    }
                    arrange();
                    if (d.open) { d.scrollIntoView({ block: 'nearest', behavior: 'smooth' }); }
                });
            });
            window.addEventListener('resize', arrange);
            arrange();

            // A link to one fact (#d-5) opens it.
            if (location.hash.indexOf('#d-') === 0) { var t = box.querySelector(location.hash); if (t) t.open = true; }
        })();
    </script>
@endif
