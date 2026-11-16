<?php

namespace App\Support\StoryMaker;

use App\Models\Story;

/**
 * Which languages a story has, and whether each is shown on the site yet. Kept on the story itself (custom_data.translations).
 * A saved translation is HIDDEN until it is switched on. English is the story itself and is not listed here.
 */
class Translations
{
    public const STATUSES = ['hidden' => 'Saved, not shown yet', 'on' => 'Shown on the site'];

    /** @return array<string, array{status: string, saved_at: string|null, run: int|null, keywords: list<string>}> */
    public static function all(Story $story): array
    {
        $out = [];
        foreach ((array) ($story->custom_data['translations'] ?? []) as $code => $row) {
            if (is_array($row)) {
                $out[(string) $code] = [
                    'status' => ($row['status'] ?? 'hidden') === 'on' ? 'on' : 'hidden',
                    'saved_at' => $row['saved_at'] ?? null,
                    'run' => $row['run'] ?? null,
                    'keywords' => (array) ($row['keywords'] ?? []),
                ];
            }
        }

        return $out;
    }

    public static function has(Story $story, string $code): bool
    {
        return isset(static::all($story)[$code]);
    }

    public static function status(Story $story, string $code): ?string
    {
        return static::all($story)[$code]['status'] ?? null;
    }

    /** The codes of the languages that are shown on the site. @return list<string> */
    public static function shown(Story $story): array
    {
        return array_keys(array_filter(static::all($story), fn ($r) => $r['status'] === 'on'));
    }

    public static function set(Story $story, string $code, string $status): void
    {
        $all = (array) ($story->custom_data['translations'] ?? []);
        if (! isset($all[$code]) || ! in_array($status, ['hidden', 'on'], true)) {
            return;
        }
        $all[$code]['status'] = $status;
        static::write($story, $all);
    }

    /** Remembers that a language was saved. A language that is already shown stays shown. */
    public static function saved(Story $story, string $code, ?int $runId, array $keywords = []): void
    {
        $all = (array) ($story->custom_data['translations'] ?? []);
        $all[$code] = [
            'status' => ($all[$code]['status'] ?? 'hidden') === 'on' ? 'on' : 'hidden',
            'saved_at' => now()->toDateTimeString(),
            'run' => $runId,
            'keywords' => array_values($keywords),
        ];
        static::write($story, $all);
    }

    public static function remove(Story $story, string $code): void
    {
        $all = (array) ($story->custom_data['translations'] ?? []);
        unset($all[$code]);
        static::write($story, $all);
    }

    private static function write(Story $story, array $all): void
    {
        $data = (array) $story->custom_data;
        $all === [] ? $data = array_diff_key($data, ['translations' => 1]) : $data['translations'] = $all;
        $story->custom_data = $data ?: null;
        $story->save();
    }
}
