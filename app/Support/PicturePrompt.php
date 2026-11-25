<?php

namespace App\Support;

use App\Models\Article;
use App\Support\StoryMaker\Rules;

/**
 * The picture of every tab of an article: when an article is made by the AI import, one picture row is made for each tab (each "## " heading),
 * in the order of the tabs, with a prompt for the picture (written by the AI, or made from the heading) and an alt text. The picture itself is added later
 * (the row waits). The prompt that is copied into an image maker is the scene of the tab plus the look of the pictures plus the picture rules
 * (4:3, prophets and angels only as light, the faces of the companions as light, and so on). The rules are in resources/prompts/picture-rules.txt,
 * and the setting picture_prompt_rules can replace them.
 */
class PicturePrompt
{
    /** The rules for every picture prompt (one "- " line for each rule). */
    public static function rules(): string
    {
        $saved = trim((string) \App\Models\Setting::get('picture_prompt_rules'));

        return $saved !== '' ? $saved : trim((string) file_get_contents(resource_path('prompts/picture-rules.txt')));
    }

    /** The look of the pictures: the same line as the stories use (Story maker > rules), so the pictures of the site match. */
    public static function style(): string
    {
        return Rules::style();
    }

    /** The whole prompt to copy into an image maker: the scene, the look and the rules. */
    public static function full(string $scene): string
    {
        $scene = trim($scene);

        return ($scene !== '' ? $scene."\n\n" : '').'Look of the picture: '.static::style()."\n\nRules for this picture:\n".static::rules();
    }

    /** When there is no scene from the AI: a plain one made from the title of the article and the heading of the tab. */
    public static function fallbackScene(string $title, string $heading): string
    {
        $heading = trim(preg_replace('/^\d+\s*[.)]\s*/u', '', $heading));

        return 'A simple, calm picture for the part "'.$heading.'" of an article about "'.trim($title).'".';
    }

    /** What the AI is told to write, and the shape of the block it writes: used by the prompts of the AI import. */
    public static function field(): string
    {
        return "PICTURES:\nTAB 1: (the exact words of the first \"## \" heading)\nPROMPT: (the picture for this tab: one to three plain sentences, one clear scene, following the picture rules)\nALT: (one plain sentence that describes the picture, for people who cannot see it)\nTAB 2: (the exact words of the second \"## \" heading)\nPROMPT: ...\nALT: ...\n(one TAB block for every \"## \" heading, in the same order. Do not write the look or the size: the website adds them)\n";
    }

    /** The instructions about the pictures, with the rules, for the prompts of the AI import. */
    public static function instructions(): string
    {
        return "PICTURES FOR THE TABS\nThe website makes one place for a picture under every \"## \" heading (every tab). For each one, write a PROMPT for an image maker, as the shape at the end shows. Rules for every picture:\n".static::rules()."\n";
    }

    /** Puts the two blocks into a prompt text. */
    public static function fill(string $text): string
    {
        return str_replace(['{{PICTURE_RULES}}', '{{PICTURES_FIELD}}'], [static::instructions(), static::field()], $text);
    }

    /**
     * Reads the PICTURES block of an answer: [tab number => ['prompt' => ..., 'alt' => ...]].
     *
     * @return array<int, array{prompt: string, alt: string}>
     */
    public static function read(string $block): array
    {
        $out = [];
        $n = null;
        $key = null;
        foreach (explode("\n", str_replace("\r", '', $block)) as $line) {
            $plain = preg_replace('/^[\s*_]+|[\s*_]+$/u', '', $line);
            if (preg_match('/^TAB\s*(\d+)\b/iu', $plain, $m)) {
                $n = (int) $m[1];
                $out[$n] ??= ['prompt' => '', 'alt' => ''];
                $key = null;

                continue;
            }
            if ($n !== null && preg_match('/^(PROMPT|ALT)\s*:\s*(.*)$/iu', $plain, $m)) {
                $key = strtolower($m[1]);
                $out[$n][$key] = trim(ltrim($m[2], "*_ "));

                continue;
            }
            if ($n !== null && $key !== null && trim($line) !== '' && ! str_starts_with(trim($line), '(one TAB block')) {
                $out[$n][$key] = trim($out[$n][$key].' '.trim($line));
            }
        }

        return array_filter($out, fn ($p) => $p['prompt'] !== '' || $p['alt'] !== '');
    }

    /**
     * Makes a picture row for every tab of the article (its main "## " headings) that has none yet, in the order of the tabs. A row with a picture or with
     * text is never changed; a waiting row with no prompt gets one. Returns how many rows were made.
     *
     * @param  array<int, array{prompt?: string, alt?: string}>  $ai  what the AI wrote, by tab number (may be empty: the prompts are then made from the headings)
     */
    public static function sync(Article $article, array $ai = []): int
    {
        $locale = config('infofluxes.default_locale');
        $headings = ArticlePictures::headings($article->getTranslation('long_detail', $locale, false));
        if ($headings === []) {
            return 0;
        }
        $rows = $article->images()->get();
        $made = 0;
        $next = (int) $rows->max('sort_order');
        foreach ($headings as $n => $heading) {
            $scene = trim((string) ($ai[$n]['prompt'] ?? '')) ?: static::fallbackScene($article->mainTitle(), (string) $heading);
            $alt = trim((string) ($ai[$n]['alt'] ?? ''));
            $has = $rows->first(fn ($r) => (int) $r->section === (int) $n);
            if ($has) {
                if (blank($has->prompt)) {
                    $has->prompt = $scene;
                    $has->save();
                }

                continue;
            }
            $article->images()->create(['section' => $n, 'image' => null, 'prompt' => $scene, 'alt' => $alt !== '' ? [$locale => $alt] : null, 'sort_order' => ++$next]);
            $made++;
        }

        return $made;
    }
}
