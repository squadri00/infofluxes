<?php

namespace App\Support\StoryMaker;

use App\Models\Article;
use App\Models\Category;
use App\Models\Story;
use App\Models\User;
use App\Support\IndexEntries;
use App\Support\ScriptExport;
use App\Support\StoryImage;
use Illuminate\Support\Facades\Storage;

/**
 * "Make a story from this article": the short, picture-led telling of an article that is already written.
 * - The staff (moderators and the super user) decide which article gets a story: the button is on the article.
 * - The Story maker opens with the article's text in it; the prompt is the "short story from an article" prompt.
 * - The saved draft story is linked to the article (stories.article_id), carries its source line, and takes its pictures from the article:
 *   the article's main picture first, then its other pictures in reading order, spread evenly over the slides (a story picture is a story-shaped copy,
 *   so the article is never touched). The gallery shows such a picture once (see Gallery::build).
 */
class FromArticle
{
    /** Who may press the button, and for which article: it needs text, and the article must not have a story yet (that one is opened instead). */
    public static function canMake(Article $article, ?User $user): bool
    {
        if (! $user || ! $user->canPublish() || ! $user->canUseScreen('storymaker')) {
            return false;
        }
        if (IndexEntries::isBlank($article)) {
            return false;
        }

        return trim(strip_tags((string) $article->getTranslation('long_detail', config('infofluxes.default_locale'), false))) !== '';
    }

    /** The story of this article, if there is one (the newest). */
    public static function storyOf(Article $article): ?Story
    {
        return Story::query()->where('article_id', $article->id)->orderByDesc('id')->first();
    }

    /** The article in plain words: title, short detail, then the text with its headings ("## ") and lists ("- "). Quotations are kept as they are. */
    public static function text(Article $article): string
    {
        $locale = config('infofluxes.default_locale');
        $lines = [$article->mainTitle(), ''];
        foreach ([(string) $article->getTranslation('short_detail', $locale, false), (string) $article->getTranslation('long_detail', $locale, false)] as $html) {
            foreach (ScriptExport::fromHtml($html) as [$kind, $text]) {
                $text = trim($text);
                if ($text === '') {
                    continue;
                }
                if (str_starts_with($kind, 'h')) {
                    $lines[] = '';
                    $lines[] = '## '.$text;
                } elseif ($kind === 'li') {
                    $lines[] = '- '.$text;
                } else {
                    $lines[] = $text;
                }
            }
        }

        return trim(implode("\n", $lines));
    }

    /** A fingerprint of what a story is made from: the title, the short detail and the text of the article. Likes, views and comments do not change it. */
    public static function fingerprint(Article $article): string
    {
        $locale = config('infofluxes.default_locale');
        $clean = fn ($v) => trim(preg_replace('/\s+/u', ' ', strip_tags((string) $v)));

        return md5($clean($article->getTranslation('title', $locale, false)).'|'.$clean($article->getTranslation('short_detail', $locale, false)).'|'.$clean($article->getTranslation('long_detail', $locale, false)));
    }

    /** True when the story was made from an article whose text has changed since (the story is then probably out of date). */
    public static function outdated(Story $story): bool
    {
        $saved = ((array) $story->custom_data)['article_fingerprint'] ?? null;
        $article = $story->article_id ? $story->article : null;

        return $saved && $article && $saved !== static::fingerprint($article);
    }

    /** The category of the story: the article's own category when a story may be saved there, else none (the person chooses). */
    public static function categoryId(Article $article): ?int
    {
        return array_key_exists((int) $article->category_id, Category::storyChoices()) ? (int) $article->category_id : null;
    }

    /**
     * The pictures of the article that exist on the server: the main picture first, then the others in reading order.
     *
     * @return list<array{path: string, alt: string, credit: string|null, licence: string|null}>
     */
    public static function pictures(Article $article): array
    {
        $locale = config('infofluxes.default_locale');
        $disk = Storage::disk('public');
        $list = [];
        if (filled($article->main_image)) {
            $list[] = ['path' => (string) $article->main_image, 'alt' => (string) $article->getTranslation('main_image_alt', $locale, false),
                'credit' => $article->getAttribute('main_image_credit') ?: null, 'licence' => $article->getAttribute('main_image_licence') ?: null];
        }
        $others = $article->images()->get()->sortBy(fn ($i) => [(int) ($i->section ?: 999), (int) $i->sort_order, (int) $i->id])->values();
        foreach ($others as $image) {
            if (filled($image->image)) {
                $list[] = ['path' => (string) $image->image, 'alt' => (string) $image->getTranslation('alt', $locale, false), 'credit' => null, 'licence' => null];
            }
        }
        $seen = [];

        return array_values(array_filter($list, function ($p) use ($disk, &$seen) {
            if (isset($seen[$p['path']]) || ! $disk->exists($p['path'])) {
                return false;
            }

            return $seen[$p['path']] = true;
        }));
    }

    /**
     * Puts the article's pictures on the cover and on the slides that have no picture yet. Slide i of S gets picture floor(i * N / S) of N, so the
     * order of the article is kept and every slide has a picture (a picture may stay for a few slides when the article has fewer pictures than the story has slides).
     * A picture that was added by hand is never replaced. Returns how many places were filled.
     */
    public static function applyPictures(Story $story, Article $article): int
    {
        $pool = static::pictures($article);
        if ($pool === []) {
            return 0;
        }
        $locale = config('infofluxes.default_locale');
        $disk = Storage::disk('public');
        $copies = [];
        $copy = function (int $index, string $what) use ($pool, $story, $disk, &$copies): string {
            $path = $pool[$index]['path'];

            return $copies[$path] ??= StoryImage::store($disk->path($path), $story->mainTitle().' '.$what);
        };

        $filled = 0;
        if (blank($story->main_image)) {
            $p = $pool[0];
            $story->main_image = $copy(0, 'cover');
            if ($p['alt'] !== '') {
                $story->setTranslation('main_image_alt', $locale, $p['alt']);
            }
            $story->main_image_credit = $p['credit'] ?: $story->main_image_credit;
            $story->main_image_licence = $p['licence'] ?: $story->main_image_licence;
            $story->custom_data = array_merge((array) $story->custom_data, ['source_main_image' => $p['path']]);
            $story->save();
            $filled++;
        }

        $slides = $story->chapters()->with('slides')->get()->flatMap(fn ($c) => $c->slides)->values();
        $s = $slides->count();
        $n = count($pool);
        foreach ($slides as $i => $slide) {
            if (filled($slide->image)) {
                continue;
            }
            $k = min($n - 1, intdiv($i * $n, max(1, $s)));
            $p = $pool[$k];
            $slide->image = $copy($k, 'slide '.($i + 1));
            if ($p['alt'] !== '') {
                $slide->setTranslation('image_alt', $locale, $p['alt']);
            }
            if ($p['credit']) {
                $slide->image_credit = $p['credit'];
            }
            if ($p['licence']) {
                $slide->image_licence = $p['licence'];
            }
            $slide->custom_data = array_merge((array) $slide->custom_data, ['source_image' => $p['path']]);
            $slide->save();
            $filled++;
        }

        return $filled;
    }
}
