<?php

namespace App\Support;

use App\Models\Category;

/** The prompts the owner copies into an AI chat. The words live in resources/prompts, one file for each kind. */
class Prompts
{
    public const KINDS = [
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

    /** One line for each category: "Shows > Popular Shows (C-2)". Long lists are cut, with a note. */
    public static function categoryList(int $max = 300): string
    {
        $choices = Category::choices();
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
