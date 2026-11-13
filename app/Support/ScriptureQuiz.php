<?php

namespace App\Support;

use App\Models\Label;
use App\Models\QuizAttempt;
use App\Models\ScripturePassage;
use App\Models\Setting;
use Illuminate\Support\Facades\Crypt;

/**
 * The test "Which scripture?": one English passage at a time, and the reader says which scripture it is from.
 * Like the Quran test, the answers travel inside a sealed (encrypted) token: nothing is kept on the server between
 * questions and the page source never shows an answer.
 */
class ScriptureQuiz
{
    public const NAME = 'scripture-source';

    public static function enabled(): bool
    {
        return (string) Setting::get('scripture_on', '1') === '1';
    }

    public static function questionsPerRound(): int
    {
        return max(1, min(30, (int) Setting::get('scripture_questions', 10)));
    }

    private static function pool()
    {
        return ScripturePassage::query()
            ->where('is_active', true)
            ->when((string) Setting::get('scripture_only_checked', '0') === '1', fn ($q) => $q->where('is_checked', true))
            ->get();
    }

    /** Can a round be made? It needs passages of at least two scriptures. */
    public static function ready(): bool
    {
        return static::pool()->pluck('kind')->unique()->count() >= 2;
    }

    /**
     * A new round. Each question has the passage and the scriptures to choose from, in a new order every time.
     *
     * @return array{token: string, questions: array<int, array{text: string, options: array<int, array{key: string, label: string}>}>}
     */
    public static function start(): array
    {
        $pool = static::pool();
        $kinds = $pool->pluck('kind')->unique()->values();

        // Take passages turn by turn from each scripture, so every scripture appears about equally often.
        $queues = $pool->groupBy('kind')->map(fn ($g) => $g->shuffle()->values())->shuffle();
        $picked = collect();
        $want = min(static::questionsPerRound(), $pool->count());
        while ($picked->count() < $want && $queues->flatten()->isNotEmpty()) {
            foreach ($queues as $kind => $queue) {
                if ($picked->count() >= $want) {
                    break;
                }
                if ($queue->isNotEmpty()) {
                    $picked->push($queue->shift());
                }
            }
        }
        $picked = $picked->shuffle()->values();

        $sealed = [];
        $visible = [];
        foreach ($picked as $passage) {
            $order = $kinds->shuffle()->values();
            $sealed[] = ['id' => $passage->id, 'kinds' => $order->all(), 'answer' => $order->search($passage->kind)];
            $visible[] = [
                'text' => $passage->text,
                'options' => $order->map(fn ($kind, $i) => ['key' => chr(65 + $i), 'label' => \Illuminate\Support\Str::ucfirst(static::kindName($kind))])->all(),
            ];
        }

        return ['token' => Crypt::encryptString(json_encode(['for' => static::NAME, 'q' => $sealed, 'until' => now()->addHours(6)->timestamp])), 'questions' => $visible];
    }

    public static function kindName(string $kind): string
    {
        return Words::get('scripture_kind_'.$kind, ScripturePassage::KINDS[$kind] ?? $kind);
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

    /** Check one answer and say what the passage really was. Null when the token or the numbers are wrong. */
    public static function answer(?string $token, int $question, int $choice): ?array
    {
        $data = static::open($token);
        $q = $data['q'][$question] ?? null;
        if (! $q || ! isset($q['kinds'][$choice])) {
            return null;
        }

        $passage = ScripturePassage::query()->find($q['id']);
        if (! $passage) {
            return null;
        }

        $correct = $choice === $q['answer'];
        $passage->increment('times_shown');
        if ($correct) {
            $passage->increment('times_correct');
        }

        return [
            'correct' => $correct,
            'answer' => $q['answer'],
            'answer_key' => chr(65 + $q['answer']),
            'kind' => $passage->kind,
            'kind_name' => static::kindName($passage->kind),
            'reference' => $passage->reference,
            'source' => $passage->source_note,
            'note' => $passage->note,
        ];
    }

    /**
     * The end of a round. The score is worked out here from the sealed token, and kept without any personal detail:
     * only the score and which scriptures were mixed up.
     *
     * @param  array<int, int|null>  $choices
     */
    public static function finish(?string $token, array $choices): ?array
    {
        $data = static::open($token);
        if (! $data) {
            return null;
        }

        $score = 0;
        $mistakes = [];
        $passages = ScripturePassage::query()->whereIn('id', collect($data['q'])->pluck('id'))->get()->keyBy('id');

        foreach ($data['q'] as $i => $q) {
            $choice = $choices[$i] ?? null;
            if ($choice === null || ! isset($q['kinds'][$choice])) {
                continue;
            }
            if ($choice === $q['answer']) {
                $score++;
            } else {
                $kind = $passages[$q['id']]->kind ?? 'unknown';
                $mistakes[$kind] = ($mistakes[$kind] ?? 0) + 1;
            }
        }

        $total = count($data['q']);
        QuizAttempt::create(['quiz' => static::NAME, 'score' => $score, 'total' => $total, 'by_kind' => $mistakes]);

        return [
            'score' => $score,
            'total' => $total,
            'mistakes' => $mistakes,
            'kind_names' => collect(array_keys($mistakes))->mapWithKeys(fn ($k) => [$k => static::kindName($k)])->all(),
        ];
    }
}
