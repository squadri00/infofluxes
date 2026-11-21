<?php

namespace App\Support;

use App\Models\Article;
use App\Models\Category;
use Illuminate\Support\Facades\DB;

/**
 * Builds new masters under a section from a plan (database/data/about-islam-masters.php): the master categories, their topic categories (the last
 * level, which hold the articles) and the planned subjects (empty draft index lines, each with its Subtopic).
 *
 * Safe to run again: what exists is kept (a master or topic is found by its name, a subject by its title in the same topic). A planned subject
 * whose title is the same as an article that already exists in the section is not made twice: the existing article is also listed in the new topic.
 * A subject that only looks close to an existing one is made, and reported, so a person can decide. Without $apply nothing is changed.
 */
class MasterBuilder
{
    private const STOP = ['the', 'of', 'a', 'an', 'and', 'in', 'on', 'to', 'is', 'are', 'was', 'were', 'what', 'how', 'why', 'who', 'do', 'does', 'did', 'it', 'its', 'for', 'by', 'with', 'from', 'as', 'at', 'or', 'about', 'his', 'her', 'their', 'that', 'this', 'can', 'be', 'we', 'i', 'my', 'you', 'our', 'us', 'not'];

    /**
     * @param  array<string, mixed>  $plan
     * @return array{masters: int, topics: int, subjects: int, linked: int, existing: int, overlaps: int, applied: bool, rows: list<array<string, string>>}
     */
    public static function run(array $plan, bool $apply = false): array
    {
        $locale = (string) config('infofluxes.default_locale', 'en');
        $parent = Category::query()->where('slug', $plan['parent'])->firstOrFail();
        $known = static::knownTitles($parent);

        $out = ['masters' => 0, 'topics' => 0, 'subjects' => 0, 'linked' => 0, 'existing' => 0, 'overlaps' => 0, 'applied' => false, 'rows' => []];

        $work = function () use ($plan, $apply, $locale, $parent, &$known, &$out) {
            foreach ($plan['masters'] as $master) {
                $masterCat = static::findChild($parent->id, $master['name']);
                if (! $masterCat) {
                    $out['masters']++;
                    if ($apply) {
                        $masterCat = Category::create([
                            'name' => [$locale => $master['name']], 'slug' => Category::uniqueSlug($master['name']), 'parent_id' => $parent->id,
                            'description' => [$locale => $master['description'] ?? ''], 'menu_icon' => $master['icon'] ?? null, 'sort_order' => 99, 'is_visible' => true,
                        ]);
                    }
                }

                foreach ($master['topics'] as $t => $topic) {
                    $topicCat = $masterCat ? static::findChild($masterCat->id, $topic['name']) : null;
                    if (! $topicCat) {
                        $out['topics']++;
                        if ($apply) {
                            $topicCat = Category::create([
                                'name' => [$locale => $topic['name']], 'slug' => Category::uniqueSlug($topic['name']), 'parent_id' => $masterCat->id,
                                'description' => [$locale => $topic['description'] ?? ''], 'sort_order' => $t + 1, 'is_visible' => true,
                            ]);
                        }
                    }
                    $have = $topicCat ? static::titlesIn($topicCat->id) : [];

                    $n = 0;
                    foreach ($topic['groups'] as $group => $titles) {
                        foreach ($titles as $title) {
                            $n++;
                            $row = ['master' => $master['name'], 'topic' => $topic['name'], 'subtopic' => (string) $group, 'title' => $title, 'status' => 'new', 'note' => ''];
                            $norm = static::normalize($title);

                            $same = static::sameAs($plan, $title);
                            if (isset($have[$norm])) {
                                $row['status'] = 'already there';
                                $out['existing']++;
                            } elseif ($same || isset($known['exact'][$norm])) {
                                $hit = $same ?? $known['exact'][$norm];
                                $row['status'] = 'linked';
                                $row['note'] = 'Same subject as '.$hit['code'].'; the existing article is also listed here';
                                $out['linked']++;
                                if ($apply && (int) $hit['category_id'] !== (int) $topicCat->id) {
                                    DB::table('article_category')->updateOrInsert(['article_id' => $hit['id'], 'category_id' => $topicCat->id], []);
                                }
                            } else {
                                if ($near = static::nearest($norm, $known['all'])) {
                                    $row['note'] = 'Looks close to '.$near['code'].' "'.mb_substr($near['title'], 0, 70).'"';
                                    $out['overlaps']++;
                                }
                                $out['subjects']++;
                                if ($apply) {
                                    $article = Article::create([
                                        'title' => [$locale => $title], 'slug' => Article::uniqueSlug($title), 'category_id' => $topicCat->id, 'status' => 'draft',
                                        'is_index_line' => true, 'sort_order' => $n, 'custom_data' => ['subtopic' => (string) $group],
                                    ]);
                                    $known['all'][] = ['id' => $article->id, 'code' => $article->code, 'title' => $title, 'tokens' => static::tokens($norm), 'category_id' => $topicCat->id];
                                    $known['exact'][$norm] = ['id' => $article->id, 'code' => $article->code, 'category_id' => $topicCat->id];
                                }
                            }
                            $out['rows'][] = $row;
                        }
                    }
                }
            }

            if ($apply) {
                static::order($parent, $plan['order'] ?? []);
            }
        };

        if ($apply) {
            Category::$pauseRebuild = true;
            try {
                DB::transaction($work);
            } finally {
                Category::$pauseRebuild = false;
            }
            Category::rebuildPaths();
            $out['applied'] = true;
        } else {
            $work();
        }

        return $out;
    }

