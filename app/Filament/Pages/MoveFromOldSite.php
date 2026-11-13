<?php

namespace App\Filament\Pages;

use App\Support\OldSiteImport;
use App\Support\OldStoryImport;
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

    /** The category the moved stories are put in. */
    public ?int $storyCategory = null;

    /** @var array<string, mixed>|null */
    public ?array $storyResult = null;

    public bool $storyDone = false;

    /** @var array<string, mixed>|null */
    public ?array $viewerChanges = null;

    public bool $viewerDone = false;

    /** @var array<string, mixed>|null */
    public ?array $audioResult = null;

    public bool $audioDone = false;

    /** @var array<string, mixed>|null */
    public ?array $heroResult = null;

    public bool $heroDone = false;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->isSuperuser() && OldSiteImport::available();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public static function storiesAvailable(): bool
    {
        return OldStoryImport::available();
    }

    public function categoryChoices(): array
    {
        return \App\Models\Category::choices();
    }

    public function mount(): void
    {
        $this->storyCategory = \App\Models\Category::whereNull('parent_id')->get()->first(fn ($c) => $c->mainName() === 'About Islam')?->id;
    }

    public static function viewerAvailable(): bool
    {
        return \App\Support\OldSettingsImport::available();
    }

    public static function heroAvailable(): bool
    {
        return \App\Support\OldHeroImport::available();
    }

    public function checkHero(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->heroResult = (new \App\Support\OldHeroImport)->check();
        $this->heroDone = false;
    }

    public function bringHero(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->heroResult = (new \App\Support\OldHeroImport)->bring();
        $this->heroDone = true;
        Notification::make()->success()->title('The home page slider was brought across.')->send();
    }

    public function checkAudio(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->audioResult = (new OldStoryImport)->checkAudio();
        $this->audioDone = false;
    }

    public function moveAudio(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->audioResult = (new OldStoryImport)->moveAudio();
        $this->audioDone = true;
        Notification::make()->success()->title($this->audioResult['total']['audio_copied'].' audio files were copied.')->send();
    }

    public function checkViewer(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->viewerChanges = (new \App\Support\OldSettingsImport)->check();
        $this->viewerDone = false;
    }

    public function bringViewer(): void
    {
        abort_unless(static::canAccess(), 403);
        $n = (new \App\Support\OldSettingsImport)->bring();
        $this->viewerChanges = [];
        $this->viewerDone = true;
        Notification::make()->success()->title($n === 1 ? '1 choice was brought across.' : "$n choices were brought across.")->send();
    }

    public function checkStories(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->storyResult = (new OldStoryImport($this->storyCategory))->check();
        $this->storyDone = false;
    }

    public function moveStories(): void
    {
        abort_unless(static::canAccess(), 403);
        if (! $this->storyCategory || ! \App\Models\Category::find($this->storyCategory)) {
            Notification::make()->danger()->title('Choose the category for the stories first.')->send();

            return;
        }
        $this->storyResult = (new OldStoryImport($this->storyCategory))->move(auth()->user());
        $this->storyDone = true;
        Notification::make()->success()->title('Done.')->body($this->storyResult['total']['stories_new'].' stories and '.$this->storyResult['total']['slides'].' slides were moved.')->send();
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
