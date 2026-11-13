<?php

namespace App\Support;

use App\Models\FactStatement;
use App\Models\Label;
use App\Models\QuizAttempt;
use App\Models\Setting;
use Illuminate\Support\Facades\Crypt;

/**
 * The test "Fact or myth?": one statement at a time, and the reader says true or false.
 * Like the other tests, the answers travel inside a sealed (encrypted) token: nothing is kept on the server between
 * questions and the page source never shows an answer. A round has about as many true as false statements.
 */
class FactQuiz
{
    public const NAME = 'fact-or-myth';

    /** What the reader got wrong, in two kinds: a myth taken for a fact, and a fact taken for a myth. */
    public const BELIEVED_MYTH = 'believed_myth';

    public const DOUBTED_FACT = 'doubted_fact';

    public static function enabled(): bool
    {
        return (string) Setting::get('facts_on', '1') === '1';
    }

    public static function questionsPerRound(): int
    {
        return max(1, min(30, (int) Setting::get('facts_questions', 10)));
    }

    private static function pool()
    {
        return FactStatement::query()
            ->where('is_active', true)
            ->when((string) Setting::get('facts_only_checked', '0') === '1', fn ($q) => $q->where('is_checked', true))
            ->get();
    }

    /** Can a round be made? It needs at least one true and one false statement. */
    public static function ready(): bool
    {
        $pool = static::pool();

        return $pool->where('is_true', true)->isNotEmpty() && $pool->where('is_true', false)->isNotEmpty();
    }

    /**
     * A new round: the statements in a mixed order. Answers are only in the sealed token.
     *
     * @return array{token: string, questions: array<int, array{text: string}>}
     */
    public static function start(): array
    {
        $pool = static::pool();
        $want = min(static::questionsPerRound(), $pool->count());

        // About half true and half false, whatever the number: the odd one goes either way.
        $trueWant = min($pool->where('is_true', true)->count(), intdiv($want, 2) + ($want % 2 && random_int(0, 1) ? 1 : 0));
        $falseWant = min($pool->where('is_true', false)->count(), $want - $trueWant);
        $trueWant = min($pool->where('is_true', true)->count(), $want - $falseWant);

        $picked = $pool->where('is_true', true)->shuffle()->take($trueWant)
            ->merge($pool->where('is_true', false)->shuffle()->take($falseWant))
            ->shuffle()->values();

        $sealed = $picked->map(fn ($s) => ['id' => $s->id, 'answer' => $s->is_true])->all();
        $visible = $picked->map(fn ($s) => ['text' => $s->statement])->all();

        return ['token' => Crypt::encryptString(json_encode(['for' => static::NAME, 'q' => $sealed, 'until' => now()->addHours(6)->timestamp])), 'questions' => $visible];
    }

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
     * Check one answer ($choice is 1 for "true" and 0 for "false") and give the explanation and the source.
     * Null when the token or the numbers are wrong.
     */
    public static function answer(?string $token, int $question, int $choice): ?array
    {
        $data = static::open($token);
        $q = $data['q'][$question] ?? null;
        if (! $q || ! in_array($choice, [0, 1], true)) {
            return null;
        }

        $statement = FactStatement::query()->find($q['id']);
        if (! $statement) {
            return null;
        }

        $correct = (bool) $choice === (bool) $q['answer'];
        $statement->increment('times_shown');
        if ($correct) {
            $statement->increment('times_correct');
        }

        return [
            'correct' => $correct,
            'is_true' => (bool) $q['answer'],
            'verdict' => Words::get($q['answer'] ? 'facts_is_true' : 'facts_is_false', $q['answer'] ? 'true' : 'false'),
            'explanation' => $statement->explanation,
            'source' => $statement->source,
        ];
    }

    /**
     * The end of a round. The score is worked out here from the sealed token and kept without any personal detail:
     * only the score and how many myths were believed and facts doubted.
     *
     * @param  array<int, int|null>  $choices  1 for "true", 0 for "false"
     */
    public static function finish(?string $token, array $choices): ?array
    {
        $data = static::open($token);
        if (! $data) {
            return null;
        }

        $score = 0;
        $mistakes = [];

        foreach ($data['q'] as $i => $q) {
            $choice = $choices[$i] ?? null;
            if (! in_array($choice, [0, 1], true)) {
                continue;
            }
            if ((bool) $choice === (bool) $q['answer']) {
                $score++;
            } else {
                $kind = $q['answer'] ? self::DOUBTED_FACT : self::BELIEVED_MYTH;
                $mistakes[$kind] = ($mistakes[$kind] ?? 0) + 1;
            }
        }

        $total = count($data['q']);
        QuizAttempt::create(['quiz' => static::NAME, 'score' => $score, 'total' => $total, 'by_kind' => $mistakes]);

        return [
            'score' => $score,
            'total' => $total,
            'mistakes' => $mistakes,
            'kind_names' => collect(array_keys($mistakes))->mapWithKeys(fn ($k) => [$k => Words::get('facts_miss_'.$k)])->all(),
        ];
    }
}
