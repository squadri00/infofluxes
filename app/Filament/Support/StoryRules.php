<?php

namespace App\Filament\Support;

use App\Models\Category;
use App\Models\Story;
use Illuminate\Support\Str;

/** The few rules applied whenever a Visual Story is saved from the admin (create and edit share them). The same rules as for an article. */
class StoryRules
{
    /**
     * @param  array<string, mixed>  $data  the form data
     * @param  ?string  $previousStatus  the status before this save (null for a new story)
     */
    public static function apply(array $data, ?string $previousStatus = null, ?string $currentSlug = null, bool $aiAssisted = false): array
    {
        $role = auth()->user()?->role;
        $canPublish = in_array($role, ['superuser', 'moderator'], true);
        $status = $data['status'] ?? 'draft';

        // An author cannot publish or archive. A published story stays as it was.
        if (! $canPublish && in_array($status, ['published', 'archived'], true)) {
            $status = $previousStatus && in_array($previousStatus, ['published', 'archived'], true) ? $previousStatus : 'draft';
        }
        // A story written with AI help is published only after a person writes their name in "Reviewer".
        if ($status === 'published' && ($aiAssisted || ! empty($data['ai_assisted'])) && blank($data['reviewer_name'] ?? null) && $previousStatus !== 'published') {
            \Filament\Notifications\Notification::make()->danger()->title('Not published')->body('This story was written with AI help. Write the name of the person who read it in "Reviewer", then publish.')->persistent()->send();
            $status = $previousStatus && $previousStatus !== 'published' ? $previousStatus : 'draft';
        }
        $data['status'] = $status;

        // Only a moderator or the super user features a story.
        if (! $canPublish) {
            unset($data['featured_order'], $data['featured_master_order']);
            if ($currentSlug) {
                unset($data['is_featured'], $data['is_featured_master']);
            } else {
                $data['is_featured'] = false;
                $data['is_featured_master'] = false;
            }
        }

        // An author's story is always the author's own.
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
            // On Edit an empty address keeps the one the story has. Only a new story gets one made from its title.
            $data['slug'] = $currentSlug ?: Story::uniqueSlug((string) ($data['title'][config('infofluxes.default_locale')] ?? ''));
        } else {
            $data['slug'] = Str::slug((string) $data['slug']) ?: Story::uniqueSlug((string) ($data['title'][config('infofluxes.default_locale')] ?? ''));
        }

        return $data;
    }
}
