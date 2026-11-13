<?php

namespace App\Support;

/** The words of the tests ("Challenge yourself") come from lang/<locale>/challenge.php, never from the code. */
class Words
{
    public static function get(string $key, ?string $default = null): string
    {
        $line = 'challenge.'.$key;
        $text = __($line);

        return $text !== $line ? (string) $text : ($default ?? $key);
    }
}
