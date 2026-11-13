<?php

namespace App\Support;

/**
 * Makes written text safe to show. Only a short list of tags and attributes survives.
 * Everything else (scripts, styles, event handlers, strange links) is removed. Used for every long text.
 */
class Html
{
    private const TAGS = ['p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'ul', 'ol', 'li', 'a', 'h2', 'h3', 'h4', 'blockquote', 'hr', 'code', 'pre', 'sub', 'sup',
        'table', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td', 'caption'];

    public static function clean(?string $html): string
    {
        $html = trim((string) $html);
        if ($html === '') {
            return '';
        }

        $doc = new \DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"><div id="__root">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $doc->getElementById('__root');
        if (! $root) {
            return e(strip_tags($html));
        }
        static::walk($root);

        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= $doc->saveHTML($child);
        }

        return $out;
    }

    /** Plain text from a form box becomes safe paragraphs: a blank line starts a new paragraph, a line break stays a line break. */
    public static function fromPlain(?string $text): string
    {
        $text = trim(str_replace(["\r\n", "\r"], "\n", (string) $text));
        if ($text === '') {
            return '';
        }
        $paragraphs = preg_split("/\n{2,}/", $text);

        return implode('', array_map(fn ($p) => '<p>'.nl2br(e(trim($p))).'</p>', $paragraphs));
    }

    private static function walk(\DOMNode $node): void
    {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof \DOMComment) {
                $node->removeChild($child);

                continue;
            }
            if (! $child instanceof \DOMElement) {
                continue;
            }
            $name = strtolower($child->tagName);
            if (! in_array($name, self::TAGS, true)) {
                // Scripts and styles disappear with their content; other unknown tags are replaced by their text.
                if (in_array($name, ['script', 'style', 'iframe', 'object', 'embed', 'form', 'textarea', 'select', 'svg', 'math'], true)) {
                    $node->removeChild($child);
                } else {
                    static::walk($child);
                    while ($child->firstChild) {
                        $node->insertBefore($child->firstChild, $child);
                    }
                    $node->removeChild($child);
                }

                continue;
            }
            foreach (iterator_to_array($child->attributes) as $attr) {
                $keep = ($name === 'a' && $attr->name === 'href' && static::safeHref($attr->value))
                    // a cell that spans several columns or rows: whole numbers only
                    || (in_array($name, ['td', 'th'], true) && in_array($attr->name, ['colspan', 'rowspan'], true) && preg_match('/^[1-9][0-9]?$/', trim($attr->value)));
                if (! $keep) {
                    $child->removeAttributeNode($attr);
                }
            }
            if ($name === 'a') {
                $child->setAttribute('rel', 'nofollow noopener');
            }
            static::walk($child);
        }
    }

    private static function safeHref(string $href): bool
    {
        $href = trim($href);

        return (bool) preg_match('~^(https?://|mailto:|/(?!/)|#)~i', $href);
    }
}
