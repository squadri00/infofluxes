<?php

namespace App\Support;

/**
 * Makes written text safe to show. Only a short list of tags and attributes survives.
 * Everything else (scripts, styles, event handlers, strange links) is removed. Used for every long text.
 */
class Html
{
    private const TAGS = ['p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'ul', 'ol', 'li', 'a', 'h2', 'h3', 'h4', 'blockquote', 'hr', 'code', 'pre', 'sub', 'sup',
        'table', 'thead', 'tbody', 'tfoot', 'tr', 'th', 'td', 'caption', 'cite',
        // from the editor: highlight, small print, a folding box, a coloured word, a picture inside the text
        'mark', 'small', 'details', 'summary', 'span', 'img'];

    /** The only class names that survive (used by the verse and hadith blocks). */
    private const CLASSES = ['quote', 'quote--quran', 'quote--hadith', 'quote__ar', 'quote__text', 'quote__ref', 'lead'];

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
        static::restoreQuoteBlocks($doc, $root);

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
        $block = null;      // while inside [QURAN] ... [/QURAN] or [HADITH] ... [/HADITH]
        $blockLines = [];
        foreach ($lines as $line) {
            $line = rtrim($line);
            if ($block !== null) {
                if (preg_match('/^\s*\[\/'.$block.'\]\s*$/i', $line)) {
                    $out .= static::quoteBlock($block, $blockLines);
                    $block = null;
                    $blockLines = [];
                } else {
                    $blockLines[] = $line;
                }

                continue;
            }
            if (preg_match('/^\s*\[(QURAN|HADITH)\]\s*$/i', $line, $bm)) {
                $flush();
                $block = strtoupper($bm[1]);

                continue;
            }
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
        if ($block !== null) {
            $out .= static::quoteBlock($block, $blockLines);   // a block that was never closed is still kept
        }
        $flush();

        return $out;
    }

    /**
     * The article editor rewrites the text when someone saves the form and drops the classes of a verse or hadith block.
     * A plain quote that has the shape of one (Arabic line, English line, a reference like "Surah An-Nahl 16:36 · ..." or "Sahih al-Bukhari 7373 ...")
     * gets its classes back, so it is shown in the blue or green style again. A quote that does not have this shape is left as it is.
     */
    private static function restoreQuoteBlocks(\DOMDocument $doc, \DOMElement $root): void
    {
        $refPattern = '/^(?:Surah|Sura|Surat)\b.*\d+\s*:\s*\d+|^(?:Sahih|Sunan|Jami|Muwatta|Musnad)\b.*\d|\b(?:Bukhari|Muslim)\b.*\d/iu';
        foreach (iterator_to_array($root->getElementsByTagName('blockquote')) as $bq) {
            if (str_contains((string) $bq->getAttribute('class'), 'quote')) {
                continue;
            }
            $parts = [];
            foreach ($bq->childNodes as $child) {
                $text = trim(preg_replace('/\s+/u', ' ', $child->textContent));
                if ($text !== '' && ($child instanceof \DOMText || ($child instanceof \DOMElement && in_array(strtolower($child->tagName), ['p', 'cite', 'footer', 'em', 'i', 'small'], true)))) {
                    $parts[] = $text;
                }
            }
            if (count($parts) < 2 || ! preg_match($refPattern, end($parts))) {
                continue;
            }
            $ref = array_pop($parts);
            $isArabic = function (string $t): bool {
                $letters = preg_match_all('/\p{L}/u', $t);

                return $letters > 0 && preg_match_all('/[\x{0600}-\x{06FF}\x{0750}-\x{077F}]/u', $t) / $letters >= 0.6;
            };
            $arabic = '';
            $english = [];
            foreach ($parts as $p) {
                if ($arabic === '' && $isArabic($p)) {
                    $arabic = $p;
                } else {
                    $english[] = $p;
                }
            }
            if ($arabic === '' && $english === []) {
                continue;
            }
            $kind = preg_match('/^(?:Surah|Sura|Surat)\b/iu', $ref) ? 'quran' : 'hadith';
            while ($bq->firstChild) {
                $bq->removeChild($bq->firstChild);
            }
            $bq->setAttribute('class', 'quote quote--'.$kind);
            if ($arabic !== '') {
                $p = $doc->createElement('p');
                $p->setAttribute('class', 'quote__ar');
                $p->setAttribute('dir', 'rtl');
                $p->setAttribute('lang', 'ar');
                $p->appendChild($doc->createTextNode($arabic));
                $bq->appendChild($p);
            }
            if ($english !== []) {
                $p = $doc->createElement('p');
                $p->setAttribute('class', 'quote__text');
                $p->appendChild($doc->createTextNode(implode(' ', $english)));
                $bq->appendChild($p);
            }
            $cite = $doc->createElement('cite');
            $cite->setAttribute('class', 'quote__ref');
            $cite->appendChild($doc->createTextNode($ref));
            $bq->appendChild($cite);
        }
    }

    /**
     * The verse and hadith blocks of a marked text.
     *
     * @return list<array{type: string, arabic: string, text: string, ref: string}>
     */
    public static function quoteBlocks(?string $text): array
    {
        $out = [];
        if (preg_match_all('/^\s*\[(QURAN|HADITH)\]\s*$(.*?)(?:^\s*\[\/\1\]\s*$|\z)/ims', str_replace("\r", '', (string) $text), $m, PREG_SET_ORDER)) {
            foreach ($m as $b) {
                $f = static::quoteFields(explode("\n", trim($b[2], "\n")));
                $out[] = ['type' => strtolower($b[1]), 'arabic' => $f['ARABIC'], 'text' => $f['TEXT'], 'ref' => $f['REF']];
            }
        }

        return $out;
    }

