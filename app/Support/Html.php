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

    /**
     * Text with light marks becomes safe HTML: "## " a main heading, "### " a sub-heading, "- " a list line,
     * a blank line a new paragraph. Everything else is plain text. No other mark is read, and no HTML is accepted.
     */
    public static function fromMarked(?string $text): string
    {
        $lines = explode("\n", trim(str_replace(["\r\n", "\r"], "\n", (string) $text)));
        $out = '';
        $para = [];
        $list = [];
        $flush = function () use (&$out, &$para, &$list) {
            if ($para) {
                $out .= '<p>'.implode('<br>', array_map(fn ($l) => e($l), $para)).'</p>';
                $para = [];
            }
            if ($list) {
                $out .= '<ul>'.implode('', array_map(fn ($l) => '<li>'.e($l).'</li>', $list)).'</ul>';
                $list = [];
            }
        };
        foreach ($lines as $line) {
            $line = rtrim($line);
            if (trim($line) === '') {
                $flush();
            } elseif (preg_match('/^\s*###\s+(.+)$/u', $line, $m)) {
                $flush();
                $out .= '<h3>'.e(trim($m[1])).'</h3>';
            } elseif (preg_match('/^\s*##\s+(.+)$/u', $line, $m)) {
                $flush();
                $out .= '<h2>'.e(trim($m[1])).'</h2>';
            } elseif (preg_match('/^\s*[-•]\s+(.+)$/u', $line, $m)) {
                if ($para) {
                    $flush();
                }
                $list[] = trim($m[1]);
            } else {
                if ($list) {
                    $flush();
                }
                $para[] = trim($line);
            }
        }
        $flush();

        return $out;
    }

    /** The main headings (## ) of a marked text, in order. */
    public static function outline(?string $text): array
    {
        preg_match_all('/^\s*(#{2,3})\s+(.+)$/mu', str_replace("\r", '', (string) $text), $m, PREG_SET_ORDER);

        return array_map(fn ($h) => ['level' => strlen($h[1]), 'title' => trim($h[2])], $m);
    }

    /**
     * Wraps each main heading (h2) and what follows it into a drop-down section. The words before the first heading stay open.
     * With fewer than two main headings nothing changes. The first section starts open.
     */
    public static function accordion(string $html): string
    {
        if (preg_match_all('/<h2>/', $html) < 2) {
            return $html;
        }
        $parts = preg_split('/(?=<h2>)/', $html, -1, PREG_SPLIT_NO_EMPTY);
        $out = '';
        $first = true;
        foreach ($parts as $part) {
            if (! preg_match('#^<h2>(.*?)</h2>(.*)$#s', $part, $m)) {
                $out .= $part;

                continue;
            }
            $out .= '<details class="acc"'.($first ? ' open' : '').'><summary class="acc__title">'.$m[1].'</summary><div class="acc__body">'.$m[2].'</div></details>';
            $first = false;
        }

        return $out;
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
