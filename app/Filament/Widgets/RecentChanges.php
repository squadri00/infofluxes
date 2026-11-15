<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\History\ActivityResource;
use App\Models\Activity;
use Filament\Widgets\Widget;

/** "Recently changed": the last changes and who made them (from the History). Only the super user. */
class RecentChanges extends Widget
{
    protected string $view = 'filament.widgets.recent-changes';

    protected static ?int $sort = 4;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->isSuperuser() ?? false;
    }

    protected function getViewData(): array
    {
        $lines = Activity::query()->with('user')->latest('id')->limit(8)->get()->map(fn (Activity $a) => [
            'when' => $a->created_at?->diffForHumans(),
            'who' => $a->who(),
            'what' => Activity::EVENTS[$a->event] ?? $a->event,
            'kind' => $a->subjectName(),
            'record' => $a->subject_label,
            'url' => ActivityResource::recordUrl($a),
        ])->all();

        return ['lines' => $lines, 'historyUrl' => ActivityResource::getUrl('index')];
    }
}
