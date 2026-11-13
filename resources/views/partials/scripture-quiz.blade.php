{{-- The test "Which scripture?". The script (public/js/scripture.js) draws the questions. All words come from Labels. --}}
@php
    $keys = ['title', 'question', 'right', 'wrong', 'mistakes_title', 'mistake_line', 'comment_high', 'comment_mid', 'comment_low', 'report'];
    $words = [];
    foreach ($keys as $key) {
        $words[$key] = \App\Support\Words::get('scripture_'.$key);
    }
    // The buttons and sentences every test shares come from the Quran test's labels.
    foreach (['start', 'loading', 'error', 'question_of', 'check', 'next', 'finish', 'result_title', 'result_text', 'try_again', 'no_mistakes'] as $key) {
        $words[$key] = \App\Support\Words::get('quiz_'.$key);
    }
@endphp
@if (\App\Support\ScriptureQuiz::enabled() && \App\Support\ScriptureQuiz::ready())
    <section class="quiz" data-scripture
        data-start-url="{{ route('scripture.start') }}" data-answer-url="{{ route('scripture.answer') }}" data-finish-url="{{ route('scripture.finish') }}"
        data-words="{{ json_encode($words, JSON_UNESCAPED_UNICODE) }}" aria-labelledby="scripture-title">
        <h2 class="quiz__title" id="scripture-title">{{ \App\Support\Words::get('scripture_title') }}</h2>

        <div class="quiz__intro" data-quiz-intro>
            <p>{{ \App\Support\Words::get('scripture_intro') }}</p>
            <p class="quiz__fine">{{ \App\Support\Words::get('scripture_disclaimer') }}</p>
            <p><button type="button" class="btn btn--primary" data-quiz-start>{{ \App\Support\Words::get('quiz_start') }}</button></p>
        </div>

        <div class="quiz__stage" data-quiz-stage hidden aria-live="polite"></div>
        <noscript><p class="notice">{{ \App\Support\Words::get('quiz_noscript') }}</p></noscript>
    </section>
@else
    <p class="empty">{{ \App\Support\Words::get('quiz_not_ready') }}</p>
@endif
