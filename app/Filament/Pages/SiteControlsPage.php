<?php

namespace App\Filament\Pages;

use App\Support\SiteControls;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/** Site controls: one page of On/Off switches for the busy parts of the public site, with "Quiet look" and "Normal look". Super user only. */
class SiteControlsPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Site controls';

    protected static ?string $title = 'Site controls';

    protected static ?string $slug = 'site-controls';

    protected static ?int $navigationSort = 0;

    /** @var array<string, mixed> */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->isSuperuser();
    }

    public function getSubheading(): ?string
    {
        return 'These switches decide how busy the site looks. Turn a switch on to show a part, off to hide it. Hiding never deletes anything. Press Quiet look to hide all the noisy parts at once, and Normal look to bring them back.';
    }

    public function mount(): void
    {
        $this->form->fill($this->current());
    }

    /** @return array<string, bool> */
    private function current(): array
    {
        $out = [];
        foreach (array_keys(SiteControls::all()) as $key) {
            $out[$key] = SiteControls::on($key);
        }

        return $out;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('quiet')->label('Quiet look')->icon('heroicon-o-speaker-x-mark')
                ->color(fn () => SiteControls::isQuiet() ? 'primary' : 'gray')
                ->requiresConfirmation()->modalHeading('Quiet look')->modalDescription('This switches off the noisy parts (likes, counts, share, recently viewed, social buttons, related). Nothing is deleted. The useful extras stay as they are.')
                ->action(fn () => $this->applyLook(true)),
            Action::make('normal')->label('Normal look')->icon('heroicon-o-speaker-wave')->color('gray')
                ->action(fn () => $this->applyLook(false)),
            Action::make('advanced')->label('All settings (advanced)')->icon('heroicon-o-cog-6-tooth')->color('gray')->url(SiteSettings::getUrl()),
        ];
    }

    public function applyLook(bool $quiet): void
    {
        SiteControls::look($quiet);
        $this->form->fill($this->current());
        Notification::make()->success()->title($quiet ? 'Quiet look is on.' : 'Normal look is on.')->send();
    }

    public function form(Schema $schema): Schema
    {
        $toggles = fn (string $group) => array_map(
            fn (string $key) => Toggle::make($key)->label(SiteControls::all()[$key][0])->helperText(SiteControls::all()[$key][1]),
            SiteControls::keys($group),
        );

        return $schema
            ->statePath('data')
            ->components([
                Section::make('The noisy parts (the quiet look switches these off)')
                    ->description('These make the site busy. A reader sees more when they are on, and less when they are off.')
                    ->schema($toggles('noisy')),
                Section::make('Useful extras (the quiet look leaves these as they are)')
                    ->schema($toggles('extras')),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([Action::make('save')->label('Save')->submit('save')->keyBindings(['mod+s'])])->key('form-actions'),
                ]),
        ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();
        foreach (array_keys(SiteControls::all()) as $key) {
            SiteControls::set($key, (bool) ($data[$key] ?? false));
        }
        Notification::make()->success()->title('Saved.')->send();
    }
}
