<?php

namespace App\Support\StoryMaker;

use App\Models\Category;

/** Makes the prompt for step 1 of the Story maker: the story in English. */
class Prompt
{
    public const MIN_SLIDES = 5;

    public const MAX_SLIDES = 10;

    /**
     * @param  array{topic?: string, category_id?: int|null, slides?: int|null, notes?: string|null, style?: string|null}  $in
     */
    public static function english(array $in): string
    {
        $text = (string) file_get_contents(resource_path('prompts/story/english.txt'));
        $topic = trim((string) ($in['topic'] ?? '')) !== '' ? trim(preg_replace('/\s+/', ' ', $in['topic'])) : '(write your subject here)';
        $category = ! empty($in['category_id']) ? (Category::choices()[(int) $in['category_id']] ?? null) : null;
        $slides = max(self::MIN_SLIDES, min(self::MAX_SLIDES, (int) ($in['slides'] ?? 8)));
        $style = trim(preg_replace('/\s+/', ' ', (string) ($in['style'] ?? ''))) ?: Rules::style();
        $notes = trim(preg_replace('/[ \t]+/', ' ', (string) ($in['notes'] ?? '')));

        return str_replace(
            ['{{RULES}}', '{{TOPIC}}', '{{CATEGORY}}', '{{SLIDES}}', '{{STYLE}}', '{{NOTES}}'],
            [Rules::master(), $topic, $category ?? '(choose a category)', $slides, $style, $notes !== '' ? 'Notes from the person who asked (follow them when they fit the rules): '.$notes."\n" : ''],
            $text,
        );
    }
}
