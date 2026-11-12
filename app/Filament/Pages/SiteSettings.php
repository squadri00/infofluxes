<?php

namespace App\Filament\Pages;

use App\Filament\Support\Translated;
use App\Models\Setting;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
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

/** Site settings: the words and pictures of the site frame. Only the super user opens it. */
class SiteSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Site settings';

    protected static ?string $title = 'Site settings';

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

    /** @return array<string, mixed> */
    private function current(): array
    {
        $out = [];
        foreach (static::keys() as $key) {
            $value = Setting::all_()[$key] ?? null;
            // The two switches are on until the super user turns them off.
            if (in_array($key, ['signups_open', 'comments_open'], true)) {
                $value = $value === null ? true : (bool) $value;
            }
            $out[$key] = $value;
        }

        return $out;
    }

    /** @return list<string> */
    private static function keys(): array
    {
        return ['signups_open', 'comments_open', 'site_name', 'tagline', 'footer_about', 'copyright', 'logo', 'social_facebook', 'social_instagram', 'social_x', 'social_youtube', 'social_tiktok'];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('The site frame')
                    ->description('These words show in the header and footer on every page. Left empty, the plain default is used.')
                    ->schema([
                        ...Translated::text('site_name', 'Site name'),
                        ...Translated::text('tagline', 'Tagline (the line in the top bar)'),
                        ...Translated::textarea('footer_about', 'About text in the footer', rows: 3),
                        ...Translated::text('copyright', 'Copyright line', help: 'You can use :year and :site.'),
                        FileUpload::make('logo')->label('Logo')->image()->disk('public')->directory('site'),
                    ]),
                Section::make('People and discussion')
                    ->schema([
                        Toggle::make('signups_open')->label('New people can sign up')->helperText('Off: the Sign up page is closed. People who already have an account can still log in.'),
                        Toggle::make('comments_open')->label('Comments are open')->helperText('Off: nobody can post a new comment. The ones already shown stay.'),
                    ])->columns(2),
                Section::make('Social networks')
                    ->description('A web address that starts with https://. A network with no address shows nothing.')
                    ->schema([
                        TextInput::make('social_facebook')->label('Facebook')->url()->maxLength(300),
                        TextInput::make('social_instagram')->label('Instagram')->url()->maxLength(300),
                        TextInput::make('social_x')->label('X')->url()->maxLength(300),
                        TextInput::make('social_youtube')->label('YouTube')->url()->maxLength(300),
                        TextInput::make('social_tiktok')->label('TikTok')->url()->maxLength(300),
                    ])->columns(2),
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
        foreach (static::keys() as $key) {
            $value = $data[$key] ?? null;
            if (is_array($value)) {
                $value = array_filter($value, fn ($v) => filled($v));
            }
            Setting::put($key, $value === [] ? null : $value);
        }
        Notification::make()->success()->title('Saved.')->send();
    }
}
