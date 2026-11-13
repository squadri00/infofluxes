{{-- The facts in four columns: each heading is a card that opens to its short answer, its source and who checked it.
     Opening one closes the one that was open, so a reader clicks, reads, and clicks another. Needs $entries. --}}
@if ($entries->isEmpty())
    <p class="empty">{{ __('site.dyk_none') }}</p>
@else
    <div class="dyk" data-dyk>
        @foreach ($entries as $entry)
            <details class="dyk__item" id="d-{{ $entry->id }}">
                <summary>
                    <span class="dyk__num" aria-hidden="true">{{ $loop->iteration }}</span>
                    <span class="dyk__title">{{ $entry->title }}</span>
                </summary>
                <div class="dyk__answer article__body">{!! \App\Support\Html::clean((string) $entry->body) !!}</div>
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
            var items = box.querySelectorAll('details');
            items.forEach(function (d) {
                d.addEventListener('toggle', function () {
                    if (!d.open) return;
                    items.forEach(function (o) { if (o !== d) o.open = false; });
                });
            });
            // A link to one fact (#d-5) opens it.
            if (location.hash.indexOf('#d-') === 0) { var t = box.querySelector(location.hash); if (t) t.open = true; }
        })();
    </script>
@endif
