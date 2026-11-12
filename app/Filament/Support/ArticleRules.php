<?php

namespace App\Filament\Support;

use App\Models\Article;
use App\Models\Category;
use Illuminate\Support\Str;

/** The few rules applied whenever an article is saved from the admin (create and edit share them). */
class ArticleRules
{
    /**
     * @param  array<string, mixed>  $data  the form data
     * @param  ?string  $previousStatus  the status before this save (null for a new article)
     */
    public static function apply(array $data, ?string $previousStatus = null, ?string $currentSlug = null): array
    {
        $role = auth()->user()?->role;
        $canPublish = in_array($role, ['superuser', 'moderator'], true);
        $status = $data['status'] ?? 'draft';

        // An author cannot publish or archive. A published article stays as it was.
        if (! $canPublish && in_array($status, ['published', 'archived'], true)) {
            $status = $previousStatus && in_array($previousStatus, ['published', 'archived'], true) ? $previousStatus : 'draft';
        }
        $data['status'] = $status;

        // An author's article is always the author's own: the author cannot be changed or set to someone else.
        if (! $canPublish) {
            if ($currentSlug) {
                unset($data['author_id']);
            } else {
                $data['author_id'] = auth()->id();
            }
        }

        // Publishing without a date means: now.
        if ($status === 'published' && blank($data['publish_date'] ?? null)) {
            $data['publish_date'] = now();
        }

        if (blank($data['slug'] ?? null)) {
            // On Edit an empty address keeps the one the article has. Only a new article gets one made from its title.
            if ($currentSlug) {
                $data['slug'] = $currentSlug;
            } else {
                $base = Str::slug((string) ($data['title'][config('infofluxes.default_locale')] ?? '')) ?: 'article';
                $slug = $base;
                $n = 2;
                while (Article::withTrashed()->where('slug', $slug)->exists() || in_array($slug, Category::RESERVED_SLUGS, true)) {
                    $slug = $base.'-'.$n++;
                }
                $data['slug'] = $slug;
            }
        }

        return $data;
    }
}
