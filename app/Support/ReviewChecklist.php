<?php

namespace App\Support;

/** The review checklist of an article written with AI help: the fixed lines (lang/en/review.php) and the points the AI and the system raised. */
class ReviewChecklist
{
    /** @return array<string, string> key => text */
    public static function standard(): array
    {
        return (array) __('review.items');
    }

    /** The "please check" points kept with the article, keyed by their number. @return array<string, string> */
    public static function points(?array $customData): array
    {
        $out = [];
        foreach (array_values((array) ($customData['please_check'] ?? [])) as $i => $text) {
            if (filled($text)) {
                $out[(string) $i] = (string) $text;
            }
        }

        return $out;
    }

    /**
     * What is still not ticked, in plain words. Empty when the review is finished.
     *
     * @return list<string>
     */
    public static function left(?array $customData): array
    {
        $review = (array) ($customData['review'] ?? []);
        $done = array_map('strval', (array) ($review['done'] ?? []));
        $pointsDone = array_map('strval', (array) ($review['points_done'] ?? []));

        $left = [];
        foreach (static::standard() as $key => $text) {
            if (! in_array((string) $key, $done, true)) {
                $left[] = $text;
            }
        }
        foreach (static::points($customData) as $i => $text) {
            if (! in_array((string) $i, $pointsDone, true)) {
                $left[] = $text;
            }
        }

        return $left;
    }
}
