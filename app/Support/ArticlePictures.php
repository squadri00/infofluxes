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

    private static function figure(ArticleImage $image): string
    {
        $caption = trim((string) $image->caption);

        return '<figure class="article__inline"><img src="'.e(asset('storage/'.$image->image)).'" alt="'.e((string) $image->alt).'" loading="lazy">'
            .($caption !== '' ? '<figcaption>'.e($caption).'</figcaption>' : '').'</figure>';
    }
}
