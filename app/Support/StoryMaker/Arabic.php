<?php

namespace App\Support\StoryMaker;

/** Finds the lines of a slide that are Arabic text (a Quran verse or a saying), and not just a line that has the symbol ﷺ in it. */
class Arabic
{
    /** The symbol ﷺ (U+FDFA) is in the Arabic block of Unicode, but it is a salutation, not a quotation. */
    private const SALUTATION = "\u{FDFA}";

    /** Is this line mostly Arabic letters? (At least 3 of them, and at least 60% of all the letters.) */
    public static function isLine(string $line): bool
    {
        $line = str_replace(self::SALUTATION, '', $line);
        $arabic = preg_match_all('/\p{Arabic}/u', preg_replace('/\p{M}/u', '', $line));
        if ($arabic < 3) {
            return false;
        }
        $letters = preg_match_all('/\p{L}/u', $line);

        return $letters > 0 && $arabic / $letters >= 0.6;
    }
}
