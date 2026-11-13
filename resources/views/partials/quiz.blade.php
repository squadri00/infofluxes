{{-- The test "Which one is from the Quran?". The script (public/js/quiz.js) draws the questions. All words come from Labels. --}}
@php
    $keys = ['title', 'start', 'loading', 'error', 'question_of', 'pick', 'check', 'next', 'finish', 'right', 'wrong', 'meaning', 'this_is', 'result_title', 'result_text', 'comment_high', 'comment_mid', 'comment_low', 'try_again', 'mistakes_title', 'mistake_line', 'no_mistakes', 'report', 'kind_quran', 'kind_hadith', 'kind_bible', 'kind_poetry', 'answer_choose'];
    $words = [];
    foreach ($keys as $key) {
        $words[$key] = \App\Support\Words::get('quiz_'.$key);
    }
@endphp
@if (\App\Support\Quiz::enabled() && \App\Support\Quiz::ready())
    <section class="quiz" data-quiz
        data-start-url="{{ route('quiz.start') }}" data-answer-url="{{ route('quiz.answer') }}" data-finish-url="{{ route('quiz.finish') }}"
        data-words="{{ json_encode($words, JSON_UNESCAPED_UNICODE) }}" aria-labelledby="quiz-title">
        <h2 class="quiz__title" id="quiz-title">{{ \App\Support\Words::get('quiz_title') }}</h2>

        <div class="quiz__intro" data-quiz-intro>
            <p>{{ \App\Support\Words::get('quiz_intro') }} {{ \App\Support\Words::get('quiz_intro_2') }}</p>
            <p class="quiz__fine">{{ \App\Support\Words::get('quiz_disclaimer') }}</p>
            <p><button type="button" class="btn btn--primary" data-quiz-start>{{ \App\Support\Words::get('quiz_start') }}</button></p>
        </div>

        <div class="quiz__stage" data-quiz-stage hidden aria-live="polite"></div>
        <noscript><p class="notice">{{ \App\Support\Words::get('quiz_noscript') }}</p></noscript>
    </section>
@else
    <p class="empty">{{ \App\Support\Words::get('quiz_not_ready') }}</p>
@endif
