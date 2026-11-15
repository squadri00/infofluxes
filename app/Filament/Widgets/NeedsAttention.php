<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\Backups;
use App\Filament\Pages\LinkCheckerPage;
use App\Filament\Resources\Articles\ArticleResource;
use App\Models\Article;
use App\Models\LinkCheck;
use App\Models\Setting;
use Filament\Widgets\Widget;

/** "Needs attention": problems that are not a decision but should be fixed. Only the problems that exist are shown. Moderator and super user. */
class NeedsAttention extends Widget
{
    /** An article that has been "Written" for longer than this many days is called forgotten. */
    public const STALE_DAYS = 14;

    protected string $view = 'filament.widgets.tiles';

    protected static ?int $sort = 2;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->canPublish() ?? false;
    }

    protected function getViewData(): array
    {
        $tiles = [];
        $add = function (string $label, int $count, string $url, string $hint, string $tone = 'bad') use (&$tiles) {
            if ($count > 0) {
                $tiles[] = compact('label', 'count', 'url', 'hint', 'tone');
            }
        };

        $broken = LinkCheck::query()->broken()->count();
        $add('Broken source links', $broken, LinkCheckerPage::getUrl(), 'A source no longer opens. Find a new link.');

        $blocked = LinkCheck::query()->where('ignored', false)->where('status', 'blocked')->whereNull('manual_ok_at')->count();
        $add('Source links to open yourself', $blocked, LinkCheckerPage::getUrl(), 'The website refuses automatic checks.', 'warn');

        $forgotten = Article::query()->where('status', 'written')->where('updated_at', '<', now()->subDays(self::STALE_DAYS))->count();
        $add('Written, not reviewed for '.self::STALE_DAYS.'+ days', $forgotten, ArticleResource::getUrl('index', ['filters' => ['status' => ['value' => 'written']]]), 'Review them or send them back to Draft.', 'warn');

        $noReviewer = Article::query()->where('status', 'published')->where('ai_assisted', true)->where(fn ($q) => $q->whereNull('reviewer_name')->orWhere('reviewer_name', ''))->count();
        $add('Published with AI help, no reviewer name', $noReviewer, ArticleResource::getUrl('index', ['filters' => ['status' => ['value' => 'published']]]), 'Open each one and write the name of the person who checked it.');

        if (auth()->user()->isSuperuser()) {
            $problem = (string) Setting::get('backup_last_errors', '');
            $add('The last automatic backup had a problem', $problem !== '' ? 1 : 0, Backups::getUrl(), mb_substr($problem, 0, 90));
        }

        return ['heading' => 'Needs attention', 'tiles' => $tiles, 'empty' => 'Nothing needs attention. All is well.'];
    }
}
