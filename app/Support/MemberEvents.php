<?php

namespace App\Support;

use App\Models\Article;
use App\Models\Comment;
use App\Models\Story;
use Illuminate\Database\Eloquent\Model;

/**
 * Makes the notices of the members when something happens to what they did:
 * a comment of theirs is approved or hidden, someone replies to it, an article they sent in is published or sent back,
 * and (news) a new article or story appears in something they follow.
 */
class MemberEvents
{
    public static function register(): void
    {
        Comment::updated(function (Comment $comment) {
            if (! $comment->wasChanged('status')) {
                return;
            }
            $from = $comment->getOriginal('status');
            if ($comment->status === 'approved' && $from !== 'approved') {
                static::commentApproved($comment);
            } elseif ($comment->status === 'hidden' && $from === 'pending') {
                Notify::to($comment->user, 'comment_hidden', __('account.note_comment_hidden', ['title' => static::titleOf($comment)]), static::urlOf($comment), $comment);
            }
        });
        // A comment from staff is approved at once: its reply notice is made when it is created.
        Comment::created(function (Comment $comment) {
            if ($comment->status === 'approved') {
                static::replyNotice($comment);
            }
        });

        Article::updated(fn (Article $a) => static::articleChanged($a));
        Article::created(fn (Article $a) => static::becamePublished($a));
        Story::updated(function (Story $s) {
            if ($s->wasChanged('status') && $s->status === 'published') {
                static::becamePublished($s);
            }
        });
        Story::created(fn (Story $s) => static::becamePublished($s));
    }

    private static function commentApproved(Comment $comment): void
    {
        Notify::to($comment->user, 'comment_approved', __('account.note_comment_approved', ['title' => static::titleOf($comment)]), static::urlOf($comment), $comment);
        static::replyNotice($comment);
    }

    /** Someone replied to a comment: its writer is told (not when they replied to themselves). */
    private static function replyNotice(Comment $comment): void
    {
        if (! $comment->parent_id) {
            return;
        }
        $parent = Comment::query()->with('user')->find($comment->parent_id);
        if ($parent?->user && (int) $parent->user_id !== (int) $comment->user_id) {
            Notify::to($parent->user, 'comment_reply', __('account.note_comment_reply', ['title' => static::titleOf($comment)]), static::urlOf($comment), $comment);
        }
    }

    private static function articleChanged(Article $article): void
    {
        if (! $article->wasChanged('status')) {
            return;
        }
        $from = $article->getOriginal('status');
        $author = $article->author;

        if ($article->status === 'published' && $from !== 'published') {
            if ($author && ! $author->isStaff()) {
                Notify::to($author, 'article_published', __('account.note_article_published', ['title' => $article->mainTitle()]), route('article.show', $article->slug), $article);
            }
            static::becamePublished($article);
        } elseif ($from === 'in_review' && in_array($article->status, ['draft', 'written'], true) && $author && ! $author->isStaff()) {
            Notify::to($author, 'article_sent_back', __('account.note_article_sent_back', ['title' => $article->mainTitle()]), route('submit.edit', $article->id), $article);
        }
    }

    /** News for the followers, when the page can really be seen by visitors now (not a draft, not a date in the future). */
    private static function becamePublished(Model $item): void
    {
        if ($item->status !== 'published' || ! $item::query()->published()->whereKey($item->getKey())->exists()) {
            return;
        }
        Notify::announce($item);
    }

    private static function titleOf(Comment $comment): string
    {
        $item = $comment->article ?: $comment->story;

        return $item ? $item->mainTitle() : '';
    }

    private static function urlOf(Comment $comment): string
    {
        if ($comment->article) {
            return route('article.show', $comment->article->slug).'#comments';
        }

        return $comment->story ? $comment->story->url().'#comments' : url('/');
    }
}
