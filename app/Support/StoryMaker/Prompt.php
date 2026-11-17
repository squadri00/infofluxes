<?php

namespace App\Support\StoryMaker;

use App\Models\Category;
use App\Models\Story;

/** Makes the prompt for step 1 of the Story maker: the story in English (or the rewrite of a story that is already saved). */
class Prompt
{
    /** There is no rule about how many slides a story has. These two numbers only keep a typing mistake out of the prompt. */
    public const MIN_SLIDES = 1;

    public const MAX_SLIDES = 60;

    /** When the AI decides the length and the number of slides ("open flow"). */
    public const OPEN_SLIDES = 'as many slides as the story needs, closing slides included (usually 6 to 14). You decide: let the story set the number, not a count.';

    public const OPEN_WORDS = '50 to 130';

    public const OPEN_SENTENCES = '4 to 12';

    /**
     * @param  array{topic?: string, category_id?: int|null, slides?: int|null, notes?: string|null, style?: string|null, length?: string|null}  $in
     */
    public static function english(array $in): string
    {
        $text = (string) file_get_contents(resource_path('prompts/story/english.txt'));
        $topic = trim((string) ($in['topic'] ?? '')) !== '' ? trim(preg_replace('/\s+/', ' ', $in['topic'])) : '(write your subject here)';
        $category = ! empty($in['category_id']) ? (Category::choices()[(int) $in['category_id']] ?? null) : null;
        $open = ! empty($in['open']);
        $slides = $open ? self::OPEN_SLIDES : 'exactly '.max(self::MIN_SLIDES, min(self::MAX_SLIDES, (int) ($in['slides'] ?? 8))).' slides in all, closing slides included.';
        $style = trim(preg_replace('/\s+/', ' ', (string) ($in['style'] ?? ''))) ?: Rules::style();
        $notes = trim(preg_replace('/[ \t]+/', ' ', (string) ($in['notes'] ?? '')));
        [$words, $sentences, $lengthText] = static::lengthParts($in['length'] ?? null, $open);
        $rules = static::rules($words, $sentences, $open);

        return str_replace(
            ['{{RULES}}', '{{TOPIC}}', '{{CATEGORY}}', '{{SLIDES}}', '{{LENGTH}}', '{{STYLE}}', '{{NOTES}}', '{{BODY_WORDS}}', '{{BODY_SENTENCES}}'],
            [$rules, $topic, $category ?? '(choose a category)', $slides, $lengthText, $style, static::notesLine($notes), $words, $sentences],
            $text,
        );
    }

    /**
     * The prompt that asks the AI to tell a story that is already saved (a draft) again, as a connected story in a narrator's voice.
     * The text of the story as it is now goes inside the prompt; the chapters and slides stay the same, so the pictures stay on their slides.
     *
     * @param  array{notes?: string|null, style?: string|null, length?: string|null}  $in
     */
    public static function rewrite(Story $story, array $in): string
    {
        $text = (string) file_get_contents(resource_path('prompts/story/rewrite.txt'));
        $story->loadMissing('chapters.slides', 'category');
        $style = trim(preg_replace('/\s+/', ' ', (string) ($in['style'] ?? ''))) ?: Rules::style();
        $notes = trim(preg_replace('/[ \t]+/', ' ', (string) ($in['notes'] ?? '')));
        [$words, $sentences, $lengthText] = static::lengthParts($in['length'] ?? null, ! empty($in['open']));
        $rules = static::rules($words, $sentences, false);
        $chapters = $story->chapters->filter(fn ($c) => $c->slides->isNotEmpty());
        $category = $story->category ? ($story->category->mainName() ?? $story->category->name) : '(choose a category)';

        return str_replace(
            ['{{RULES}}', '{{TITLE}}', '{{CATEGORY}}', '{{SLIDES}}', '{{CHAPTERS}}', '{{LENGTH}}', '{{STYLE}}', '{{NOTES}}', '{{CURRENT}}', '{{BODY_WORDS}}', '{{BODY_SENTENCES}}'],
            [$rules, $story->mainTitle(), $category, $chapters->sum(fn ($c) => $c->slides->count()), $chapters->count(), $lengthText, $style, static::notesLine($notes), static::currentText($story), $words, $sentences],
            $text,
        );
    }

    /** The saved story in the same shape as the answer of the AI (the English text), so it can be given back to the AI to tell it again. */
    public static function currentText(Story $story): string
    {
        $locale = config('infofluxes.default_locale');
        $story->loadMissing('chapters.slides');
        $out = 'TITLE: '.$story->mainTitle()."\n".'SHORT: '.trim(preg_replace('/\s+/', ' ', strip_tags((string) $story->getTranslation('short_detail', $locale, false))))."\n";
        foreach ($story->chapters as $chapter) {
            if ($chapter->slides->isEmpty()) {
                continue;
            }
            $out .= 'CHAPTER: '.$chapter->getTranslation('title', $locale, false)."\n";
            foreach ($chapter->slides as $slide) {
                $out .= "SLIDE\nHEADING: ".$slide->getTranslation('heading', $locale, false)."\nBODY: ".trim((string) $slide->getTranslation('body', $locale, false))."\n"
                    .'PICTURE: '.(string) ($slide->custom_data['picture'] ?? '')."\nALT: ".(string) $slide->getTranslation('image_alt', $locale, false)."\n";
            }
        }

        return trim($out);
    }

    /** How long each slide is: the words, the sentences and the sentence of the prompt. @return array{0: string, 1: string, 2: string} */
    private static function lengthParts(?string $code, bool $open = false): array
    {
        if ($open) {
            return [self::OPEN_WORDS, self::OPEN_SENTENCES, 'you decide how long each slide is: as long as its scene needs, usually '.self::OPEN_WORDS.' words ('.self::OPEN_SENTENCES.' short sentences, joined into one flowing paragraph). The flow of the story is more important than the number of words.'];
        }
        $length = Rules::length($code);
        $words = $length['min'].' to '.$length['max'];
        $sentences = Rules::sentenceRange($length['min'], $length['max']);
        $text = 'the BODY of every slide has about '.$words.' words ('.$sentences.' short sentences). This number is the rule for the length of a slide, even if another number is written above.';
        if ($length['extra']) {
            $text .= ' Besides the story itself, give some extra information: when it helps, end the BODY with ONE short "good to know" sentence (for example the place, the time, or what a hard word means). The extra sentence is as simple as the rest. Put a reference in brackets only if it is really needed. Never invent a fact for it; if you are not sure, leave it out.';
        }

        return [$words, $sentences, $text];
    }

    /** The rules with the numbers of the length filled in. When the AI decides the number of slides, the rule about the exact number is replaced. */
    private static function rules(string $words, string $sentences, bool $open): string
    {
        $rules = str_replace(['{{BODY_WORDS}}', '{{BODY_SENTENCES}}'], [$words, $sentences], Rules::master());

        return $open ? str_replace('The task below says how many slides the story has. Use exactly that number, closing slides included.', 'You decide how many slides the story needs, closing slides included. Let the story decide, not a number.', $rules) : $rules;
    }

    private static function notesLine(string $notes): string
    {
        return $notes !== '' ? 'Notes from the person who asked (follow them when they fit the rules): '.$notes."\n" : '';
    }
}
