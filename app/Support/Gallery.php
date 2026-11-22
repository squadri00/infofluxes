<?php

namespace App\Support;

use App\Models\Article;
use App\Models\ArticleImage;
use App\Models\Category;
use App\Models\DidYouKnow;
use App\Models\Story;
use Illuminate\Support\Facades\Cache;

/**
 * The picture gallery: every picture of a published page of the site, each with the page it leads to.
 * Stories (the slides and the covers), articles (the main picture and the others), "Did You Know?" facts and categories.
 * A draft, a hidden category or a page with a future date never shows. A picture used in several places is listed once.
 */
class Gallery
{
    public const KINDS = ['story', 'article', 'fact', 'category'];

    public const PER_PAGE = [25, 50, 100, 200];

    public const DEFAULT_PER = 50;

    /** The list is kept for a few minutes: it takes a few database questions to make. */
    private const CACHE_MINUTES = 10;

    public static function on(): bool
    {
        return SiteControls::on('gallery');
    }

    public static function forget(): void
    {
        Cache::forget('gallery.pictures');
    }

    /**
     * @return list<array{src: string, alt: string, title: string, parent: string, kind: string, url: string, root: ?int}>
     */
    public static function pictures(): array
    {
        return Cache::remember('gallery.pictures', now()->addMinutes(self::CACHE_MINUTES), fn () => static::build());
    }

    /** @return list<array{src: string, alt: string, title: string, parent: string, kind: string, url: string, root: ?int}> */
    private static function build(): array
    {
        $locale = config('infofluxes.default_locale');
        $parents = Category::query()->pluck('parent_id', 'id')->all();
        $root = function (?int $id) use ($parents): ?int {
            $guard = 0;
            while ($id && ($parents[$id] ?? null) && $guard++ < 12) {
                $id = $parents[$id];
            }

            return $id ?: null;
        };
        $out = [];
        $add = function (?string $src, string $alt, string $title, string $parent, string $kind, string $url, ?int $categoryId) use (&$out, $root) {
            if (blank($src) || isset($out[$src])) {
                return;
            }
            $out[$src] = ['src' => $src, 'alt' => trim($alt) !== '' ? trim($alt) : $title, 'title' => $title, 'parent' => $parent, 'kind' => $kind, 'url' => $url, 'root' => $root($categoryId)];
        };

        // Stories: the slides first (the link goes to that slide), then the covers.
        $stories = Story::published()->with('chapters.slides')->orderByDesc('id')->get();
        foreach ($stories as $story) {
            $n = 0;
            foreach ($story->chapters as $chapter) {
                foreach ($chapter->slides as $slide) {
                    $n++;
                    $heading = trim((string) $slide->getTranslation('heading', $locale, false));
                    $add($slide->image, (string) $slide->getTranslation('image_alt', $locale, false), $heading !== '' ? $heading : $story->mainTitle(), $story->mainTitle(), 'story', $story->url().'#slide-'.$n, $story->category_id);
                }
            }
        }
        foreach ($stories as $story) {
            $add($story->main_image, (string) $story->getTranslation('main_image_alt', $locale, false), $story->mainTitle(), $story->mainTitle(), 'story', $story->url(), $story->category_id);
        }

        foreach (Article::published()->whereNotNull('main_image')->orderByDesc('id')->get() as $article) {
            $add($article->main_image, (string) $article->getTranslation('main_image_alt', $locale, false), $article->mainTitle(), $article->mainTitle(), 'article', route('article.show', $article->slug), $article->category_id);
        }
        foreach (ArticleImage::query()->with('article')->whereHas('article', fn ($q) => $q->published())->orderByDesc('id')->get() as $image) {
            $article = $image->article;
            $add($image->image, (string) $image->getTranslation('alt', $locale, false), $article->mainTitle(), $article->mainTitle(), 'article', route('article.show', $article->slug), $article->category_id);
        }
        foreach (DidYouKnow::query()->visible()->whereNotNull('image')->get() as $fact) {
            $add($fact->image, (string) $fact->image_alt, (string) $fact->title, (string) $fact->title, 'fact', $fact->url(), $fact->category_id);
        }
        foreach (Category::query()->where('is_visible', true)->whereNotNull('image')->get() as $category) {
            $add($category->image, (string) $category->getTranslation('image_alt', $locale, false), $category->mainName(), $category->mainName(), 'category', route('category.show', $category->slug), $category->id);
        }

        return array_values($out);
    }

    /**
     * The pictures in a random order that stays the same for one visitor (the same $seed gives the same order), so
     * "Next page" never shows a picture twice.
     *
     * @param  list<array<string, mixed>>  $pictures
     * @return list<array<string, mixed>>
     */
    public static function shuffled(array $pictures, string $seed): array
    {
        usort($pictures, fn ($a, $b) => strcmp(md5($seed.$a['src']), md5($seed.$b['src'])));

        return $pictures;
    }

    /**
     * @param  list<array<string, mixed>>  $pictures
     * @return list<array<string, mixed>>
     */
    public static function filtered(array $pictures, ?string $kind, ?int $section): array
    {
        return array_values(array_filter($pictures, fn ($p) => ($kind === null || $p['kind'] === $kind) && ($section === null || $p['root'] === $section)));
    }

    /**
     * What the filter buttons show: how many pictures each kind and each section has.
     *
     * @param  list<array<string, mixed>>  $pictures
     * @return array{kinds: array<string, int>, sections: list<array{id: int, name: string, slug: string, count: int}>}
     */
    public static function counts(array $pictures): array
    {
        $kinds = array_fill_keys(self::KINDS, 0);
        $roots = [];
        foreach ($pictures as $p) {
            $kinds[$p['kind']]++;
            if ($p['root']) {
                $roots[$p['root']] = ($roots[$p['root']] ?? 0) + 1;
            }
        }
        $sections = [];
        foreach (Category::query()->whereIn('id', array_keys($roots))->where('is_visible', true)->orderBy('sort_order')->orderBy('id')->get() as $c) {
            $sections[] = ['id' => $c->id, 'name' => $c->mainName(), 'slug' => $c->slug, 'count' => $roots[$c->id]];
        }

        return ['kinds' => array_filter($kinds), 'sections' => $sections];
    }
}
