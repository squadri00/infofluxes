<?php

namespace App\Support;

use App\Models\QuizAttempt;
use App\Models\User;

/** The challenge scores of a signed-in member: every attempt, and the best one of each test. */
class ChallengeScores
{
    /** The name of a test as it is saved => its title. The order test has a level after its name ("quran-order-easy"). */
    public static function label(string $quiz): string
    {
        $map = ['quran-style' => 'quiz_title', 'fact-or-myth' => 'facts_title', 'scripture-source' => 'scripture_title', 'who-said-it' => 'who_title', 'quran-order' => 'order_title'];
        foreach ($map as $name => $key) {
            if ($quiz === $name || str_starts_with($quiz, $name.'-')) {
                $level = $quiz === $name ? '' : ucfirst(substr($quiz, strlen($name) + 1));

                return __('challenge.'.$key).($level !== '' ? ' ('.$level.')' : '');
            }
        }

        return $quiz;
    }

    /** @return \Illuminate\Support\Collection<int, QuizAttempt> */
    public static function history(User $user, int $limit = 100)
    {
        return QuizAttempt::query()->where('user_id', $user->id)->orderByDesc('id')->limit($limit)->get();
    }

    /** The best attempt of each test (the highest share of right answers; the newest when equal). @return \Illuminate\Support\Collection<string, QuizAttempt> */
    public static function best(User $user)
    {
        return QuizAttempt::query()->where('user_id', $user->id)->get()
            ->groupBy('quiz')
            ->map(fn ($rows) => $rows->sortByDesc(fn ($r) => [$r->total > 0 ? $r->score / $r->total : 0, $r->id])->first())
            ->sortKeys();
    }

    public static function percent(QuizAttempt $attempt): int
    {
        return $attempt->total > 0 ? (int) round($attempt->score * 100 / $attempt->total) : 0;
    }
}
