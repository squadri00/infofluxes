<?php

namespace App\Support\History;

/** Shows what changed between two texts: removed words struck through, added words underlined. Safe HTML (everything is escaped). */
class TextDiff
{
    /** Above this many word pairs the exact comparison is too heavy, and the two texts are shown side by side instead. */
    private const MAX_CELLS = 1_500_000;

    /** HTML or text, as plain readable text: paragraphs and list items start on their own line. */
    public static function plain(?string $value): string
    {
        // A paragraph or a heading ends with a blank line; a list item, a table row or a line break ends with a new line.
        $text = preg_replace(['~</(p|h[1-6]|blockquote|div)>~i', '~</(li|tr|cite)>|<br\s*/?>~i'], ["\n\n", "\n"], (string) $value);
        $text = html_entity_decode(strip_tags((string) $text), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace("/[ \t]+/u", ' ', preg_replace("/\n{3,}/", "\n\n", $text)));
    }

    /** The two texts compared, as HTML with <del> and <ins>; or null when they are too long to compare word by word. */
    public static function inline(string $old, string $new): ?string
    {
        $a = static::tokens($old);
        $b = static::tokens($new);
        $n = count($a);
        $m = count($b);
        if ($n * $m > self::MAX_CELLS) {
            return null;
        }

        // The longest common run of words, by the usual table.
        $len = array_fill(0, $n + 1, array_fill(0, $m + 1, 0));
        for ($i = $n - 1; $i >= 0; $i--) {
            for ($j = $m - 1; $j >= 0; $j--) {
                $len[$i][$j] = $a[$i] === $b[$j] ? $len[$i + 1][$j + 1] + 1 : max($len[$i + 1][$j], $len[$i][$j + 1]);
            }
        }

        $out = '';
        $i = $j = 0;
        while ($i < $n && $j < $m) {
            if ($a[$i] === $b[$j]) {
                $out .= e($a[$i]);
                $i++;
                $j++;
            } elseif ($len[$i + 1][$j] >= $len[$i][$j + 1]) {
                $out .= '<del>'.e($a[$i]).'</del>';
                $i++;
            } else {
                $out .= '<ins>'.e($b[$j]).'</ins>';
                $j++;
            }
        }
        for (; $i < $n; $i++) {
            $out .= '<del>'.e($a[$i]).'</del>';
        }
        for (; $j < $m; $j++) {
            $out .= '<ins>'.e($b[$j]).'</ins>';
        }

        return str_replace("\n", '<br>', preg_replace('~</(del|ins)>(\s*)<\1>~', '$2', $out));
    }

    /** Words, and the spaces and line breaks between them, each as its own piece. @return list<string> */
    private static function tokens(string $text): array
    {
        return preg_split('/(\s+)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [];
    }
}
