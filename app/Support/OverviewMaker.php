<?php

namespace App\Support;

use App\Models\Article;
use App\Models\Category;
use Illuminate\Support\Facades\DB;

/**
 * An article sits only in a last-level category. A heading that has sub-categories and also holds articles gets an "Overview" sub-category,
 * and its articles move into it (the main category and the additional ones). The headings keep their place in the menu.
 * Without "apply" nothing is changed.
 */
class OverviewMaker
{
    public const NAME = 'Overview';

    /**
     * @param  list<int>  $skip  ids of headings to leave alone
     * @return array{headings: list<array{id: int, name: string, overview: ?int, main: int, extra: int}>, applied: bool}
     */
    public static function run(bool $apply, array $skip = []): array
    {
        $parentIds = Category::query()->whereNotNull('parent_id')->pluck('parent_id')->unique()->all();
        $rows = [];

        foreach (Category::query()->whereIn('id', $parentIds)->whereNotIn('id', $skip)->orderBy('tree_path')->get() as $heading) {
            $main = Article::query()->where('category_id', $heading->id)->count();
            // only articles that are not in the Trash
            $live = Article::query()->pluck('id')->all();
            $extra = DB::table('article_category')->where('category_id', $heading->id)->whereIn('article_id', $live)->count();
            if ($main + $extra === 0) {
                continue;
            }
            $row = ['id' => $heading->id, 'name' => $heading->mainName(), 'overview' => null, 'main' => $main, 'extra' => $extra];

            if ($apply) {
                DB::transaction(function () use ($heading, &$row) {
                    $overview = Category::query()->where('parent_id', $heading->id)->get()->first(fn ($c) => mb_strtolower($c->mainName()) === mb_strtolower(self::NAME) && $c->isLeaf());
                    $overview ??= Category::create([
                        'name' => [config('infofluxes.default_locale') => self::NAME],
                        'slug' => Category::uniqueSlug($heading->mainName().' '.self::NAME),
                        'parent_id' => $heading->id,
                        'sort_order' => 0,
                        'is_visible' => $heading->is_visible,
                    ]);

                    Article::query()->where('category_id', $heading->id)->toBase()->update(['category_id' => $overview->id]);
                    foreach (DB::table('article_category')->where('category_id', $heading->id)->whereIn('article_id', $live)->get() as $link) {
                        $same = Article::query()->whereKey($link->article_id)->where('category_id', $overview->id)->exists()
                            || DB::table('article_category')->where('article_id', $link->article_id)->where('category_id', $overview->id)->exists();
                        if ($same) {
                            DB::table('article_category')->where('id', $link->id ?? null)->delete();
                            DB::table('article_category')->where('article_id', $link->article_id)->where('category_id', $heading->id)->delete();
                        } else {
                            DB::table('article_category')->where('article_id', $link->article_id)->where('category_id', $heading->id)->update(['category_id' => $overview->id]);
                        }
                    }
                    $row['overview'] = $overview->id;
                });
            }
            $rows[] = $row;
        }

        return ['headings' => $rows, 'applied' => $apply];
    }

    /** Articles that sit in a category with sub-categories (the ones the rule is broken for). @return \Illuminate\Support\Collection<int, Article> */
    public static function offenders()
    {
        $parentIds = Category::query()->whereNotNull('parent_id')->pluck('parent_id')->unique()->all();

        return Article::query()->whereIn('category_id', $parentIds)->with('category')->orderBy('id')->get();
    }
}
