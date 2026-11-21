<?php

namespace App\Support;

use App\Models\Article;
use App\Models\Story;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The script of an article or of a story in pictures as a file for the staff: a Word file (.docx) or a plain text file (.txt).
 * It holds the words only: the title, the short detail, the text (for a story: each chapter and each slide in order) and the source line.
 */
class ScriptExport
{
    private const FORMATS = ['docx', 'txt'];

    /**
     * The "Download script" button for the admin screens: Word or plain text. In a table row the record comes in by itself;
     * on an edit page pass a closure that gives the record.
     */
    public static function button(?\Closure $item = null, bool $asButton = true): \Filament\Actions\ActionGroup
    {
        $make = fn (string $format, string $label) => \Filament\Actions\Action::make('script_'.$format)->label($label)
            ->action(fn ($record = null) => static::download($item ? $item() : $record, $format));

        $group = \Filament\Actions\ActionGroup::make([
            $make('docx', 'Word file (.docx)'),
            $make('txt', 'Plain text (.txt)'),
        ])->label('Download script')->icon('heroicon-o-arrow-down-tray')->color('gray');

        return $asButton ? $group->button() : $group->tooltip('Download the script');
    }

    /** @return list<array{0: string, 1: string}> blocks of [kind, text]; kind is title, h1, h2, p or li */
    public static function blocks(Article|Story $item): array
    {
        $out = [['title', static::text($item, 'title')]];
        $out[] = ['p', ($item instanceof Story ? 'Story ' : 'Article ').$item->code.' - '.$item->status.' - '.static::category($item)];

        if (($short = static::text($item, 'short_detail')) !== '') {
            $out[] = ['h1', 'Short detail'];
            array_push($out, ...static::fromHtml($short));
        }

        if ($item instanceof Story) {
            $n = 0;
            foreach ($item->chapters()->with('slides')->get() as $chapter) {
                if (($t = trim((string) static::translated($chapter, 'title'))) !== '') {
                    $out[] = ['h1', $t];
                }
                foreach ($chapter->slides as $slide) {
                    $n++;
                    $heading = trim((string) static::translated($slide, 'heading'));
                    $out[] = ['h2', 'Slide '.$n.($heading !== '' ? ': '.$heading : '')];
                    array_push($out, ...static::fromHtml((string) static::translated($slide, 'body')));
                }
            }
            if (($more = static::text($item, 'long_detail')) !== '') {
                $out[] = ['h1', 'Text under the story'];
                array_push($out, ...static::fromHtml($more));
            }
        } else {
            if (($long = static::text($item, 'long_detail')) !== '') {
                $out[] = ['h1', 'Text'];
                array_push($out, ...static::fromHtml($long));
            }
        }

        if (trim((string) $item->source_line) !== '') {
            $out[] = ['h1', 'Source'];
            $out[] = ['p', trim((string) $item->source_line)];
        }

        return $out;
    }

    public static function download(Article|Story $item, string $format): Response
    {
        $format = in_array($format, static::FORMATS, true) ? $format : 'txt';
        $name = $item->code.'-'.$item->slug.'.'.$format;
        $blocks = static::blocks($item);
        $body = $format === 'docx' ? static::docx($blocks) : static::txt($blocks);
        $type = $format === 'docx' ? 'application/vnd.openxmlformats-officedocument.wordprocessingml.document' : 'text/plain; charset=UTF-8';

        return new StreamedResponse(function () use ($body) {
            echo $body;
        }, 200, ['Content-Type' => $type, 'Content-Disposition' => 'attachment; filename="'.$name.'"', 'Content-Length' => (string) strlen($body)]);
    }

    /** @param list<array{0: string, 1: string}> $blocks */
    public static function txt(array $blocks): string
    {
        $lines = [];
        foreach ($blocks as [$kind, $text]) {
            switch ($kind) {
                case 'title':
                    $lines[] = $text;
                    $lines[] = str_repeat('=', max(3, mb_strlen($text)));
                    break;
                case 'h1':
                    $lines[] = '';
                    $lines[] = mb_strtoupper($text);
                    break;
                case 'h2':
                    $lines[] = '';
                    $lines[] = $text;
                    $lines[] = str_repeat('-', max(3, mb_strlen($text)));
                    break;
                case 'li':
                    $lines[] = '- '.$text;
                    break;
                default:
                    $lines[] = '';
                    $lines[] = $text;
            }
        }

        return "\xEF\xBB\xBF".str_replace("\n", "\r\n", trim(implode("\n", $lines)))."\r\n";
    }

