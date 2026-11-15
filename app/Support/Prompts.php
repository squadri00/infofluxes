<?php

namespace App\Support;

use App\Models\AiPrompt;
use App\Models\Category;

/** The prompts the owner copies into an AI chat. The words live in resources/prompts, one file for each kind. */
class Prompts
{
    public const KINDS = [
        'research' => 'A researched article (with sections and sources)',
        'article' => 'A full article',
        'fact' => 'A short fact',
        'categories' => 'A list of new categories',
    ];

    /** The prompt for one kind, with the subject and the present categories filled in. */
    public static function make(string $kind, string $topic): string
    {
        abort_unless(array_key_exists($kind, self::KINDS), 404);
        $file = resource_path('prompts/'.$kind.'.txt');
        $text = (string) file_get_contents($file);
        $topic = trim($topic) !== '' ? trim(preg_replace('/\s+/', ' ', $topic)) : '(write your subject here)';

        return str_replace(['{{TOPIC}}', '{{CATEGORIES}}'], [$topic, static::categoryList()], $text);
    }

    public const DEPTHS = [
        'short' => 'Short: the main points only, at least 3 good sources.',
        'normal' => 'Normal: the main points with the evidence and the different views, at least 6 good sources.',
        'detailed' => 'Detailed: full evidence, every important view and its reasons, history and limits, at least 10 good sources.',
    ];

    /** Above this many words the answer is asked for in parts, because an AI loses the rules in one very long answer. */
    public const PARTS_ABOVE = 2500;

    /**
     * The prompt for a researched article: master rules + specialist prompt + the form values + the answer shape.
     *
     * @param  array{topic?: string, category_id?: int|null, specialist?: string|null, words?: int|null, depth?: string|null, comments?: string|null, entry?: string|null}  $in
     */
    public static function research(array $in): string
    {
        $text = (string) file_get_contents(resource_path('prompts/research.txt'));
        $topic = trim((string) ($in['topic'] ?? '')) !== '' ? trim(preg_replace('/\s+/', ' ', $in['topic'])) : '(write your subject here)';
        $category = ! empty($in['category_id']) ? (Category::choices()[(int) $in['category_id']] ?? null) : null;
        $words = max(300, min(8000, (int) ($in['words'] ?? 1500)));
        $depth = self::DEPTHS[$in['depth'] ?? 'normal'] ?? self::DEPTHS['normal'];
        $comments = trim(preg_replace('/[ \t]+/', ' ', (string) ($in['comments'] ?? '')));
        if (filled($in['entry'] ?? null)) {
            $comments = trim('This text is for the planned index subject "'.$in['entry'].'". Write about exactly this subject and do not widen it. '.$comments);
        }
        $parts = '';
        if ($words > self::PARTS_ABOVE) {
            $parts = "\nThis is a long article. Do it in parts so that no rule is lost: first answer ONLY with the outline (the headings and one line for each), and wait. Then I will say \"write part 1\", \"write part 2\" and so on, and you write those headings in full. When the last part is done I will ask for the whole answer in the shape below.\n";
        }

        return str_replace(
            ['{{RULES}}', '{{TOPIC}}', '{{CATEGORY}}', '{{WORDS}}', '{{DEPTH}}', '{{COMMENTS}}', '{{PARTS}}'],
            [AiPrompt::combined($in['specialist'] ?? null), $topic, $category ?? '(choose a category)', $words, $depth,
                $comments !== '' ? "Notes from the contributor (follow them when they fit the rules): ".$comments."\n" : '', $parts],
            $text,
        );
    }

    /** One line for each category: "Shows > Popular Shows (C-2)". Long lists are cut, with a note. */
    public static function categoryList(int $max = 300): string
    {
        $choices = Category::leafChoices();   // an article is only ever placed in a last-level category
        if ($choices === []) {
            return '(there are no categories yet)';
        }
        $lines = array_slice(array_values($choices), 0, $max);
        if (count($choices) > $max) {
            $lines[] = '(and '.(count($choices) - $max).' more)';
        }

        return implode("\n", $lines);
    }
}
