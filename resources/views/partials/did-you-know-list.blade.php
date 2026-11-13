{{-- The facts as a FAQ: each heading opens to its short answer, its source and who checked it. Needs $entries. --}}
@if ($entries->isEmpty())
    <p class="empty">{{ __('site.dyk_none') }}</p>
@else
    <div class="dyk" data-dyk>
        <p class="dyk__tools">
            <button type="button" class="linklike" data-dyk-open>{{ __('site.index_open_all') }}</button>
            <button type="button" class="linklike" data-dyk-close>{{ __('site.index_close_all') }}</button>
        </p>
        @foreach ($entries as $entry)
            <details class="dyk__item" id="d-{{ $entry->id }}">
                <summary><span class="dyk__title">{{ $entry->title }}</span></summary>
                <div class="dyk__answer article__body">{!! \App\Support\Html::clean((string) $entry->body) !!}</div>
                <p class="dyk__meta">
                    @if ($entry->sourceUrl())
                        {{ __('site.dyk_source') }}: <a href="{{ $entry->sourceUrl() }}" target="_blank" rel="noopener noreferrer nofollow">{{ \Illuminate\Support\Str::limit($entry->source, 70) }}</a>
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
            var box = document.querySelector('[data-dyk]');
            if (!box) return;
            function all(open) { box.querySelectorAll('details').forEach(function (d) { d.open = open; }); }
            var o = box.querySelector('[data-dyk-open]'), c = box.querySelector('[data-dyk-close]');
            if (o) o.addEventListener('click', function () { all(true); });
            if (c) c.addEventListener('click', function () { all(false); });
            // A link to one fact (#d-5) opens it.
            if (location.hash.indexOf('#d-') === 0) { var t = document.querySelector(location.hash); if (t && t.tagName === 'DETAILS') t.open = true; }
        })();
    </script>
@endif