    /** @param list<array{0: string, 1: string}> $blocks */
    public static function docx(array $blocks): string
    {
        $style = ['title' => 'Title', 'h1' => 'Heading1', 'h2' => 'Heading2'];
        $body = '';
        foreach ($blocks as [$kind, $text]) {
            $props = isset($style[$kind]) ? '<w:pPr><w:pStyle w:val="'.$style[$kind].'"/></w:pPr>' : ($kind === 'li' ? '<w:pPr><w:ind w:left="360"/></w:pPr>' : '');
            $text = $kind === 'li' ? '• '.$text : $text;
            $body .= '<w:p>'.$props.'<w:r><w:t xml:space="preserve">'.static::xml($text).'</w:t></w:r></w:p>';
        }

        $ns = 'xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"';
        $files = [
            '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/><Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/></Types>',
            '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>',
            'word/_rels/document.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>',
            'word/styles.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:styles '.$ns.'><w:docDefaults><w:rPrDefault><w:rPr><w:rFonts w:ascii="Calibri" w:hAnsi="Calibri" w:cs="Arial"/><w:sz w:val="24"/></w:rPr></w:rPrDefault><w:pPrDefault><w:pPr><w:spacing w:after="160" w:line="300" w:lineRule="auto"/></w:pPr></w:pPrDefault></w:docDefaults>'
                .'<w:style w:type="paragraph" w:default="1" w:styleId="Normal"><w:name w:val="Normal"/></w:style>'
                .'<w:style w:type="paragraph" w:styleId="Title"><w:name w:val="Title"/><w:basedOn w:val="Normal"/><w:next w:val="Normal"/><w:pPr><w:spacing w:after="240"/></w:pPr><w:rPr><w:b/><w:sz w:val="44"/></w:rPr></w:style>'
                .'<w:style w:type="paragraph" w:styleId="Heading1"><w:name w:val="heading 1"/><w:basedOn w:val="Normal"/><w:next w:val="Normal"/><w:pPr><w:keepNext/><w:spacing w:before="320" w:after="120"/><w:outlineLvl w:val="0"/></w:pPr><w:rPr><w:b/><w:sz w:val="32"/></w:rPr></w:style>'
                .'<w:style w:type="paragraph" w:styleId="Heading2"><w:name w:val="heading 2"/><w:basedOn w:val="Normal"/><w:next w:val="Normal"/><w:pPr><w:keepNext/><w:spacing w:before="240" w:after="80"/><w:outlineLvl w:val="1"/></w:pPr><w:rPr><w:b/><w:sz w:val="26"/></w:rPr></w:style></w:styles>',
            'word/document.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:document '.$ns.'><w:body>'.$body.'<w:sectPr><w:pgSz w:w="11906" w:h="16838"/><w:pgMar w:top="1440" w:right="1440" w:bottom="1440" w:left="1440" w:header="708" w:footer="708" w:gutter="0"/></w:sectPr></w:body></w:document>',
        ];

        $tmp = tempnam(sys_get_temp_dir(), 'scr');
        $zip = new \ZipArchive;
        $zip->open($tmp, \ZipArchive::OVERWRITE);
        foreach ($files as $path => $content) {
            $zip->addFromString($path, $content);
        }
        $zip->close();
        $data = (string) file_get_contents($tmp);
        @unlink($tmp);

        return $data;
    }

    /** The text of a translatable field in the main language (the first language that has text when the main one is empty). */
    private static function translated(object $model, string $field): string
    {
        $locale = config('infofluxes.default_locale');

        return (string) ($model->getTranslation($field, $locale, false) ?: $model->getTranslation($field, $locale));
    }

    private static function text(Article|Story $item, string $field): string
    {
        return trim(static::translated($item, $field));
    }

    private static function category(Article|Story $item): string
    {
        return $item->category ? $item->category->mainName().' ('.$item->category->code.')' : 'no category';
    }

    private static function xml(string $text): string
    {
        $text = preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $text) ?? '';

        return htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    /** @return list<array{0: string, 1: string}> */
    public static function fromHtml(string $html): array
    {
        $html = trim($html);
        if ($html === '') {
            return [];
        }
        if (! str_contains($html, '<')) {
            return array_map(fn ($line) => ['p', $line], array_values(array_filter(array_map('trim', preg_split('/\R/u', $html)))));
        }

        $dom = new \DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8"><body>'.$html.'</body>');
        libxml_clear_errors();
        $out = [];
        $body = $dom->getElementsByTagName('body')->item(0);
        if ($body) {
            static::walk($body, $out);
        }

        return $out;
    }

    /** @param list<array{0: string, 1: string}> $out */
    private static function walk(\DOMNode $node, array &$out): void
    {
        $blockTags = ['p', 'ul', 'ol', 'li', 'blockquote', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'div', 'table', 'tr'];
        $flat = function (\DOMNode $n): string {
            return trim((string) preg_replace('/\s+/u', ' ', str_replace("\xC2\xA0", ' ', $n->textContent)));
        };
        $hasBlock = function (\DOMNode $n) use ($blockTags): bool {
            foreach ($n->childNodes as $c) {
                if ($c instanceof \DOMElement && in_array(strtolower($c->tagName), $blockTags, true)) {
                    return true;
                }
            }

            return false;
        };

        $loose = '';
        foreach ($node->childNodes as $child) {
            if ($child instanceof \DOMText) {
                $loose .= $child->textContent;

                continue;
            }
            if (! $child instanceof \DOMElement) {
                continue;
            }
            $tag = strtolower($child->tagName);
            if (! in_array($tag, $blockTags, true)) {
                $loose .= $child->textContent;

                continue;
            }
            if (($t = trim((string) preg_replace('/\s+/u', ' ', $loose))) !== '') {
                $out[] = ['p', $t];
            }
            $loose = '';

            if (in_array($tag, ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'], true)) {
                if (($t = $flat($child)) !== '') {
                    $out[] = [in_array($tag, ['h1', 'h2'], true) ? 'h1' : 'h2', $t];
                }
            } elseif ($tag === 'li' && $hasBlock($child)) {
                $inner = [];
                static::walk($child, $inner);
                foreach ($inner as [$k, $t]) {
                    $out[] = [$k === 'p' ? 'li' : $k, $t];
                }
            } elseif (in_array($tag, ['ul', 'ol', 'div', 'table', 'tr'], true) || $hasBlock($child)) {
                static::walk($child, $out);
            } elseif (($t = $flat($child)) !== '') {
                $out[] = [$tag === 'li' ? 'li' : 'p', $t];
            }
        }
        if (($t = trim((string) preg_replace('/\s+/u', ' ', $loose))) !== '') {
            $out[] = ['p', $t];
        }
    }
}