    /** @return array{ARABIC: string, TEXT: string, REF: string} */
    private static function quoteFields(array $lines): array
    {
        $f = ['ARABIC' => '', 'TEXT' => '', 'REF' => ''];
        $key = null;
        foreach ($lines as $line) {
            if (preg_match('/^\s*(ARABIC|TEXT|REF)\s*:\s?(.*)$/iu', $line, $m)) {
                $key = strtoupper($m[1]);
                $f[$key] = trim($m[2]);
            } elseif ($key !== null && trim($line) !== '') {
                $f[$key] .= ' '.trim($line);
            }
        }

        return array_map('trim', $f);
    }

    /** One verse or hadith block as safe HTML. A block with neither Arabic nor English words is dropped. */
    private static function quoteBlock(string $type, array $lines): string
    {
        $f = static::quoteFields($lines);
        if ($f['ARABIC'] === '' && $f['TEXT'] === '') {
            return '';
        }
        $kind = $type === 'HADITH' ? 'hadith' : 'quran';

        return '<blockquote class="quote quote--'.$kind.'">'
            .($f['ARABIC'] !== '' ? '<p class="quote__ar" dir="rtl" lang="ar">'.e($f['ARABIC']).'</p>' : '')
            .($f['TEXT'] !== '' ? '<p class="quote__text">'.e($f['TEXT']).'</p>' : '')
            .($f['REF'] !== '' ? '<cite class="quote__ref">'.e($f['REF']).'</cite>' : '')
            .'</blockquote>';
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
                if ($attr->name === 'class' && in_array($name, ['blockquote', 'p', 'cite'], true)) {
                    $classes = array_values(array_intersect(preg_split('/\s+/', trim($attr->value)), self::CLASSES));
                    if ($classes !== []) {
                        $attr->value = implode(' ', $classes);
                        $keep = true;
                    }
                }
                // the text colour from the editor: a span with the class "color"
                if ($name === 'span' && $attr->name === 'class' && in_array('color', preg_split('/\s+/', trim($attr->value)), true)) {
                    $attr->value = 'color';
                    $keep = true;
                }
                // alignment, a text colour and a highlight: only a short list of safe style parts
                if ($attr->name === 'style' && in_array($name, ['p', 'h2', 'h3', 'h4', 'li', 'span', 'mark', 'td', 'th'], true)) {
                    $safe = static::safeStyle($name, $attr->value);
                    if ($safe !== '') {
                        $attr->value = $safe;
                        $keep = true;
                    }
                }
                // a picture: only from this site's own storage; size as whole numbers
                if ($name === 'img') {
                    if ($attr->name === 'src' && static::safeImage($attr->value)) {
                        $keep = true;
                    }
                    if ($attr->name === 'alt') {
                        $keep = true;
                    }
                    if (in_array($attr->name, ['width', 'height'], true) && preg_match('/^[0-9]{1,4}$/', trim($attr->value))) {
                        $keep = true;
                    }
                }
                if ($name === 'p' && (($attr->name === 'dir' && $attr->value === 'rtl') || ($attr->name === 'lang' && $attr->value === 'ar'))) {
                    $keep = true;
                }
                if (! $keep) {
                    $child->removeAttributeNode($attr);
                }
            }
            if ($name === 'a') {
                $child->setAttribute('rel', 'nofollow noopener');
            }
            if ($name === 'img') {
                if (! $child->hasAttribute('src')) {
                    $node->removeChild($child);

                    continue;
                }
                $child->setAttribute('loading', 'lazy');
            }
            static::walk($child);
        }
    }

    /** Only the style parts the editor makes: text-align, the colour of a word (--color, --dark-color), and the highlight. */
    private static function safeStyle(string $tag, string $style): string
    {
        $colour = '/^(#[0-9a-f]{3,8}|(?:rgb|rgba|hsl|hsla|oklch|oklab)\([0-9a-z .,%\/-]{1,60}\)|[a-z]{3,20})$/i';
        $out = [];
        foreach (explode(';', $style) as $declaration) {
            if (! str_contains($declaration, ':')) {
                continue;
            }
            [$property, $value] = array_map('trim', explode(':', $declaration, 2));
            $property = strtolower($property);
            if ($property === 'text-align' && in_array(strtolower($value), ['left', 'right', 'center', 'justify', 'start', 'end'], true)) {
                $out[] = 'text-align: '.strtolower($value);
            } elseif (in_array($property, ['--color', '--dark-color'], true) && $tag === 'span' && preg_match($colour, $value)) {
                $out[] = $property.': '.$value;
            } elseif ($property === 'background-color' && $tag === 'mark' && preg_match($colour, $value)) {
                $out[] = 'background-color: '.$value;
            } elseif ($property === 'color' && $tag === 'mark' && strtolower($value) === 'inherit') {
                $out[] = 'color: inherit';
            }
        }

        return implode('; ', $out);
    }

    /** A picture address that points into this site's own storage folder (a full address on this host, or one that starts with a slash). */
    private static function safeImage(string $src): bool
    {
        $src = trim($src);
        if ($src === '' || str_contains($src, '..') || str_contains($src, '\\')) {
            return false;
        }
        if (preg_match('~^https?://([^/]+)(/.*)$~i', $src, $m)) {
            if (strcasecmp($m[1], request()->getHttpHost()) !== 0) {
                return false;
            }
            $src = $m[2];
        }

        return (bool) preg_match('~^/(?:[A-Za-z0-9_.-]+/)*storage/[A-Za-z0-9_./-]+\.(?:webp|jpe?g|png|gif)$~', $src);
    }

    private static function safeHref(string $href): bool
    {
        $href = trim($href);

        return (bool) preg_match('~^(https?://|mailto:|/(?!/)|#)~i', $href);
    }
}
