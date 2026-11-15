<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Articles\ArticleResource;
use App\Filament\Resources\Comments\CommentResource;
use App\Models\Article;
use App\Models\Comment;
use Filament\Widgets\Widget;

/** "Waiting for you": what needs a decision. Each number opens its list. A moderator sees everything; an author only their own articles. */
class WaitingForYou extends Widget
{
    protected string $view = 'filament.widgets.tiles';

    protected static ?int $sort = 1;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->isStaff() ?? false;
    }

    protected function getViewData(): array
    {
        $user = auth()->user();
        $all = $user->canPublish();
        $mine = fn ($query) => $all ? $query : $query->where('author_id', $user->id);
        $tiles = [];

        if ($user->canUseScreen('articles')) {
            $tiles[] = [
                'label' => $all ? 'Written, waiting for review' : 'Your articles, written and waiting',
                'count' => $mine(Article::query()->where('status', 'written'))->count(),
                'url' => ArticleResource::getUrl('index', ['filters' => ['status' => ['value' => 'written']]]),
                'tone' => 'warn',
                'hint' => 'Text from the AI import. Check it and publish.',
            ];
            $tiles[] = [
                'label' => $all ? 'Sent in, waiting for a moderator' : 'Your articles in review',
                'count' => $mine(Article::query()->where('status', 'in_review'))->count(),
                'url' => ArticleResource::getUrl('index', ['filters' => ['status' => ['value' => 'in_review']]]),
                'tone' => 'warn',
                'hint' => 'Articles sent in from the site.',
            ];
        }
        if ($user->canUseScreen('comments')) {
            $tiles[] = [
                'label' => 'Comments waiting for approval',
                'count' => Comment::query()->where('status', 'pending')->count(),
                'url' => CommentResource::getUrl('index'),
                'tone' => 'warn',
                'hint' => 'Nothing shows to visitors until you approve it.',
            ];
        }

        return ['heading' => 'Waiting for you', 'tiles' => $tiles, 'empty' => 'Nothing is waiting for you.'];
    }
}
