<?php

namespace App\Support;

use App\Models\Label;
use App\Models\QuizAttempt;
use App\Models\QuizPassage;
use App\Models\Setting;
use Illuminate\Support\Facades\Crypt;

/**
 * The test "Which one is from the Quran?".
 * A round shows 4 or 5 Arabic passages: exactly one from the Quran, the others from hadith, the Arabic Bible and Arabic poetry.
 * The answers travel inside a sealed (encrypted) token, so nothing is kept on the server between questions
 * and the page source never shows the answer.
 */
class Quiz
{
    public const NAME = 'quran-style';

    public static function enabled(): bool
    {
        return (string) Setting::get('quiz_on', '1') === '1';
    }

    public static function questionsPerRound(): int
    {
        return max(1, min(30, (int) Setting::get('quiz_questions', 10)));
    }

    /** The passages that may be used. */
    private static function pool()
    {
        return QuizPassage::query()
            ->where('is_active', true)
            ->when((string) Setting::get('quiz_only_checked', '0') === '1', fn ($q) => $q->where('is_checked', true))
            ->get();
    }

    /** Can a round be made? It needs Quran passages and at least one more kind. */
    public static function ready(): bool
    {
        $pool = static::pool();

        return $pool->where('kind', 'quran')->isNotEmpty() && $pool->where('kind', '!=', 'quran')->pluck('kind')->unique()->isNotEmpty();
    }

    /**
     * A new round: [token, questions]. Each question has options with a short key and the Arabic text only.
     *
     * @return array{token: string, questions: array<int, array{options: array<int, array{key: string, text: string}>}>}
     */
    public static function start(): array
    {
        $pool = static::pool();
        $qurans = $pool->where('kind', 'quran')->shuffle()->take(static::questionsPerRound())->values();
        $others = $pool->where('kind', '!=', 'quran')->values();

        $min = max(2, min(5, (int) Setting::get('quiz_options_min', 4)));
        $max = max($min, min(5, (int) Setting::get('quiz_options_max', 5)));

        $usedOthers = [];
        $sealed = [];
        $visible = [];

        foreach ($qurans as $quran) {
            $count = random_int($min, $max);
            $chosen = static::distractors($quran, $others, $count - 1, $usedOthers);
            foreach ($chosen as $passage) {
                $usedOthers[] = $passage->id;
            }

            $options = collect([$quran])->merge($chosen)->shuffle()->values();
            $sealed[] = ['ids' => $options->pluck('id')->all(), 'answer' => $options->search(fn ($p) => $p->id === $quran->id)];
            $visible[] = ['options' => $options->map(fn ($p, $i) => ['key' => chr(65 + $i), 'text' => $p->arabic])->all()];
        }

        return ['token' => Crypt::encryptString(json_encode(['for' => static::NAME, 'q' => $sealed, 'until' => now()->addHours(6)->timestamp])), 'questions' => $visible];
    }

    /**
     * The wrong choices for one question: at least one of each kind that exists (hadith, Bible, poetry),
     * then more of any kind, always preferring passages of nearly the same length so length gives nothing away.
     */
    private static function distractors(QuizPassage $quran, $others, int $needed, array $avoid)
    {
        $byCloseness = fn ($set) => $set->sortBy(fn ($p) => abs($p->words - $quran->words) + random_int(0, 30) / 10)->values();

        $fresh = $others->reject(fn ($p) => in_array($p->id, $avoid, true));
        $source = $fresh->count() >= $needed ? $fresh : $others;

        $picked = collect();
        foreach ($source->groupBy('kind')->shuffle() as $group) {
            if ($picked->count() >= $needed) {
                break;
            }
            $picked->push($byCloseness($group)->first());
        }

        $rest = $byCloseness($source->reject(fn ($p) => $picked->contains('id', $p->id)));
        foreach ($rest as $p) {
            if ($picked->count() >= $needed) {
                break;
            }
            $picked->push($p);
        }

        return $picked->take($needed)->values();
    }

    /** Open a token. Null when it is not ours, damaged or too old. */
    private static function open(?string $token): ?array
    {
        try {
            $data = json_decode(Crypt::decryptString((string) $token), true);
        } catch (\Throwable) {
            return null;
        }

        return is_array($data) && ($data['for'] ?? null) === static::NAME && ($data['until'] ?? 0) >= now()->timestamp && is_array($data['q'] ?? null) ? $data : null;
    }

    /**
     * Check one answer. Returns what the reader should now see: right or wrong, and the truth about every passage.
     * Null when the token or the numbers are wrong.
     */
    public static function answer(?string $token, int $question, int $choice): ?array
    {
        $data = static::open($token);
        $q = $data['q'][$question] ?? null;
        if (! $q || ! isset($q['ids'][$choice])) {
            return null;
        }

        $passages = QuizPassage::query()->whereIn('id', $q['ids'])->get()->keyBy('id');
        $kindName = fn ($kind) => Words::get('quiz_kind_'.$kind, QuizPassage::KINDS[$kind] ?? $kind);

        QuizPassage::query()->whereIn('id', $q['ids'])->increment('times_shown');
        QuizPassage::query()->whereKey($q['ids'][$choice])->increment('times_picked');

        return [
            'correct' => $choice === $q['answer'],
            'answer' => $q['answer'],
            'reveal' => collect($q['ids'])->map(function ($id, $i) use ($passages, $kindName) {
                $p = $passages[$id] ?? null;

                return $p ? [
                    'key' => chr(65 + $i), 'kind' => $p->kind, 'kind_name' => $kindName($p->kind), 'reference' => $p->reference,
                    'source' => $p->source_note, 'english' => $p->english, 'note' => $p->note,
                    'style' => Words::get('quiz_note_'.$p->kind),
                ] : ['key' => chr(65 + $i), 'kind' => 'unknown', 'kind_name' => '', 'reference' => '', 'source' => '', 'english' => '', 'note' => '', 'style' => ''];
            })->all(),
        ];
    }

    /**
     * The end of a round: the score is worked out here from the sealed token (not trusted from the page) and kept without any personal detail.
     *
     * @param  array<int, int|null>  $choices  the chosen option number of each question
     */
    public static function finish(?string $token, array $choices): ?array
    {
        $data = static::open($token);
        if (! $data) {
            return null;
        }

        $score = 0;
        $mistakes = [];
        $passages = QuizPassage::query()->whereIn('id', collect($data['q'])->pluck('ids')->flatten())->get()->keyBy('id');

        foreach ($data['q'] as $i => $q) {
            $choice = $choices[$i] ?? null;
            if ($choice === null || ! isset($q['ids'][$choice])) {
                continue;
            }
            if ($choice === $q['answer']) {
                $score++;
            } else {
                $kind = $passages[$q['ids'][$choice]]->kind ?? 'unknown';
                $mistakes[$kind] = ($mistakes[$kind] ?? 0) + 1;
            }
        }

        $total = count($data['q']);
        QuizAttempt::create(['quiz' => static::NAME, 'score' => $score, 'total' => $total, 'by_kind' => $mistakes]);

        return ['score' => $score, 'total' => $total, 'mistakes' => $mistakes];
    }
}
