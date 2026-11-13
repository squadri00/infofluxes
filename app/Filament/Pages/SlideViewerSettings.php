<?php

namespace App\Filament\Pages;

use App\Models\Article;
use App\Models\Setting;
use App\Support\ViewerSettings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
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

/**
 * Slide viewer: every control of the story slider on one screen (the settings of the old "Slide viewer", "Visual Stories" and
 * "Listen" groups). A setting that is not saved here has its usual value. Only the super user opens it.
 */
class SlideViewerSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartLine;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = 'Slide viewer';

    protected static ?string $title = 'Slide viewer';

    /** @var array<string, mixed> */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->isSuperuser();
    }

    public function mount(): void
    {
        $this->form->fill($this->current());
    }

    /** @return array<string, mixed> every control with its value now (or its usual one) */
    private function current(): array
    {
        $out = [];
        foreach (ViewerSettings::definitions() as $key => [, $type, $usual]) {
            $value = Setting::get($key, $usual);
            $out[$key] = $type === 'toggle' ? (string) $value === '1' : (string) $value;
        }

        return $out;
    }

    public function form(Schema $schema): Schema
    {
        $sections = [];
        $first = true;
        foreach (ViewerSettings::groups() as $heading => $items) {
            $fields = [];
            foreach ($items as $key => [$label, $type, $usual, $options]) {
                $fields[] = match ($type) {
                    'toggle' => Toggle::make($key)->label($label),
                    'select' => Select::make($key)->label($label)->options($options ?? [])->required()->native(true),
                    'number' => TextInput::make($key)->label($label)->numeric()->required()->placeholder($usual),
                    'textarea' => Textarea::make($key)->label($label)->rows(4),
                    default => TextInput::make($key)->label($label)->maxLength(255),
                };
            }
            $sections[] = Section::make($heading)->collapsible()->collapsed(! $first)->columns(1)->schema($fields);
            $first = false;
        }

        return $schema->statePath('data')->components($sections);
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
        foreach (ViewerSettings::definitions() as $key => [, $type, $usual]) {
            $value = $data[$key] ?? null;
            $value = $type === 'toggle' ? ($value ? '1' : '0') : trim((string) $value);
            // A setting at its usual value is not kept, so the site keeps following the usual value.
            if ($value === (string) $usual || ($type !== 'toggle' && $value === '')) {
                Setting::query()->where('key', $key)->delete();
            } else {
                Setting::put($key, $value);
            }
        }
        Setting::forget();
        Notification::make()->success()->title('Saved.')->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('see')
                ->label('See it on a story')
                ->icon('heroicon-o-eye')
                ->color('gray')
                ->url(fn () => ($a = Article::published()->whereHas('chapters')->orderBy('id')->first()) ? route('article.show', $a->slug) : null, shouldOpenInNewTab: true)
                ->visible(fn () => Article::published()->whereHas('chapters')->exists()),
            Action::make('reset')
                ->label('Back to the usual settings')
                ->color('danger')
                ->requiresConfirmation()
                ->modalDescription('Every control on this screen goes back to its usual value. Your articles and pictures are not changed.')
                ->action(function () {
                    Setting::query()->whereIn('key', array_keys(ViewerSettings::definitions()))->delete();
                    Setting::forget();
                    $this->form->fill($this->current());
                    Notification::make()->success()->title('Back to the usual settings.')->send();
                }),
        ];
    }
}
