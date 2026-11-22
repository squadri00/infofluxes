<?php

namespace App\Support;

use App\Models\Article;
use App\Models\ArticleImage;
use App\Models\ArticleSlide;
use App\Models\Category;
use App\Models\DidYouKnow;
use App\Models\HeroSlide;
use App\Models\Person;
use App\Models\Setting;
use App\Models\Story;
use App\Models\StorySlide;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * The ALT text of a picture (what a screen reader says and what a search engine reads). A picture saved with an empty ALT gets one made from
 * the page it belongs to: the article title, the title and tab of a picture under a tab, the heading of a slide, the name of a category or person.
 * An ALT somebody wrote is never changed. The text can still be edited by hand.
 */
class PictureAlt
{
    /** The longest ALT text (screen readers read about this much comfortably). */
    public const MAX = 125;

    public static function register(): void
    {
        foreach ([Article::class, ArticleImage::class, ArticleSlide::class, Story::class, StorySlide::class, Category::class, DidYouKnow::class, HeroSlide::class, Person::class] as $class) {
            $class::saving(function (Model $model) {
                static::fill($model);
            });
        }
    }

    /** Fill the empty ALT of a picture of this record. Returns true when a text was set. */
    public static function fill(Model $model): bool
    {
        // [the picture column, the ALT column, the text when the ALT is empty]
        [$image, $alt, $text] = match (true) {
            $model instanceof Article => ['main_image', 'main_image_alt', fn () => $model->mainTitle()],
            $model instanceof Story => ['main_image', 'main_image_alt', fn () => $model->mainTitle()],
            $model instanceof ArticleImage => ['image', 'alt', fn () => static::forArticleImage($model)],
            $model instanceof ArticleSlide => ['image', 'image_alt', fn () => static::first($model, 'heading') ?: $model->chapter?->article?->mainTitle()],
            $model instanceof StorySlide => ['image', 'image_alt', fn () => static::first($model, 'heading') ?: static::slideOf($model)],
            $model instanceof Category => ['image', 'image_alt', fn () => $model->mainName()],
            $model instanceof DidYouKnow => ['image', 'image_alt', fn () => (string) $model->title],
            $model instanceof HeroSlide => ['image', 'image_alt', fn () => static::first($model, 'heading') ?: (string) Setting::get('site_name', config('app.name'))],
            $model instanceof Person => ['photo', 'photo_alt', fn () => $model->name ? 'Photo of '.$model->name : null],
            default => [null, null, null],
        };
        if ($image === null || blank($model->getAttribute($image)) || ! static::isEmpty($model, $alt)) {
            return false;
        }
        $value = trim((string) $text());
        if ($value === '') {
            return false;
        }
        $value = Str::limit(trim((string) preg_replace('/\s+/u', ' ', strip_tags($value))), static::MAX, '');
        if (static::translatable($model, $alt)) {
            $model->setTranslation($alt, config('infofluxes.default_locale'), $value);
        } else {
            $model->setAttribute($alt, $value);
        }

        return true;
    }

    private static function translatable(Model $model, string $field): bool
    {
        return method_exists($model, 'getTranslatableAttributes') && in_array($field, $model->getTranslatableAttributes(), true);
    }

    private static function isEmpty(Model $model, string $field): bool
    {
        if (static::translatable($model, $field)) {
            return blank($model->getTranslation($field, config('infofluxes.default_locale'), false));
        }

        return blank($model->getAttribute($field));
    }

    /** The main-language text of a translated field, or an empty string. */
    private static function first(Model $model, string $field): string
    {
        return trim((string) $model->getTranslation($field, config('infofluxes.default_locale'), false));
    }

    /** "Title - The Beginning of His Travels": the article and, when the picture sits under a tab, the heading of that tab. */
    private static function forArticleImage(ArticleImage $image): ?string
    {
        $article = $image->article;
        if (! $article) {
            return null;
        }
        $title = $article->mainTitle();
        $n = (int) ($image->section ?? 0);
        if ($n >= 1) {
            $heading = ArticlePictures::headings($article->getTranslation('long_detail', config('infofluxes.default_locale'), false))[$n] ?? null;
            $heading = $heading ? trim((string) preg_replace('/^\d+\s*[.)]\s*/u', '', $heading)) : null;
            if ($heading) {
                return $title.' - '.$heading;
            }
        }

        return $title;
    }

    /** "Story title, slide 4" for a slide with no heading. */
    private static function slideOf(StorySlide $slide): ?string
    {
        $story = $slide->chapter?->story;

        return $story ? $story->mainTitle().', slide '.max(1, (int) $slide->sort_order) : null;
    }
}
