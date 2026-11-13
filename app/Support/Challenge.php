<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/** The five tests of "Challenge yourself", in the order they are shown. Titles and texts come from lang/en/challenge.php. */
class Challenge
{
    /**
     * @return Collection<int, array{key: string, title: string, text: string, on: bool, ready: bool, view: string}>
     */
    public static function tests(): Collection
    {
        $defs = [
            ['quran', 'quiz', Quiz::class, 'partials.quiz'],
            ['scripture', 'scripture', ScriptureQuiz::class, 'partials.scripture-quiz'],
            ['facts', 'facts', FactQuiz::class, 'partials.facts-quiz'],
            ['who', 'who', WhoQuiz::class, 'partials.who-quiz'],
            ['order', 'order', OrderQuiz::class, 'partials.order-quiz'],
        ];

        return collect($defs)->map(fn (array $d) => [
            'key' => $d[0],
            'title' => Words::get($d[1].'_title'),
            'text' => Str::limit(Words::get($d[1].'_intro'), 170),
            'on' => (bool) $d[2]::enabled(),
            'ready' => (bool) $d[2]::ready(),
            'view' => $d[3],
        ]);
    }

    /** Is at least one test switched on? */
    public static function anyOn(): bool
    {
        return static::tests()->contains('on', true);
    }
}
