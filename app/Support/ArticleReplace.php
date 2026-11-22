<?php

namespace App\Support;

use App\Models\Article;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * "Replace an existing article" in the AI import: the AI text goes into an article that already exists. The article keeps its ID, its web address,
 * its category, its publish date, its status (a published article stays published), its author, its pictures, its comments and its counts.
 * The old and the new text are both kept in the History (Settings > History), so the change can be put back.
 */
class ArticleReplace
{
    /** A published or archived article may be replaced only by a moderator or the super user. A draft also by its author or the person it is assigned to. */
    public static function canReplace(Article $article, ?User $user): bool
    {
        if (! $user || $article->is_index_line && IndexEntries::isBlank($article)) {
            return false;
        }
        if ($user->canPublish()) {
            return true;
        }

        return ! in_array($article->status, ['published', 'archived'], true) && ((int) $article->author_id === $user->id || (int) $article->assigned_to === $user->id);
    }

    public static function isLive(Article $article): bool
    {
        return $article->status === 'published';
    }

    /** @return Collection<int, Article> the articles the person may replace that match "A-123", "123" or words of the title (at most 12) */
    public static function search(string $term, ?User $user): Collection
    {
        $term = trim($term);
        if ($term === '' || ! $user) {
            return collect();
        }
        $query = Article::query()->with('category')->where(fn ($q) => $q->where('is_index_line', false)->orWhereNotNull('long_detail'));
        if (preg_match('/^A?-?\s*(\d+)$/i', $term, $m)) {
            $query->where('id', (int) $m[1]);
        } else {
            TextSearch::match($query, ['title'], $term);
        }

        return $query->orderByDesc('id')->limit(40)->get()->filter(fn (Article $a) => static::canReplace($a, $user))->take(12)->values();
    }

    /**
     * Put the checked AI answer into the article. $d is the "data" of a checked item (see App\Support\AiImport).
     * The reviewer's name and the review ticks are cleared: the text is new and nobody has read it yet.
     */
    public static function apply(Article $article, array $d, ?User $user): Article
    {
        $locale = config('infofluxes.default_locale');
        $custom = (array) $article->custom_data;
        unset($custom['review'], $custom['accordion'], $custom['sources'], $custom['please_check']);
        $fresh = [
            'accordion' => count(array_filter($d['outline'] ?? [], fn ($h) => $h['level'] === 2)) >= 2 ? true : null,
            'sources' => ($d['sources'] ?? []) ?: null,
            'please_check' => ($d['please_check'] ?? []) ?: null,
        ];
        foreach ($fresh as $k => $v) {
            if ($v !== null) {
                $custom[$k] = $v;
            }
        }

        $article->setTranslation('title', $locale, $d['title']);
        $article->setTranslation('short_detail', $locale, $d['short']);
        $article->setTranslation('long_detail', $locale, Explainers::applyNotes(Html::fromMarked($d['long']), $d['explainers'] ?? []));
        $article->search_words = ($d['words'] ?? []) ?: $article->search_words;
        if (! empty($d['meta_title'])) {
            $article->setTranslation('meta_title', $locale, $d['meta_title']);
        }
        if (! empty($d['meta_description'])) {
            $article->setTranslation('meta_description', $locale, $d['meta_description']);
        }
        $article->source_line = $d['source'] ?? null;
        $article->ai_assisted = true;
        $article->reviewer_name = null;
        $article->custom_data = $custom ?: null;
        $article->save();   // the slug, the category, the status and the publish date are left as they are

        if (! empty($d['extra_ids'])) {
            $article->extraCategories()->syncWithoutDetaching($d['extra_ids']);
        }
        if (! empty($d['videos'])) {
            $article->videos()->delete();
            foreach ($d['videos'] as $i => $url) {
                $article->videos()->create(['url' => $url, 'sort_order' => $i + 1]);
            }
        }

        return $article;
    }
}
