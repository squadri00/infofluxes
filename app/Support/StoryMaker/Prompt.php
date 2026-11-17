<?php

namespace App\Support\StoryMaker;

use App\Models\Category;

/** Makes the prompt for step 1 of the Story maker: the story in English. */
class Prompt
{
    /** There is no rule about how many slides a story has. These two numbers only keep a typing mistake out of the prompt. */
    public const MIN_SLIDES = 1;

    public const MAX_SLIDES = 60;

    /**
     * @param  array{topic?: string, category_id?: int|null, slides?: int|null, notes?: string|null, style?: string|null, length?: string|null}  $in
     */
    public static function english(array $in): string
    {
        $text = (string) file_get_contents(resource_path('prompts/story/english.txt'));
        $topic = trim((string) ($in['topic'] ?? '')) !== '' ? trim(preg_replace('/\s+/', ' ', $in['topic'])) : '(write your subject here)';
        $category = ! empty($in['category_id']) ? (Category::choices()[(int) $in['category_id']] ?? null) : null;
        $slides = max(self::MIN_SLIDES, min(self::MAX_SLIDES, (int) ($in['slides'] ?? 8)));
        $style = trim(preg_replace('/\s+/', ' ', (string) ($in['style'] ?? ''))) ?: Rules::style();
        $notes = trim(preg_replace('/[ \t]+/', ' ', (string) ($in['notes'] ?? '')));

        // How long each slide is: three levels, set on the Story maker screen (the numbers can be changed by the super user).
        $length = Rules::length($in['length'] ?? null);
        $words = $length['min'].' to '.$length['max'];
        $sentences = Rules::sentenceRange($length['min'], $length['max']);
        $lengthText = 'the BODY of every slide has about '.$words.' words ('.$sentences.' short sentences). This number is the rule for the length of a slide, even if another number is written above.';
        if ($length['extra']) {
            $lengthText .= ' Besides the story itself, give some extra information: when it helps, end the BODY with ONE short "good to know" sentence (for example the place, the time, or what a hard word means). The extra sentence is as simple as the rest. Put a reference in brackets only if it is really needed. Never invent a fact for it; if you are not sure, leave it out.';
        }

        $rules = str_replace(['{{BODY_WORDS}}', '{{BODY_SENTENCES}}'], [$words, $sentences], Rules::master());

        return str_replace(
            ['{{RULES}}', '{{TOPIC}}', '{{CATEGORY}}', '{{SLIDES}}', '{{LENGTH}}', '{{STYLE}}', '{{NOTES}}', '{{BODY_WORDS}}', '{{BODY_SENTENCES}}'],
            [$rules, $topic, $category ?? '(choose a category)', $slides, $lengthText, $style, $notes !== '' ? 'Notes from the person who asked (follow them when they fit the rules): '.$notes."\n" : '', $words, $sentences],
            $text,
        );
    }
}
