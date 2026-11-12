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
    use HasTranslations, SoftDeletes;

    protected $guarded = [];

    /** Words that are already web addresses of the site and cannot be used for a category. */
    public const RESERVED_SLUGS = ['admin', 'login', 'logout', 'register', 'search', 'category', 'article', 'storage', 'livewire', 'up', 'profile', 'comments', 'submit', 'id'];

    public array $translatable = ['name', 'image_alt', 'description', 'header_text', 'page_title', 'meta_description'];

    protected function casts(): array
    {
        return ['is_visible' => 'boolean', 'custom_data' => 'array'];
    }

    protected static function booted(): void
    {
        $rebuild = fn () => static::rebuildPaths();
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
        return Article::query()->where('category_id', $this->id)->orWhereHas('extraCategories', fn ($q) => $q->where('categories.id', $this->id))->count();
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
