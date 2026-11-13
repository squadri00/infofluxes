{{-- The test "Put the chapters in order". The script (public/js/order.js) draws it and handles dragging. All words come from Labels. --}}
@php
    $words = [];
    foreach (['easy', 'hard', 'practice_easy', 'practice_hard', 'range', 'range_hard', 'practice_help', 'pool', 'slots', 'place', 'first', 'last', 'empty', 'tap', 'check', 'reset', 'loading', 'error', 'result_title', 'score', 'perfect', 'comment_high', 'comment_mid', 'comment_low', 'number', 'verses', 'you_put', 'nothing', 'again', 'practice_again', 'tries', 'placed', 'right_place', 'wrong_place', 'done', 'done_best', 'report'] as $key) {
        $words[$key] = \App\Support\Words::get('order_'.$key);
    }
    $size = \App\Support\OrderQuiz::size();
    $chapters = \App\Support\OrderQuiz::ready() ? \App\Models\QuranChapter::query()->orderBy('number')->get(['number', 'arabic_name', 'name', 'meaning', 'honorific', 'verses']) : collect();
@endphp
@if (\App\Support\OrderQuiz::enabled() && \App\Support\OrderQuiz::ready())
    <section class="quiz order" data-order
        data-start-url="{{ route('order.start') }}" data-check-url="{{ route('order.check') }}" data-try-url="{{ route('order.try') }}" data-size="{{ $size }}"
        data-words="{{ json_encode($words, JSON_UNESCAPED_UNICODE) }}" aria-labelledby="order-title">
        <h2 class="quiz__title" id="order-title">{{ \App\Support\Words::get('order_title') }}</h2>

        <div class="quiz__intro" data-order-intro>
            <p>{{ \App\Support\Words::get('order_intro') }}</p>
            <div class="order__levels">
                <div class="order__level">
                    <button type="button" class="btn btn--primary" data-order-level="easy">{{ str_replace(':n', $size, \App\Support\Words::get('order_easy')) }}</button>
                    <p class="quiz__fine">{{ \App\Support\Words::get('order_easy_note') }}</p>
                </div>
                <div class="order__level">
                    <button type="button" class="btn btn--primary" data-order-level="hard">{{ str_replace(':n', $size, \App\Support\Words::get('order_hard')) }}</button>
                    <p class="quiz__fine">{{ \App\Support\Words::get('order_hard_note') }}</p>
                </div>
            </div>
            <div class="order__practice">
                <button type="button" class="btn" data-order-level="easy" data-order-practice>{{ str_replace(':n', $size, \App\Support\Words::get('order_practice_easy')) }}</button>
                <button type="button" class="btn" data-order-level="hard" data-order-practice>{{ str_replace(':n', $size, \App\Support\Words::get('order_practice_hard')) }}</button>
                <p class="quiz__fine">{{ \App\Support\Words::get('order_practice_note') }}</p>
            </div>
            <p class="quiz__fine">{{ \App\Support\Words::get('order_disclaimer') }}</p>
        </div>

        <div class="quiz__stage" data-order-stage hidden aria-live="polite"></div>
        <noscript><p class="notice">{{ \App\Support\Words::get('quiz_noscript') }}</p></noscript>

        {{-- The list of all 114 chapters, sliding in from the left edge. It is hidden during a scored round. --}}
        <button type="button" class="chapters-tab" data-chapters-open aria-controls="chapters-panel" aria-expanded="false" hidden>
            <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false"><path d="M4 5.5C4 4.7 4.7 4 5.5 4H11v15H5.5C4.7 19 4 18.3 4 17.5zM20 5.5c0-.8-.7-1.5-1.5-1.5H13v15h5.5c.8 0 1.5-.7 1.5-1.5z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg>
            <span>{{ \App\Support\Words::get('order_index_open') }}</span>
        </button>
        <div class="chapters-backdrop" data-chapters-backdrop hidden></div>
        <aside class="chapters-panel" id="chapters-panel" data-chapters-panel role="dialog" aria-labelledby="chapters-title" aria-hidden="true" inert>
            <header class="chapters-panel__head">
                <h2 class="chapters-panel__title" id="chapters-title">{{ \App\Support\Words::get('order_index_title') }}</h2>
                <button type="button" class="chapters-panel__close" data-chapters-close aria-label="{{ \App\Support\Words::get('order_index_close') }}">&times;</button>
            </header>
            <p class="chapters-panel__note">{{ \App\Support\Words::get('order_index_note') }}</p>
            <input type="search" class="chapters-panel__search" data-chapters-search placeholder="{{ \App\Support\Words::get('order_index_search') }}" aria-label="{{ \App\Support\Words::get('order_index_search') }}" autocomplete="off">
            <ol class="chapters-list" data-chapters-list>
                @foreach ($chapters as $c)
                    <li class="chapters-list__item" data-number="{{ $c->number }}" data-find="{{ mb_strtolower($c->number.' '.$c->name.' '.$c->meaning.' '.$c->arabic_name) }}">
                        <span class="chapters-list__no">{{ $c->number }}</span>
                        <span class="chapters-list__ar" lang="ar" dir="rtl">{{ $c->arabic_name }}</span>
                        <span class="chapters-list__en"><strong>{{ $c->shown_name }}</strong> <span>{{ $c->shown_meaning }}</span></span>
                        <span class="chapters-list__verses">{{ str_replace(':n', $c->verses, \App\Support\Words::get('order_verses')) }}</span>
                    </li>
                @endforeach
            </ol>
            <p class="chapters-panel__none" data-chapters-none hidden>{{ \App\Support\Words::get('order_index_none') }}</p>
            {{-- A second close button on the right edge of the open panel --}}
            <button type="button" class="chapters-edge" data-chapters-close aria-label="{{ \App\Support\Words::get('order_index_close') }}">
                <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false"><path d="M15 6l-6 6 6 6" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </button>
        </aside>
    </section>
@else
    <p class="empty">{{ \App\Support\Words::get('quiz_not_ready') }}</p>
@endif
