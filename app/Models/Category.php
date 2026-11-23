<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Spatie\Translatable\HasTranslations;

/**
 * A category, a sub-category or a sub-sub-category: one table, one tree.
 * The code (C-12) is made from the id, so it never changes and is never used twice (deleted ones are only hidden).
 */
class Category extends Model
{
    use HasTranslations, SoftDeletes, \App\Models\Concerns\LogsActivity, \App\Models\Concerns\KeepsOldAddress;

    public function publicPath(?string $slug = null): string
    {
        return '/category/'.($slug ?? $this->slug);
    }

    protected $guarded = [];

    /** Words that are already web addresses of the site and cannot be used for a category. */
    public const RESERVED_SLUGS = ['admin', 'login', 'logout', 'register', 'search', 'category', 'article', 'storage', 'livewire', 'up', 'profile', 'comments', 'submit', 'account', 'id', 'language', 'sitemap', 'people', 'newsletter', 'stories', 'words', 'challenge-yourself', 'quiz', 'gallery', 'categories'];

    public array $translatable = ['name', 'image_alt', 'description', 'header_text', 'page_title', 'meta_description', 'feature_title', 'feature_text', 'feature_button'];

    protected function casts(): array
    {
        return ['is_visible' => 'boolean', 'custom_data' => 'array'];
    }

    /** While true, saving a category does not re-order the tree. A big import turns it on, then rebuilds once at the end. */
    public static bool $pauseRebuild = false;

    protected static function booted(): void
    {
        $rebuild = function () {
            if (! static::$pauseRebuild) {
                static::rebuildPaths();
            }
        };
        static::saved($rebuild);
        static::deleted($rebuild);
        static::restored($rebuild);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id')->orderBy('sort_order')->orderBy('id');
    }

    /** Articles whose main category this is. */
    public function articles(): HasMany
    {
        return $this->hasMany(Article::class);
    }

