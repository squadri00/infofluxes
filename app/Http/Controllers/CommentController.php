<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Comment;
use App\Models\Setting;
use App\Models\Story;
use Illuminate\Http\Request;

class CommentController extends Controller
{
    /**
     * A comment belongs to an article or to a story (the "Audience" under a story).
     * A member's comment waits for a moderator. A comment from staff is shown at once.
     */
    public function store(Request $request)
    {
        abort_unless((bool) Setting::get('comments_open', true), 403);
        $user = auth()->user();
        abort_unless($user->isActive(), 403);

        $data = $request->validate([
            'article_id' => ['nullable', 'integer', 'required_without:story_id'],
            'story_id' => ['nullable', 'integer', 'required_without:article_id'],
            'parent_id' => ['nullable', 'integer'],
            'body' => ['required', 'string', 'min:2', 'max:2000'],
        ]);

        $story = null;
        $article = null;
        if (! empty($data['story_id'])) {
            $story = Story::published()->findOrFail($data['story_id']);
        } else {
            $article = Article::published()->findOrFail($data['article_id']);
        }

        $parentId = null;
        if (! empty($data['parent_id'])) {
            // A reply goes under a first comment of this same article or story that people can see.
            $parent = Comment::query()
                ->when($story, fn ($q) => $q->where('story_id', $story->id), fn ($q) => $q->where('article_id', $article->id))
                ->whereNull('parent_id')->where('status', 'approved')->findOrFail($data['parent_id']);
            $parentId = $parent->id;
        }

        $staff = $user->isStaff();
        Comment::create([
            'article_id' => $article?->id,
            'story_id' => $story?->id,
            'user_id' => $user->id,
            'parent_id' => $parentId,
            'body' => trim($data['body']),
            'status' => $staff ? 'approved' : 'pending',
        ]);

        $to = $story ? $story->url() : route('article.show', $article->slug);

        return redirect($to.'#comments')->with('status', $staff ? __('site.comment_posted') : __('site.comment_sent'));
    }
}
