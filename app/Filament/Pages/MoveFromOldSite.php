<?php

namespace App\Filament\Pages;

use App\Support\OldSiteImport;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * "Move from the old site": brings the categories and the subject indexes of the old InfoFluxes across.
 * Only the super user sees it, and only on a computer that still has the old database. Check first, then Move.
 */
class MoveFromOldSite extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowDownTray;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Move from the old site';

    protected static ?string $title = 'Move from the old site';

    protected string $view = 'filament.pages.move-from-old-site';

    /** @var array<string, mixed>|null */
    public ?array $result = null;

    public bool $done = false;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->isSuperuser() && OldSiteImport::available();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function check(): void
    {
        $this->result = (new OldSiteImport)->check();
        $this->done = false;
    }

    public function move(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->result = (new OldSiteImport)->move(auth()->user());
        $this->done = true;
        Notification::make()->success()->title('Done.')
            ->body($this->result['total']['categories_new'].' new categories and '.$this->result['total']['articles_new'].' draft articles were made.')->send();
    }

    public static function names(): array
    {
        return [
            'headings' => 'Main menu headings',
            'quran' => 'Quran Index', 'allah' => 'Allah (SWT) Index', 'sharia' => 'Sharia Index',
            'history' => 'History Index', 'science' => 'Science and Knowledge Index',
            'bible' => 'Bible Index', 'hinduism' => 'Hinduism Index', 'society' => 'Society and World Affairs Index',
        ];
    }
}
