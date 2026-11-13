{{-- The test "Fact or myth?". The script (public/js/facts.js) draws the questions. All words come from Labels. --}}
@php
    $words = [];
    foreach (['question', 'true', 'false', 'right', 'wrong', 'source', 'mistakes_title', 'comment_high', 'comment_mid', 'comment_low', 'report'] as $key) {
        $words[$key] = \App\Support\Words::get('facts_'.$key);
    }
    // The buttons and sentences every test shares come from the Quran test's labels.
    foreach (['start', 'loading', 'error', 'question_of', 'next', 'finish', 'result_title', 'result_text', 'try_again', 'no_mistakes', 'mistake_line'] as $key) {
        $words[$key] = \App\Support\Words::get('quiz_'.$key);
    }
@endphp
@if (\App\Support\FactQuiz::enabled() && \App\Support\FactQuiz::ready())
    <section class="quiz" data-facts
        data-start-url="{{ route('facts.start') }}" data-answer-url="{{ route('facts.answer') }}" data-finish-url="{{ route('facts.finish') }}"
        data-words="{{ json_encode($words, JSON_UNESCAPED_UNICODE) }}" aria-labelledby="facts-title">
        <h2 class="quiz__title" id="facts-title">{{ \App\Support\Words::get('facts_title') }}</h2>

        <div class="quiz__intro" data-quiz-intro>
            <p>{{ \App\Support\Words::get('facts_intro') }}</p>
            <p class="quiz__fine">{{ \App\Support\Words::get('facts_disclaimer') }}</p>
            <p><button type="button" class="btn btn--primary" data-quiz-start>{{ \App\Support\Words::get('quiz_start') }}</button></p>
        </div>

        <div class="quiz__stage" data-quiz-stage hidden aria-live="polite"></div>
        <noscript><p class="notice">{{ \App\Support\Words::get('quiz_noscript') }}</p></noscript>
    </section>
@else
    <p class="empty">{{ \App\Support\Words::get('quiz_not_ready') }}</p>
@endif
