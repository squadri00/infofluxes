<?php

namespace App\Support;

use App\Models\Article;
use App\Models\User;

/**
 * Who writes which subject. Every staff member (super user, moderator, author) may see the subjects that still need writing,
 * take a free one, or give a free one to another person. A subject somebody already has can only be handed back or given on by
 * that person, a moderator or the super user, so two people never write the same topic.
 */
class TopicAssignments
{
    /** May this person change who has this subject? A free subject: any staff member. A taken one: the person who has it, a moderator, the super user. */
    public static function canChange(Article $article, ?User $by): bool
    {
        if (! $by || ! $by->isStaff() || ! $by->canUseScreen('articles')) {
            return false;
        }

        return $article->assigned_to === null || (int) $article->assigned_to === $by->id || $by->canPublish();
    }

    /** The people a subject can be given to: active staff. @return array<int, string> */
    public static function people(): array
    {
        return User::query()->where('status', 'active')->whereIn('role', ['superuser', 'moderator', 'author'])->orderBy('name')->pluck('name', 'id')->all();
    }

    /**
     * Give the subject to a person (null takes it back). The person also becomes its author, so it shows in their own list
     * and they may edit it; an author who was put there by someone else is not replaced.
     */
    public static function assign(Article $article, ?int $userId, ?User $by): bool
    {
        if (! static::canChange($article, $by)) {
            return false;
        }
        $previous = $article->assigned_to;
        $article->assigned_to = $userId;
        $article->assigned_at = $userId ? now() : null;
        if ($userId && ($article->author_id === null || (int) $article->author_id === (int) $previous)) {
            $article->author_id = $userId;
        }
        if (! $userId && $previous && (int) $article->author_id === (int) $previous) {
            $article->author_id = null;
        }
        $article->save();

        return true;
    }
}
