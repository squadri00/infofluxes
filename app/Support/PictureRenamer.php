<?php

namespace App\Support;

use App\Models\Article;
use App\Models\ArticleImage;
use App\Models\ArticleSlide;
use App\Models\Category;
use App\Models\DidYouKnow;
use App\Models\HeroSlide;
use App\Models\Person;
use App\Models\PictureRedirect;
use App\Models\Setting;
use App\Models\Story;
use App\Models\StorySlide;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Gives the pictures that are already on the site a name made from their page (see App\Support\PictureNames), in one go.
 * The file is moved, every place that holds its path is updated, and the old address is remembered (PictureRedirect), so a link or a search
 * result that still uses the old name goes to the new one. Safe to run again: a picture that is named after its page is left alone,
 * and one whose page was renamed gets a new name (the old names are still remembered).
 */
class PictureRenamer
{
    /**
     * Every picture and what its page is called. The first reference of a file that is used in several places decides its name.
     *
     * @return array<string, array{subject: string, refs: array<string, array<string, mixed>>}> old path => the subject and the places that use it (a table column, a site setting, or a key of a category's custom data)
     */
    public static function references(): array
    {
        $locale = config('infofluxes.default_locale');
        $heading = fn (Model $m) => trim((string) $m->getTranslation('heading', $locale, false));
        $found = [];
        $add = function (Model $model, string $column, string $subject) use (&$found) {
            $path = $model->getAttribute($column);
            if (! is_string($path) || trim($path) === '') {
                return;
            }
            $found[$path]['subject'] ??= $subject;
            $found[$path]['refs'][$model->getTable().'.'.$column] = ['kind' => 'column', 'table' => $model->getTable(), 'column' => $column];
        };
        $addOther = function (string $path, string $subject, string $key, array $ref) use (&$found) {
            if (trim($path) === '') {
                return;
            }
            $found[$path]['subject'] ??= $subject;
            $found[$path]['refs'][$key] = $ref;
        };
        $all = fn (string $class) => method_exists($class, 'withTrashed') ? $class::withTrashed() : $class::query();

        foreach ($all(Article::class)->whereNotNull('main_image')->get() as $a) {
            $add($a, 'main_image', $a->mainTitle());
        }
        foreach ($all(Story::class)->whereNotNull('main_image')->get() as $s) {
            $add($s, 'main_image', $s->mainTitle());
        }
        foreach (ArticleImage::query()->with('article')->get() as $image) {
            $title = $image->article?->mainTitle() ?? 'article';
            $tab = '';
            if ($image->article && ($n = (int) $image->section) >= 1) {
                $h = ArticlePictures::headings($image->article->getTranslation('long_detail', $locale, false))[$n] ?? '';
                $tab = trim((string) preg_replace('/^\d+\s*[.)]\s*/u', '', $h));
            }
            $add($image, 'image', trim($tab.' '.$title));
        }
        foreach (ArticleSlide::query()->with('chapter.article')->get() as $slide) {
            $add($slide, 'image', trim(($heading($slide) ?: 'slide '.$slide->sort_order).' '.($slide->chapter?->article?->mainTitle() ?? 'article')));
        }
        foreach (StorySlide::query()->with('chapter.story')->get() as $slide) {
            $add($slide, 'image', trim(($heading($slide) ?: 'slide '.$slide->sort_order).' '.($slide->chapter?->story?->mainTitle() ?? 'story')));
        }
        foreach ($all(Category::class)->get() as $c) {
            $add($c, 'image', $c->mainName());
            $add($c, 'feature_image', $c->mainName().' menu card');
            if (is_string($menu = $c->custom_data['menu_challenge_image'] ?? null)) {
                $addOther($menu, $c->mainName().' menu challenge', 'category_data.'.$c->id.'.menu_challenge_image', ['kind' => 'category_data', 'id' => $c->id, 'key' => 'menu_challenge_image']);
            }
        }
        foreach ($all(DidYouKnow::class)->whereNotNull('image')->get() as $f) {
            $add($f, 'image', (string) $f->title);
        }
        foreach (HeroSlide::query()->whereNotNull('image')->get() as $h) {
            $add($h, 'image', $heading($h) ?: 'home slider');
        }
        foreach (Person::query()->whereNotNull('photo')->get() as $p) {
            $add($p, 'photo', (string) $p->name);
        }
        // The logo and the picture shown when a link is shared (Settings): named after the site.
        $site = (string) Setting::get('site_name', config('app.name'));
        foreach (['logo' => $site.' logo', 'seo_og_image' => $site.' social share picture'] as $key => $subject) {
            if (is_string($value = Setting::get($key))) {
                $addOther($value, $subject, 'setting.'.$key, ['kind' => 'setting', 'key' => $key]);
            }
        }

        return $found;
    }

    /**
     * What would be renamed.
     *
     * @return list<array{old: string, new: string, subject: string, refs: list<array<string, mixed>>}>
     */
    public static function plan(): array
    {
        $disk = Storage::disk(ImageStore::DISK);
        $plan = [];
        $taken = [];
        foreach (static::references() as $old => $info) {
            if (! $disk->exists($old) || PictureNames::isNamedFor($old, $info['subject'])) {
                continue;   // a file that is not there cannot be moved; one that has its name already stays
            }
            $ext = strtolower(pathinfo($old, PATHINFO_EXTENSION)) ?: 'webp';
            do {
                $new = PictureNames::path(dirname($old) === '.' ? '' : dirname($old), $info['subject'], $ext);
            } while (isset($taken[$new]));
            $taken[$new] = true;
            $plan[] = ['old' => $old, 'new' => $new, 'subject' => $info['subject'], 'refs' => array_values($info['refs'])];
        }

        return $plan;
    }

    /**
     * Do it. Returns how many pictures were renamed and the problems.
     *
     * @param  list<array{old: string, new: string, refs: list<array<string, mixed>>}>  $plan
     * @return array{renamed: int, problems: list<string>}
     */
    public static function apply(array $plan): array
    {
        $disk = Storage::disk(ImageStore::DISK);
        $done = 0;
        $problems = [];
        foreach ($plan as $row) {
            try {
                if (! $disk->move($row['old'], $row['new'])) {
                    $problems[] = $row['old'].': the file could not be moved';

                    continue;
                }
                try {
                    DB::transaction(function () use ($row) {
                        foreach ($row['refs'] as $ref) {
                            static::repoint($ref, $row['old'], $row['new']);
                        }
                        PictureRedirect::remember($row['old'], $row['new']);
                    });
                } catch (\Throwable $e) {
                    $disk->move($row['new'], $row['old']);     // the database did not change, so the file goes back
                    throw $e;
                }
                $done++;
            } catch (\Throwable $e) {
                $problems[] = $row['old'].': '.$e->getMessage();
            }
        }

        return ['renamed' => $done, 'problems' => $problems];
    }

    /** Make one place that holds the old path hold the new one. @param array<string, mixed> $ref */
    private static function repoint(array $ref, string $old, string $new): void
    {
        switch ($ref['kind'] ?? 'column') {
            case 'setting':
                Setting::put($ref['key'], $new);
                break;
            case 'category_data':
                $category = Category::withTrashed()->find($ref['id']);
                if ($category) {
                    $data = (array) $category->custom_data;
                    $data[$ref['key']] = $new;
                    $category->custom_data = $data;
                    $category->saveQuietly();
                }
                break;
            default:
                DB::table($ref['table'])->where($ref['column'], $old)->update([$ref['column'] => $new]);
        }
    }
}
