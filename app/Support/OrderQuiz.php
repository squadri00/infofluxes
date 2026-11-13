<?php

namespace App\Support;

use App\Models\Label;
use App\Models\QuizAttempt;
use App\Models\QuranChapter;
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

/**
 * The test "Put the chapters in order": a set of Quran chapters in a mixed-up order, and the reader puts them back.
 * Easy: the chapters are in a row (for example 18 to 29) and the reader is told the range.
 * Hard: the chapters come from anywhere in the Quran.
 * The right order travels inside a sealed (encrypted) token that only this test accepts; the page never holds it.
 */
class OrderQuiz
{
    public const NAME = 'quran-order';

    public const LEVELS = ['easy', 'hard'];

    public static function enabled(): bool
    {
        return (string) Setting::get('order_on', '1') === '1';
    }

    /** How many chapters in one round (6 to 20). */
    public static function size(): int
    {
        return max(6, min(20, (int) Setting::get('order_size', 12)));
    }

    /** Can a round be made? It needs all 114 chapters. */
    public static function ready(): bool
    {
        return QuranChapter::query()->count() >= QuranChapter::COUNT;
    }

    /**
     * A new round.
     *
     * @return array{token: string, level: string, practice: bool, size: int, from: ?int, to: ?int, slots: array<int, ?int>, tiles: array<int, array{id: int, arabic: string, name: string, meaning: string}>}
     */
    public static function start(string $level, bool $practice = false): array
    {
        $level = in_array($level, self::LEVELS, true) ? $level : 'easy';
        $size = static::size();

        if ($level === 'easy') {
            $from = random_int(1, QuranChapter::COUNT - $size + 1);
            $numbers = range($from, $from + $size - 1);
        } else {
            $numbers = collect(range(1, QuranChapter::COUNT))->shuffle()->take($size)->sort()->values()->all();
            $from = null;
        }

        $chapters = QuranChapter::query()->whereIn('number', $numbers)->get()->keyBy('number');

        // The tiles, shuffled. A tile's id is only its place in the shuffled list: it says nothing about the real number.
        $shuffled = collect($numbers)->shuffle()->values();
        while ($shuffled->all() === $numbers) {
            $shuffled = $shuffled->shuffle()->values();     // never hand out the right order
        }

        $tiles = $shuffled->map(fn ($number, $i) => [
            'id' => $i,
            'arabic' => $chapters[$number]->arabic_name,
            'name' => $chapters[$number]->shown_name,
            'meaning' => $chapters[$number]->shown_meaning,
        ])->all();

        // For each slot (0 = first), which tile belongs there.
        $correct = collect($numbers)->map(fn ($number) => $shuffled->search($number))->all();

        $token = Crypt::encryptString(json_encode([
            'for' => static::NAME, 'level' => $level, 'practice' => $practice, 'numbers' => $numbers, 'correct' => $correct,
            'tiles' => $shuffled->all(), 'until' => now()->addHours(6)->timestamp,
        ]));

        return [
            'token' => $token,
            'level' => $level,
            'practice' => $practice,
            'size' => $size,
            'from' => $level === 'easy' ? $numbers[0] : null,
            'to' => $level === 'easy' ? $numbers[$size - 1] : null,
            // Easy: the slots carry the real numbers. Hard: the slots are only places 1 to size.
            'slots' => $level === 'easy' ? $numbers : array_fill(0, $size, null),
            'tiles' => $tiles,
        ];
    }

    private static function open(?string $token): ?array
    {
        try {
            $data = json_decode(Crypt::decryptString((string) $token), true);
        } catch (\Throwable) {
            return null;
        }

        return is_array($data) && ($data['for'] ?? null) === static::NAME && ($data['until'] ?? 0) >= now()->timestamp
            && is_array($data['numbers'] ?? null) && is_array($data['correct'] ?? null) ? $data : null;
    }

    /**
     * Check the reader's order. $placement has one entry per slot: the tile placed there, or null.
     * Null when the token or the placement is not valid.
     *
     * @param  array<int, int|null>  $placement
     */
    public static function check(?string $token, array $placement): ?array
    {
        $data = static::open($token);
        if (! $data || ! empty($data['practice'])) {
            return null;
        }

        $size = count($data['numbers']);
        $placement = array_values($placement);
        if (count($placement) !== $size) {
            return null;
        }

        $placed = [];
        foreach ($placement as $tile) {
            if ($tile !== null && (! is_int($tile) || $tile < 0 || $tile >= $size)) {
                return null;
            }
            if ($tile !== null) {
                $placed[] = $tile;
            }
        }
        if (count($placed) !== count(array_unique($placed))) {
            return null;                                   // a tile used twice
        }

        $chapters = QuranChapter::query()->whereIn('number', $data['numbers'])->get()->keyBy('number');
        $score = 0;
        $rows = [];
        foreach ($data['numbers'] as $slot => $number) {
            $ok = $placement[$slot] !== null && $placement[$slot] === $data['correct'][$slot];
            $score += $ok ? 1 : 0;
            $mine = $placement[$slot] === null ? null : ($chapters[$data['tiles'][$placement[$slot]]] ?? null);

            $rows[] = [
                'slot' => $slot,
                'ok' => $ok,
                'number' => $number,
                'arabic' => $chapters[$number]->arabic_name,
                'name' => $chapters[$number]->shown_name,
                'meaning' => $chapters[$number]->shown_meaning,
                'verses' => $chapters[$number]->verses,
                'yours' => $mine ? ['arabic' => $mine->arabic_name, 'name' => $mine->shown_name, 'number' => $mine->number] : null,
            ];
        }

        // Each round is counted once, however many times the same token is checked.
        if (Cache::add('quiz.order.done.'.sha1((string) $token), 1, now()->addHours(7))) {
            QuizAttempt::create(['quiz' => static::NAME.'-'.$data['level'], 'score' => $score, 'total' => $size, 'by_kind' => $size - $score > 0 ? ['wrong_places' => $size - $score] : []]);
        }

        return ['score' => $score, 'total' => $size, 'level' => $data['level'], 'perfect' => $score === $size, 'rows' => $rows];
    }

    /**
     * PRACTICE only: is this title the right one for this place? One title at a time, nothing is scored or kept.
     * A scored round's token is refused here, otherwise the answer could be found out one place at a time.
     *
     * @return array{ok: bool, chapter: ?array{number: int, arabic: string, name: string, meaning: string, verses: int}}|null
     */
    public static function tryTile(?string $token, int $slot, int $tile): ?array
    {
        $data = static::open($token);
        if (! $data || empty($data['practice'])) {
            return null;
        }

        $size = count($data['numbers']);
        if ($slot < 0 || $slot >= $size || $tile < 0 || $tile >= $size) {
            return null;
        }

        $ok = $data['correct'][$slot] === $tile;
        $chapter = null;
        if ($ok) {
            $c = QuranChapter::query()->where('number', $data['numbers'][$slot])->first();
            $chapter = $c ? ['number' => $c->number, 'arabic' => $c->arabic_name, 'name' => $c->shown_name, 'meaning' => $c->shown_meaning, 'verses' => $c->verses] : null;
        }

        return ['ok' => $ok, 'chapter' => $chapter];
    }

    /** The label of a level, for the chooser. */
    public static function levelName(string $level): string
    {
        return Words::get('order_'.$level, ucfirst($level));
    }
}
