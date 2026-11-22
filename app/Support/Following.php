<?php

namespace App\Support;

use App\Models\Category;
use App\Models\Follow;
use App\Models\Person;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/** What a member follows: a category (with everything below it), a contributor, or a word. */
class Following
{
    public static function on(): bool
    {
        return SiteControls::on('follow');
    }

    public static function clean(string $word): string
    {
        return mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $word)));
    }

    public static function is(?User $user, string $kind, int|string|null $target): bool
    {
        if (! $user) {
            return false;
        }
        $q = Follow::query()->where('user_id', $user->id)->where('kind', $kind);

        return ($kind === 'keyword' ? $q->where('keyword', static::clean((string) $target)) : $q->where('target_id', (int) $target))->exists();
    }

    /**
     * Follow, or stop following when it is followed already.
     *
     * @return bool|null true = now followed, false = not followed any more, null = not allowed (a made-up target, an empty word, or too many)
     */
    public static function toggle(User $user, string $kind, int|string $target): ?bool
    {
        if (! in_array($kind, Follow::KINDS, true)) {
            return null;
        }
        $keyword = null;
        $id = null;
        if ($kind === 'keyword') {
            $keyword = static::clean((string) $target);
            if ($keyword === '' || mb_strlen($keyword) > 60) {
                return null;
            }
        } else {
            $id = (int) $target;
            $exists = $kind === 'category' ? Category::query()->where('is_visible', true)->whereKey($id)->exists() : Person::query()->whereKey($id)->exists();
            if (! $exists) {
                return null;
            }
        }

        $row = Follow::query()->where('user_id', $user->id)->where('kind', $kind)->where('target_id', $id)->where('keyword', $keyword)->first();
        if ($row) {
            $row->delete();

            return false;
        }
        if (Follow::query()->where('user_id', $user->id)->count() >= Follow::MAX) {
            return null;
        }
        Follow::create(['user_id' => $user->id, 'kind' => $kind, 'target_id' => $id, 'keyword' => $keyword]);

        return true;
    }

    /** The ids of the members who follow something that this article or story belongs to. @return Collection<int, int> */
    public static function followersOf(Model $item): Collection
    {
        $categoryIds = collect([$item->category_id])->merge($item->extraCategories()->pluck('categories.id'))->filter()->unique();
        // Following a category means following everything below it: so the category itself and every category above it count.
        $above = collect();
        foreach ($categoryIds as $id) {
            $node = Category::query()->find($id);
            $guard = 0;
            while ($node && $guard++ < 12) {
                $above->push($node->id);
                $node = $node->parent_id ? Category::query()->find($node->parent_id) : null;
            }
        }
        $users = Follow::query()->where('kind', 'category')->whereIn('target_id', $above->unique())->pluck('user_id');

        if ($item->contributor_id) {
            $users = $users->merge(Follow::query()->where('kind', 'person')->where('target_id', $item->contributor_id)->pluck('user_id'));
        }
        $words = collect((array) $item->search_words)->map(fn ($w) => static::clean((string) $w))->filter()->unique();
        if ($words->isNotEmpty()) {
            $users = $users->merge(Follow::query()->where('kind', 'keyword')->whereIn('keyword', $words)->pluck('user_id'));
        }

        return $users->unique()->values();
    }
}
