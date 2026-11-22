<?php

namespace App\Support;

use App\Models\MemberNotification;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/** Notices for members: made here, shown by the bell and the Notifications page, and (news only) in the weekly email. */
class Notify
{
    public static function on(): bool
    {
        return SiteControls::on('notifications');
    }

    public static function to(?User $user, string $type, string $title, ?string $url = null, ?Model $subject = null): ?MemberNotification
    {
        if (! $user || ! static::on() || ! $user->isActive()) {
            return null;
        }
        // The same news about the same page is told once.
        if ($subject && MemberNotification::query()->where('user_id', $user->id)->where('type', $type)->where('subject_type', $subject::class)->where('subject_id', $subject->getKey())->exists()) {
            return null;
        }

        return MemberNotification::create([
            'user_id' => $user->id, 'type' => $type, 'title' => mb_substr($title, 0, 300), 'url' => $url,
            'subject_type' => $subject ? $subject::class : null, 'subject_id' => $subject?->getKey(),
        ]);
    }

    public static function unread(?User $user): int
    {
        return $user && static::on() ? MemberNotification::query()->where('user_id', $user->id)->unread()->count() : 0;
    }

    /** News: a new article or story in something the members follow. Not told to the author. */
    public static function announce(Model $item): int
    {
        if (! static::on() || ! Following::on()) {
            return 0;
        }
        $title = method_exists($item, 'mainTitle') ? $item->mainTitle() : (string) $item->title;
        $url = $item instanceof \App\Models\Story ? $item->url() : route('article.show', $item->slug);
        $made = 0;
        foreach (User::query()->whereIn('id', Following::followersOf($item))->where('status', 'active')->get() as $user) {
            if ((int) $user->id === (int) $item->author_id) {
                continue;
            }
            if (static::to($user, 'new_work', __('account.note_new_work', ['title' => $title]), $url, $item)) {
                $made++;
            }
        }

        return $made;
    }
}
