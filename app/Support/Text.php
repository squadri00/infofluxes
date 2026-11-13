<?php

namespace App\Support;

use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class Text
{
    /** Does this text come from the web editor (it holds formatting tags) rather than from a plain box? */
    public static function isFormatted(?string $text): bool
    {
        $text = (string) $text;

        // A block tag, or an inline tag that is closed again (a lone "<b>" typed in a plain box stays plain text).
        return (bool) (preg_match('/<(?:p|h[1-6]|ul|ol|li|blockquote|br)(?:\s[^>]*)?\/?>/i', $text)
            || preg_match('/<(strong|em|b|i|u|s|a)(?:\s[^>]*)?>.*?<\/(?:strong|em|b|i|u|s|a)>/is', $text));
    }

    /**
     * Text from the web editor made safe to show: no scripts, no unsafe links (the clean-up of Laravel), and of every
     * "style" only the alignment and the colours stay. A style could otherwise cover the page or load a picture from elsewhere.
     */
    public static function clean(?string $html): string
    {
        $html = Str::sanitizeHtml((string) $html);

        return preg_replace_callback('/\sstyle\s*=\s*(?:"([^"]*)"|\'([^\']*)\')/i', function (array $m) {
            $keep = [];
            foreach (explode(';', html_entity_decode($m[1] !== '' ? $m[1] : ($m[2] ?? ''), ENT_QUOTES | ENT_HTML5)) as $rule) {
                if (! str_contains($rule, ':')) {
                    continue;
                }
                [$name, $value] = array_map('trim', explode(':', $rule, 2));
                $name = strtolower($name);
                $color = '(?:#[0-9a-f]{3,8}|rgba?\(\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*\d{1,3}\s*(?:,\s*[0-9.]+\s*)?\)|[a-z]{3,20})';
                if (($name === 'text-align' && preg_match('/^(left|right|center|justify)$/i', $value))
                    || (in_array($name, ['color', 'background-color'], true) && preg_match('/^'.$color.'$/i', $value) && ! preg_match('/^(?:url|expression|javascript)/i', $value))) {
                    $keep[] = $name.': '.$value;
                }
            }

            return $keep === [] ? '' : ' style="'.e(implode('; ', $keep)).'"';
        }, $html) ?? $html;
    }

    /** The words only: no tags, one space between words. For a short line, a search or a description. */
    public static function plain(?string $text): string
    {
        $text = preg_replace('/<\/(?:p|h[1-6]|li|blockquote)>|<br\s*\/?>/i', ' ', (string) $text);

        return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags((string) $text), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
    }

    /**
     * Turns plain text typed in admin into safe paragraphs.
     * A blank line starts a new paragraph, a single line break stays a line break.
     * Everything is escaped first, so nothing typed in admin can inject code into the page.
     */
    public static function paragraphs(?string $text): HtmlString
    {
        $text = trim((string) $text);
        if ($text === '') {
            return new HtmlString('');
        }

        // Text written with the web editor is formatted text (HTML). It is cleaned, so only the simple formatting stays.
        if (static::isFormatted($text)) {
            return new HtmlString(Notes::finish(static::clean(Notes::prepare($text))));
        }

        $html = collect(preg_split('/\R{2,}/', $text))
            ->map(fn (string $p) => '<p>'.nl2br(e(trim($p))).'</p>')
            ->implode('');

        return new HtmlString($html);
    }
}
