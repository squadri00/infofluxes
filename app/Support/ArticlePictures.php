<?php

namespace App\Support;

use App\Models\ArticleImage;
use Illuminate\Support\Collection;

/**
 * Puts the extra pictures of an article inside its text, under the main headings (the "## " parts), like a magazine.
 *
 * A picture with a number in "Put it in section number" goes under that heading (1 = the first main heading).
 * The pictures without a number go in the order they were added, one under each heading that has no picture yet.
 * A picture that has no place (more pictures than headings, or a text without headings) stays in the small "Pictures" strip at the bottom.
 */
class ArticlePictures
{
    /**
     * @param  Collection<int, ArticleImage>  $images
     * @return array{html: string, rest: Collection<int, ArticleImage>}
     */
    public static function place(string $html, Collection $images): array
    {
        $images = $images->values();
        $count = preg_match_all('#<h2\b[^>]*>.*?</h2>#s', $html);
        if ($images->isEmpty() || $count === 0) {
            return ['html' => $html, 'rest' => $images];
        }

        // Which pictures go under which heading (1-based).
        $under = [];
        $rest = collect();
        $free = [];
        foreach ($images as $image) {
            $n = (int) ($image->section ?? 0);
            if ($n >= 1 && $n <= $count) {
                $under[$n][] = $image;
            } elseif ($n >= 1) {
                $rest->push($image);            // a number that does not exist: bottom strip
            } else {
                $free[] = $image;
            }
        }
        $n = 1;
        foreach ($free as $image) {
            while ($n <= $count && isset($under[$n])) {
                $n++;
            }
            if ($n > $count) {
                $rest->push($image);

                continue;
            }
            $under[$n][] = $image;
            $n++;
        }

        $i = 0;
        $html = preg_replace_callback('#(<h2\b[^>]*>.*?</h2>)#s', function ($m) use (&$i, $under) {
            $i++;
            $figures = '';
            foreach ($under[$i] ?? [] as $image) {
                $figures .= static::figure($image);
            }

            return $m[1].$figures;
        }, $html);

        return ['html' => $html, 'rest' => $rest->sortBy('sort_order')->values()];
    }

    /**
     * The tabs of an article text: its main headings (h2) in order. The text is HTML (a saved article) or the document of the text editor
     * while it is being edited (nested blocks: {"type":"doc","content":[{"type":"heading","attrs":{"level":2},...}]}).
     *
     * @param  string|array<string, mixed>|null  $content
     * @return array<int, string> 1-based number => the heading as plain text
     */
    public static function headings(string|array|null $content): array
    {
        $texts = [];
        if (is_array($content)) {
            static::collectHeadings($content, $texts);
        } elseif (preg_match_all('#<h2\b[^>]*>(.*?)</h2>#s', (string) $content, $m)) {
            foreach ($m[1] as $inner) {
                $texts[] = html_entity_decode(strip_tags($inner), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
        }
        $out = [];
        foreach ($texts as $i => $text) {
            $text = trim((string) preg_replace('/\s+/u', ' ', $text));
            $out[$i + 1] = $text !== '' ? $text : '(empty heading)';
        }

        return $out;
    }

    /** @param array<string, mixed> $node @param list<string> $texts */
    private static function collectHeadings(array $node, array &$texts): void
    {
        if (($node['type'] ?? null) === 'heading' && (int) ($node['attrs']['level'] ?? 0) === 2) {
            $texts[] = static::plain($node);

            return;
        }
        foreach ((array) ($node['content'] ?? []) as $child) {
            if (is_array($child)) {
                static::collectHeadings($child, $texts);
            }
        }
    }

    /** @param array<string, mixed> $node */
    private static function plain(array $node): string
    {
        $text = (string) ($node['text'] ?? '');
        foreach ((array) ($node['content'] ?? []) as $child) {
            if (is_array($child)) {
                $text .= static::plain($child);
            }
        }

        return $text;
    }

    /** How a tab is named in lists: its own heading, with its number in front when the heading has none ("3. The Beginning of His Travels"). */
    public static function tabName(int $n, string $heading): string
    {
        return preg_match('/^\d+\s*[.)]/u', $heading) ? $heading : $n.'. '.$heading;
    }

    /**
     * The drop-down of the picture form: [tab number => name]. A number that no heading has any more (the heading was removed) stays in the list, so it is not lost.
     *
     * @return array<int, string>
     */
    public static function tabOptions(string|array|null $html, ?int $current = null): array
    {
        $options = [];
        foreach (static::headings($html) as $n => $heading) {
            $options[$n] = static::tabName($n, $heading);
        }
        if ($current && ! isset($options[$current])) {
            $options[$current] = 'Tab '.$current.' (the text has no such heading now)';
        }

        return $options;
    }

    /** The title of a picture row when it is closed: its tab and what it shows. @param array<string, mixed> $state */
    public static function itemLabel(array $state, string|array|null $html): string
    {
        $alt = $state['alt']['en'] ?? (is_array($state['alt'] ?? null) ? (string) (reset($state['alt']) ?: '') : '');
        $n = (int) ($state['section'] ?? 0);
        $tab = $n >= 1 ? 'Tab '.rtrim(static::tabName($n, static::headings($html)[$n] ?? '(no such heading)'), '.') : 'No tab chosen';

        return $tab.' · '.($alt !== '' ? $alt : 'Picture');
    }

    private static function figure(ArticleImage $image): string
    {
        $caption = trim((string) $image->caption);

        return '<figure class="article__inline"><img src="'.e(asset('storage/'.$image->image)).'" alt="'.e((string) $image->alt).'" loading="lazy">'
            .($caption !== '' ? '<figcaption>'.e($caption).'</figcaption>' : '').'</figure>';
    }
}