    /** The existing article a plan subject is the same as (from the plan's same_as list), or null. @return array<string, mixed>|null */
    private static function sameAs(array $plan, string $title): ?array
    {
        $code = $plan['same_as'][$title] ?? null;
        if (! $code || ! preg_match('/^A-?(\d+)$/i', $code, $m) || ! ($a = Article::query()->find((int) $m[1]))) {
            return null;
        }

        return ['id' => $a->id, 'code' => $a->code, 'category_id' => $a->category_id];
    }

    /** Put the masters under the section in the given order (a slug for an old one, a name for a new one). */
    private static function order(Category $parent, array $order): void
    {
        foreach ($order as $i => $key) {
            $cat = Category::query()->where('parent_id', $parent->id)->where('slug', $key)->first() ?? static::findChild($parent->id, $key);
            $cat?->update(['sort_order' => $i + 1]);
        }
    }

    private static function findChild(int $parentId, string $name): ?Category
    {
        return Category::query()->where('parent_id', $parentId)->get()->first(fn (Category $c) => mb_strtolower($c->mainName()) === mb_strtolower($name));
    }

    /** @return array<string, true> the normalized titles of the articles in one category */
    private static function titlesIn(int $categoryId): array
    {
        $titles = [];
        foreach (Article::query()->where('category_id', $categoryId)->get(['id', 'title']) as $a) {
            $titles[static::normalize($a->mainTitle())] = true;
        }

        return $titles;
    }

    /** @return array{exact: array<string, array<string, mixed>>, all: list<array<string, mixed>>} */
    private static function knownTitles(Category $parent): array
    {
        $exact = [];
        $all = [];
        foreach (Article::query()->whereIn('category_id', $parent->descendantIds())->get(['id', 'category_id', 'title']) as $a) {
            $title = $a->mainTitle();
            $norm = static::normalize($title);
            $exact[$norm] ??= ['id' => $a->id, 'code' => $a->code, 'category_id' => $a->category_id];
            $all[] = ['id' => $a->id, 'code' => $a->code, 'title' => $title, 'tokens' => static::tokens($norm), 'category_id' => $a->category_id];
        }

        return ['exact' => $exact, 'all' => $all];
    }

    /** A title in small letters with the marks (SWT) (AS) (RA) and punctuation taken away. */
    public static function normalize(string $title): string
    {
        $t = mb_strtolower($title);
        $t = str_replace(['ﷺ', '(swt)', '(as)', '(ra)', '(rah)'], ' ', $t);
        $t = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $t);

        return trim((string) preg_replace('/\s+/', ' ', $t));
    }

    /** @return list<string> the words that carry meaning */
    private static function tokens(string $norm): array
    {
        return array_values(array_unique(array_filter(explode(' ', $norm), fn ($w) => $w !== '' && ! in_array($w, self::STOP, true) && mb_strlen($w) > 2)));
    }

    /** The existing article whose words overlap most with this title, when the overlap is large. @return array<string, mixed>|null */
    private static function nearest(string $norm, array $all): ?array
    {
        $mine = static::tokens($norm);
        if (count($mine) < 3) {
            return null;
        }
        $best = null;
        $bestScore = 0.0;
        foreach ($all as $other) {
            $theirs = $other['tokens'];
            $same = count(array_intersect($mine, $theirs));
            if ($same < 3) {
                continue;
            }
            $score = $same / max(1, count(array_unique(array_merge($mine, $theirs))));
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $other;
            }
        }

        return $bestScore >= 0.6 ? $best : null;
    }
}
