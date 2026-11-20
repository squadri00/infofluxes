<?php

namespace App\Support;

use App\Models\Article;
use App\Models\Category;
use App\Models\Redirect;
use Illuminate\Support\Facades\DB;

/**
 * Shrinks the tree of one section to a few levels (the section itself is level 1).
 *
 * Everything that sits deeper moves up into its category on the last kept level. The names of the levels it passed are kept on each
 * article as its "Subtopic" (custom_data.subtopic, for example "Prayer (Salah) > How Prayer Is Done"), the old categories go to the
 * Trash (they can be restored), and the old web address of each one leads to the category that took over.
 *
 * Without $apply nothing is changed: it only counts.
 */
class CategoryFlattener
{
    /**
     * @return array{kept: int, removed: int, articles: int, stories: int, extra_links: int, facts: int, runs: int, applied: bool}
     */
    public static function run(Category $root, int $levels = 3, bool $apply = false): array
    {
        $levels = max(2, $levels);
        $root = Category::query()->findOrFail($root->id);   // fresh, so its tree_path is current
        $all = Category::query()->where('tree_path', 'like', $root->tree_path.'/%')->orderBy('tree_path')->get()->keyBy('id');
        $all->put($root->id, $root);

        // depth of each category (the root is 1) and the ancestor on the last kept level
        $depth = [$root->id => 1];
        $target = [$root->id => $root->id];
        $names = [$root->id => []];       // names passed below the kept level
        $pending = $all->except($root->id)->sortBy(fn ($c) => substr_count((string) $c->tree_path, '/'))->all();
        foreach ($pending as $c) {
            $parent = $c->parent_id;
            $depth[$c->id] = ($depth[$parent] ?? 1) + 1;
            if ($depth[$c->id] <= $levels) {
                $target[$c->id] = $c->id;
                $names[$c->id] = [];
            } else {
                $target[$c->id] = $target[$parent];
                $names[$c->id] = array_merge($names[$parent], [$c->mainName()]);
            }
        }
        $removeIds = array_keys(array_filter($depth, fn ($d) => $d > $levels));

        $counts = [
            'kept' => count($depth) - count($removeIds),
            'removed' => count($removeIds),
            'articles' => $removeIds ? Article::query()->whereIn('category_id', $removeIds)->count() : 0,
            'stories' => $removeIds ? DB::table('stories')->whereIn('category_id', $removeIds)->count() : 0,
            'extra_links' => $removeIds ? DB::table('article_category')->whereIn('category_id', $removeIds)->count() + DB::table('story_category')->whereIn('category_id', $removeIds)->count() : 0,
            'facts' => $removeIds ? DB::table('did_you_know_entries')->whereIn('category_id', $removeIds)->count() : 0,
            'runs' => $removeIds ? DB::table('story_runs')->whereIn('category_id', $removeIds)->count() : 0,
            'applied' => false,
        ];
        if (! $apply || ! $removeIds) {
            return $counts;
        }

        Category::$pauseRebuild = true;
        try {
            DB::transaction(function () use ($removeIds, $target, $names, $all) {
                // 1. Articles: the main category moves up, the old names become the Subtopic.
                Article::query()->whereIn('category_id', $removeIds)->select(['id', 'category_id', 'custom_data'])->orderBy('id')->chunkById(500, function ($chunk) use ($target, $names) {
                    foreach ($chunk as $a) {
                        $data = (array) $a->custom_data;
                        $path = $names[$a->category_id] ?? [];
                        if ($path && blank($data['subtopic'] ?? null)) {
                            $data['subtopic'] = implode(' > ', $path);
                        }
                        DB::table('articles')->where('id', $a->id)->update(['category_id' => $target[$a->category_id], 'custom_data' => json_encode($data, JSON_UNESCAPED_UNICODE)]);
                    }
                });

                // 2. Stories, facts and story runs just move up.
                foreach (['stories', 'did_you_know_entries', 'story_runs'] as $table) {
                    foreach (DB::table($table)->whereIn('category_id', $removeIds)->get(['id', 'category_id']) as $row) {
                        DB::table($table)->where('id', $row->id)->update(['category_id' => $target[$row->category_id]]);
                    }
                }

                // 3. "Also in" links move up too (a link that would repeat the main category or another link is dropped).
                foreach ([['article_category', 'article_id', 'articles'], ['story_category', 'story_id', 'stories']] as [$pivot, $col, $table]) {
                    foreach (DB::table($pivot)->whereIn('category_id', $removeIds)->get() as $link) {
                        $to = $target[$link->category_id];
                        $main = DB::table($table)->where('id', $link->{$col})->value('category_id');
                        $taken = DB::table($pivot)->where($col, $link->{$col})->where('category_id', $to)->exists();
                        DB::table($pivot)->where($col, $link->{$col})->where('category_id', $link->category_id)->delete();
                        if ((int) $main !== (int) $to && ! $taken) {
                            DB::table($pivot)->insert([$col => $link->{$col}, 'category_id' => $to]);
                        }
                    }
                }

                // 4. The old categories go to the Trash, the deepest first, and their old address leads to the one that took over.
                $deepest = collect($removeIds)->sortByDesc(fn ($id) => substr_count((string) $all[$id]->tree_path, '/'));
                foreach ($deepest as $id) {
                    $old = $all[$id];
                    $new = $all[$target[$id]];
                    Redirect::remember('/category/'.$old->slug, '/category/'.$new->slug, 'category', $new->id, 'Category moved up when the tree was shrunk');
                    $old->delete();
                }
            });
        } finally {
            Category::$pauseRebuild = false;
        }
        Category::rebuildPaths();

        return ['applied' => true] + $counts;
    }
}
