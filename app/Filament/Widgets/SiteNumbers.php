<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Articles\ArticleResource;
use App\Filament\Resources\Categories\CategoryResource;
use App\Filament\Resources\Comments\CommentResource;
use App\Filament\Resources\DidYouKnow\DidYouKnowResource;
use App\Filament\Resources\Newsletter\NewsletterSubscriberResource;
use App\Filament\Resources\Stories\StoryResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\Article;
use App\Models\Category;
use App\Models\Comment;
use App\Models\DidYouKnow;
use App\Models\NewsletterSubscriber;
use App\Models\Story;
use App\Models\User;
use Filament\Widgets\Widget;

/** "Site numbers": how big the site is, and how far the index is written. Moderator and super user. */
class SiteNumbers extends Widget
{
    protected string $view = 'filament.widgets.tiles';

    protected static ?int $sort = 3;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->canPublish() ?? false;
    }

    protected function getViewData(): array
    {
        $locale = config('infofluxes.default_locale');
        $planned = Article::query()->where('is_index_line', true)->where('status', '!=', 'archived');
        $written = (clone $planned)->whereNotNull("long_detail->{$locale}")->where("long_detail->{$locale}", '!=', '');
        $planCount = (clone $planned)->count();
        $doneCount = $written->count();
        $percent = $planCount > 0 ? round(100 * $doneCount / $planCount, 1) : 0;

        $tiles = [
            ['label' => 'Index entries written', 'count' => $doneCount, 'display' => number_format($doneCount).' of '.number_format($planCount), 'hint' => $percent.'% of the planned index has text.', 'url' => ArticleResource::getUrl('index', ['filters' => ['status' => ['value' => 'draft']]]), 'tone' => 'good'],
            ['label' => 'Published articles', 'count' => Article::query()->where('status', 'published')->count(), 'url' => ArticleResource::getUrl('index', ['filters' => ['status' => ['value' => 'published']]]), 'hint' => 'Visible to visitors.'],
            ['label' => 'Visual stories', 'count' => Story::query()->where('status', 'published')->count(), 'url' => StoryResource::getUrl('index'), 'hint' => 'Published.'],
            ['label' => 'Did You Know? items', 'count' => DidYouKnow::query()->where('status', 'published')->count(), 'url' => DidYouKnowResource::getUrl('index'), 'hint' => 'Published.'],
            ['label' => 'Categories', 'count' => Category::query()->count(), 'url' => CategoryResource::getUrl('index'), 'hint' => count(Category::leafIds()).' last-level.'],
            ['label' => 'Members', 'count' => User::query()->where('role', 'member')->where('status', 'active')->count(), 'url' => auth()->user()->isSuperuser() ? UserResource::getUrl('index') : ArticleResource::getUrl('index'), 'hint' => 'People who signed up.'],
            ['label' => 'Comments', 'count' => Comment::query()->where('status', 'approved')->count(), 'url' => CommentResource::getUrl('index'), 'hint' => 'Approved.'],
        ];
        if (class_exists(NewsletterSubscriber::class)) {
            $tiles[] = ['label' => 'Newsletter subscribers', 'count' => NewsletterSubscriber::query()->count(), 'url' => NewsletterSubscriberResource::getUrl('index'), 'hint' => 'All who subscribed.'];
        }

        return ['heading' => 'Site numbers', 'tiles' => $tiles, 'empty' => ''];
    }
}