    /** Articles that also sit in this category. */
    public function extraArticles(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Article::class, 'article_category');
    }

    /** How many articles are in this category (main or additional). */
    public function articleCount(): int
    {
        $articles = Article::query()->where(fn ($w) => $w->where('category_id', $this->id)->orWhereHas('extraCategories', fn ($q) => $q->where('categories.id', $this->id)))->count();
        $stories = Story::query()->where(fn ($w) => $w->where('category_id', $this->id)->orWhereHas('extraCategories', fn ($q) => $q->where('categories.id', $this->id)))->count();

        return $articles + $stories;
    }

    /** How many published articles and stories the visitor finds in this category and everything below it (shown on the category cards). */
    public function listedCount(): int
    {
        return \App\Support\AllArticles::total($this);
    }

    /** A web address made from a name: letters, numbers and dashes, never a reserved word, never one already used. */
    public static function uniqueSlug(string $name, string $fallback = 'category'): string
    {
        $base = \Illuminate\Support\Str::slug($name) ?: $fallback;
        $slug = $base;
        $n = 2;
        while (static::withTrashed()->where('slug', $slug)->exists() || in_array($slug, static::RESERVED_SLUGS, true)) {
            $slug = $base.'-'.$n++;
        }

        return $slug;
    }

    /** The permanent reference shown beside the name, for example C-12. */
    public function getCodeAttribute(): string
    {
        return 'C-'.$this->id;
    }

    /** The name in the main language (used in the admin lists, whatever language the admin works in). */
    public function mainName(): string
    {
        return (string) ($this->getTranslation('name', config('infofluxes.default_locale'), false) ?: $this->getTranslation('name', config('infofluxes.default_locale')));
    }

    /** The ids of this category and everything below it. */
    public function descendantIds(): array
    {
        $ids = [$this->id];
        foreach (static::withTrashed()->where('parent_id', $this->id)->pluck('id') as $childId) {
            $ids = array_merge($ids, static::withTrashed()->find($childId)->descendantIds());
        }

        return $ids;
    }

    /**
     * Put the tree in order: sets tree_path (so a list sorted by it shows each category right under its parent)
     * and depth. Uses plain queries, so it does not start itself again.
     */
    public static function rebuildPaths(): void
    {
        $all = static::query()->get(['id', 'parent_id', 'sort_order'])->groupBy(fn ($c) => $c->parent_id ?? 0);
        $updates = [];

        $walk = function (int $parent, string $prefix, int $depth) use (&$walk, $all, &$updates) {
            foreach (($all->get($parent) ?? collect())->sortBy(fn ($c) => sprintf('%08d-%010d', $c->sort_order, $c->id)) as $c) {
                $path = ($prefix === '' ? '' : $prefix.'/').sprintf('%08d-%010d', $c->sort_order, $c->id);
                $updates[$c->id] = [$path, $depth];
                if ($depth < 12) {
                    $walk($c->id, $path, $depth + 1);
                }
            }
        };
        $walk(0, '', 0);

        foreach ($updates as $id => [$path, $depth]) {
            DB::table('categories')->where('id', $id)->update(['tree_path' => $path, 'depth' => $depth]);
        }
    }

    /** "Shows > Popular Shows" for a category, from one shared list of categories. */
    public static function pathFor(Category $category, $all): string
    {
        $names = [$category->mainName()];
        $node = $category;
        $guard = 0;
        while ($node->parent_id && isset($all[$node->parent_id]) && $guard++ < 12) {
            $node = $all[$node->parent_id];
            array_unshift($names, $node->mainName());
        }

        return implode(' > ', $names);
    }

    /** A last-level category: nothing sits below it. An article may only be placed in one of these. */
    public function isLeaf(): bool
    {
        return ! static::query()->where('parent_id', $this->id)->exists();
    }

    /** Does an article sit directly in this category (as its main or an additional category)? Trashed articles do not count. */
    public function holdsArticles(): bool
    {
        return Article::query()->where('category_id', $this->id)->exists()
            || Article::query()->whereHas('extraCategories', fn ($q) => $q->where('categories.id', $this->id))->exists();
    }

    /** @return list<int> ids of the last-level categories */
    public static function leafIds(): array
    {
        $parents = static::query()->whereNotNull('parent_id')->pluck('parent_id')->unique()->all();

        return static::query()->whereNotIn('id', $parents)->pluck('id')->all();
    }

    /** [id => "About Islam > Allah (SWT) Index > ... (C-12)"] of the last-level categories only: where an article may be placed. */
    public static function leafChoices(): array
    {
        // A section with tabs (Stories in pictures) is only for stories, so articles never go into it.
        return array_intersect_key(static::choices(), array_flip(array_diff(static::leafIds(), static::storyOnlyIds())));
    }

    /** The ids of the categories that have "Show its sub-categories as tabs" switched on. */
    public static function tabRootIds(): array
    {
        return static::query()->get(['id', 'custom_data'])->filter(fn (Category $c) => (bool) ($c->custom_data['topic_tabs'] ?? false))->pluck('id')->all();
    }

    /** Everything inside a section with tabs, the section included: these categories hold stories only. */
    public static function storyOnlyIds(): array
    {
        $ids = [];
        foreach (static::query()->whereIn('id', static::tabRootIds())->get() as $root) {
            $ids = array_merge($ids, $root->descendantIds());
        }

        return array_values(array_unique($ids));
    }

    /**
     * [id => label] of the categories where a story may be placed: the last-level ones, and the tabs of a section with tabs.
     * The section itself (for example Stories in pictures) is not offered, because a story there would belong to no tab.
     */
    public static function storyChoices(): array
    {
        $all = static::query()->get(['id', 'parent_id'])->keyBy('id');
        $roots = static::tabRootIds();
        $parents = $all->pluck('parent_id')->filter()->unique()->all();
        $ok = [];
        foreach ($all as $c) {
            if (in_array($c->id, $roots, true)) {
                continue;
            }
            if (! in_array($c->id, $parents, true) || in_array($c->parent_id, $roots, true)) {
                $ok[] = $c->id;
            }
        }

        return array_intersect_key(static::choices(), array_flip($ok));
    }

    /** The same for the visitors' forms: only visible last-level categories, without the code. */
    public static function publicLeafChoices(): array
    {
        return array_intersect_key(static::publicChoices(), array_flip(static::leafIds()));
    }

    /**
     * Some last-level categories below this one, as text for a message ("A > B (C-12); A > C (C-13)").
     *
     * @return list<string>
     */
    public function leafExamples(int $limit = 6): array
    {
        $ids = array_values(array_diff($this->descendantIds(), [$this->id]));

        return array_slice(array_values(array_intersect_key(static::leafChoices(), array_flip($ids))), 0, $limit);
    }

    /** [id => "Shows > Popular Shows"] for the visitors' search box: only categories that are visible and sit inside visible ones. */
    public static function publicChoices(?int $maxDepth = null): array
    {
        $all = static::query()->orderBy('tree_path')->get()->keyBy('id');
        $visible = function (Category $c) use (&$visible, $all): bool {
            return $c->is_visible && (! $c->parent_id || (isset($all[$c->parent_id]) && $visible($all[$c->parent_id])));
        };
        $out = [];
        foreach ($all as $category) {
            if (($maxDepth === null || $category->depth <= $maxDepth) && $visible($category)) {
                $out[$category->id] = static::pathFor($category, $all);
            }
        }

        return $out;
    }

    /** [id => "Shows > Popular Shows (C-2)"] for drop-down boxes, in tree order, optionally leaving out one branch. */
    public static function choices(?int $exceptBranchOf = null): array
    {
        $all = static::query()->orderBy('tree_path')->get()->keyBy('id');
        $skip = $exceptBranchOf && $all->has($exceptBranchOf) ? $all[$exceptBranchOf]->descendantIds() : [];
        $out = [];
        foreach ($all as $category) {
            if (! in_array($category->id, $skip, true)) {
                $out[$category->id] = static::pathFor($category, $all).' ('.$category->code.')';
            }
        }

        return $out;
    }
}
