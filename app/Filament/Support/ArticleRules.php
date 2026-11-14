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
    public static function apply(array $data, ?string $previousStatus = null, ?string $currentSlug = null, bool $aiAssisted = false, ?Article $record = null): array
    {
        // The form holds only some keys of custom_data. The others (sources, the AI's check points) are kept as they are.
        if ($record) {
            $data['custom_data'] = array_merge((array) $record->custom_data, (array) ($data['custom_data'] ?? []));
        }
        $role = auth()->user()?->role;
        $canPublish = in_array($role, ['superuser', 'moderator'], true);
        $status = $data['status'] ?? 'draft';

        // An author cannot publish or archive. A published article stays as it was.
        if (! $canPublish && in_array($status, ['published', 'archived'], true)) {
            $status = $previousStatus && in_array($previousStatus, ['published', 'archived'], true) ? $previousStatus : 'draft';
        }
        // An article written with AI help is published only after a person writes their name in "Reviewer".
        if ($status === 'published' && ($aiAssisted || ! empty($data['ai_assisted'])) && blank($data['reviewer_name'] ?? null) && $previousStatus !== 'published') {
            \Filament\Notifications\Notification::make()->danger()->title('Not published')->body('This article was written with AI help. Write the name of the person who read it in "Reviewer", then publish.')->persistent()->send();
            $status = $previousStatus && $previousStatus !== 'published' ? $previousStatus : 'draft';
        }
        // The review checklist: every line ticked before an article written with AI help goes live.
        if ($status === 'published' && ($aiAssisted || ! empty($data['ai_assisted'])) && $previousStatus !== 'published' && $record) {
            $left = \App\Support\ReviewChecklist::left((array) ($data['custom_data'] ?? []));
            if ($left !== []) {
                \Filament\Notifications\Notification::make()->danger()->title(__('review.blocked_title'))->body(__('review.blocked_body', ['left' => count($left)]))->persistent()->send();
                $status = $previousStatus && $previousStatus !== 'published' ? $previousStatus : 'draft';
            } else {
                // who finished the review, and when
                $data['custom_data']['review']['reviewed_by'] = $data['reviewer_name'] ?? null;
                $data['custom_data']['review']['reviewed_at'] = now()->toDateTimeString();
            }
        }
        $data['status'] = $status;
        // Only a moderator or the super user features an article. Anyone else changes nothing there (and a new article is not featured).
        if (! $canPublish) {
            unset($data['featured_order']);
            if ($currentSlug) {
                unset($data['is_featured']);
            } else {
                $data['is_featured'] = false;
            }
        }

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
