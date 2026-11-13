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
        return ['home_featured_count', 'home_latest_count', 'signups_open', 'comments_open', 'site_name', 'tagline', 'footer_about', 'copyright', 'logo'];
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
                Section::make('Home page')
                    ->description('How many articles each part of the home page shows. A part set to 0 is not shown. Featured articles are chosen on the article itself.')
                    ->schema([
                        TextInput::make('home_featured_count')->label('Featured: how many')->numeric()->minValue(0)->maxValue(24)->placeholder('6'),
                        TextInput::make('home_latest_count')->label('Latest: how many')->numeric()->minValue(0)->maxValue(24)->placeholder('8'),
                    ])->columns(2),
                Section::make('People and discussion')
                    ->schema([
                        Toggle::make('signups_open')->label('New people can sign up')->helperText('Off: the Sign up page is closed. People who already have an account can still log in.'),
                        Toggle::make('comments_open')->label('Comments are open')->helperText('Off: nobody can post a new comment. The ones already shown stay.'),
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
            // The two counts are whole numbers (an empty box means the usual number).
            if (in_array($key, ['home_featured_count', 'home_latest_count'], true)) {
                $value = is_numeric($value) ? max(0, min(24, (int) $value)) : null;
            }
            Setting::put($key, $value === [] ? null : $value);
        }
        Notification::make()->success()->title('Saved.')->send();
    }
}
