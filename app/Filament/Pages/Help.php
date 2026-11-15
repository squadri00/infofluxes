<?php

namespace App\Filament\Pages;

use App\Support\Guide;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/** Help: a guide to every screen of the admin, in simple English. Every staff person sees the topics of the screens they may use. */
class Help extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQuestionMarkCircle;

    protected static string|UnitEnum|null $navigationGroup = null;

    protected static ?string $navigationLabel = 'Help';

    protected static ?string $title = 'Help';

    protected static ?string $slug = 'help';

    protected static ?int $navigationSort = 1000;

    protected string $view = 'filament.pages.help';

    /** Help has its own button at the top of every screen, so it is not repeated in the side menu. */
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->isStaff() ?? false;
    }

    /** @return array<string, list<array>> */
    public function groups(): array
    {
        return Guide::for(auth()->user());
    }

    public function urlOf(array $entry): ?string
    {
        return Guide::url($entry);
    }
}
