<?php

namespace App\Filament\Pages;

use App\Filament\Support\Translated;
use App\Models\Setting;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Utilities\Get;
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

    protected static ?int $navigationSort = 1;

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
            if (in_array($key, ['signups_open', 'comments_open', 'newsletter_on', 'newsletter_popup_delay', 'newsletter_popup_days', 'card_stats_on', 'logo_with_name'], true)) {
                $value = $value === null ? true : filter_var($value, FILTER_VALIDATE_BOOLEAN);
            }
            $out[$key] = $value;
        }

        return $out;
    }

    /** @return list<string> */
    private static function keys(): array
    {
        return ['home_featured_count', 'home_latest_count', 'home_stories_count', 'signups_open', 'comments_open', 'site_name', 'tagline', 'footer_about', 'copyright', 'logo', 'logo_with_name', 'newsletter_on', 'newsletter_popup_delay', 'newsletter_popup_days', 'card_stats_on', 'card_image_fit', 'card_image_focus', 'card_image_saturation', 'card_image_contrast', 'card_image_brightness', 'newsletter_code_minutes', 'newsletter_per_run', 'font_heading', 'font_body', 'font_arabic', 'font_size', 'upload_max_mb', 'image_max_kb', 'image_max_width', 'image_quality'];
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
                        \App\Filament\Support\ImageUpload::make('logo', 'site')->label('Logo'),
                        Toggle::make('logo_with_name')->label('Show the site name beside the logo')->helperText('On: the logo and the name, side by side (for a logo that is only a picture). Off: only the logo (for a logo that already contains the name).'),
                    ]),
                Section::make('Home page')
                    ->description('How many articles each part of the home page shows. A part set to 0 is not shown. Featured articles are chosen on the article itself.')
                    ->schema([
                        TextInput::make('home_featured_count')->label('Featured: how many')->numeric()->minValue(0)->maxValue(24)->placeholder('6'),
                        TextInput::make('home_latest_count')->label('Latest: how many')->numeric()->minValue(0)->maxValue(24)->placeholder('8'),
                        Toggle::make('card_stats_on')->label('Show likes, dislikes and views on the home page cards')->helperText('A small row under each story and article card. Off: the row is hidden.')->columnSpanFull(),
                        TextInput::make('home_stories_count')->label('Stories in pictures: how many')->helperText('Featured stories first, then the newest. 0 hides the part.')->numeric()->minValue(0)->maxValue(12)->placeholder('4'),
                    ])->columns(2),
                Section::make('People and discussion')
                    ->schema([
                        Toggle::make('signups_open')->label('New people can sign up')->helperText('Off: the Sign up page is closed. People who already have an account can still log in.'),
                        Toggle::make('comments_open')->label('Comments are open')->helperText('Off: nobody can post a new comment. The ones already shown stay.'),
                    ])->columns(2),
                Section::make('Fonts')
                    ->description('The letters of the site. Left empty, the usual font is used. A font marked "device" is already on the visitor\'s phone or computer; the others are served from this site. More fonts: put a .woff2 file in public/fonts/site (the list shows it once the file is there).')
                    ->schema([
                        Select::make('font_heading')->label('Heading font')->options(\App\Support\Fonts::options())->native(true)->live()->placeholder('System default (usual)'),
                        Select::make('font_body')->label('Body text font')->options(\App\Support\Fonts::options())->native(true)->live()->placeholder('System default (usual)'),
                        Select::make('font_arabic')->label('Arabic font')->helperText('For the Quran lines and other Arabic text.')->options(\App\Support\Fonts::arabicOptions())->native(true)->live()->placeholder('Amiri (usual)'),
                        Select::make('font_size')->label('Text size')->helperText('Makes all text on the site a little smaller or larger.')->options(\App\Support\Fonts::SIZE_LABELS)->native(true)->placeholder('Normal (usual)'),
                        TextEntry::make('font_preview')->label('Preview')->state(fn (Get $get) => \App\Support\Fonts::preview($get('font_heading'), $get('font_body'), $get('font_arabic')))->html()->columnSpanFull(),
                    ])->columns(2),
                Section::make('Pictures')
                    ->description('Every picture you upload is saved as a WebP that is no heavier than the size below, so the site loads fast, also on a phone. The quality is lowered a little first; only if that is not enough is the picture made narrower. Story pictures have their own settings in Sliders.')
                    ->schema([
                        TextInput::make('upload_max_mb')->label('Biggest picture you may upload (MB)')->helperText('Up to 12.')->numeric()->minValue(1)->maxValue(12)->placeholder('12'),
                        TextInput::make('image_max_kb')->label('Heaviest saved picture (KB)')->helperText('Usually 300. Between 50 and 1500.')->numeric()->minValue(50)->maxValue(1500)->placeholder('300'),
                        TextInput::make('image_max_width')->label('Widest saved picture (pixels)')->helperText('A wider picture is made narrower. A narrower one is never made bigger.')->numeric()->minValue(400)->maxValue(6000)->placeholder('2400'),
                        TextInput::make('image_quality')->label('Best quality tried first (40 to 95)')->helperText('85 is a good balance. It goes down from here until the picture fits.')->numeric()->minValue(40)->maxValue(95)->placeholder('85'),
                    ])->columns(4),
                Section::make('How pictures look on cards')
                    ->description('For the pictures on the home page, the category tiles and the story cards. This changes only how they are shown; the saved pictures stay as they are. If the colours look too strong, lower Colour strength a little (85 or 90 is often enough).')
                    ->schema([
                        Select::make('card_image_fit')->label('How a picture fits its box')->options(\App\Support\CardPictures::FITS)->native(true)->placeholder('Fill the box (usual)'),
                        Select::make('card_image_focus')->label('If the edges are cut, keep')->options(\App\Support\CardPictures::FOCUS)->native(true)->placeholder('Middle (usual)'),
                        TextInput::make('card_image_saturation')->label('Colour strength (%)')->helperText('100 is the picture as it is. Lower is calmer.')->numeric()->minValue(30)->maxValue(130)->placeholder('100'),
                        TextInput::make('card_image_contrast')->label('Contrast (%)')->numeric()->minValue(70)->maxValue(120)->placeholder('100'),
                        TextInput::make('card_image_brightness')->label('Brightness (%)')->numeric()->minValue(70)->maxValue(130)->placeholder('100'),
                    ])->columns(3),
                Section::make('Newsletter')
                    ->description('People type their email, get a 6-digit code by email, and type it back. Only then are they on the list (Content > Newsletter).')
                    ->schema([
                        Toggle::make('newsletter_on')->label('The newsletter sign-up is on')->helperText('Off: the Subscribe link and page are closed.'),
                        TextInput::make('newsletter_popup_delay')->label('Seconds before the newsletter card slides in')->helperText('0 shows it at once. The card waits until the cookie notice is answered. Switch it off in Site controls.')->numeric()->minValue(0)->maxValue(120)->placeholder('8'),
                        TextInput::make('newsletter_popup_days')->label('Days it stays away after a visitor closes it')->numeric()->minValue(1)->maxValue(365)->placeholder('14'),
                        TextInput::make('newsletter_code_minutes')->label('Minutes a code works')->numeric()->minValue(1)->maxValue(120)->placeholder('15'),
                        TextInput::make('newsletter_per_run')->label('Emails sent at a time when you send an issue')->helperText('Cheap hosting limits how many emails go out each hour. Ask your host, then set this number. Usual: 100.')->numeric()->minValue(1)->maxValue(500)->placeholder('100'),
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
            if (in_array($key, ['home_featured_count', 'home_latest_count', 'home_stories_count'], true)) {
                $value = is_numeric($value) ? max(0, min(24, (int) $value)) : null;
            }
            // How long a newsletter code works, in minutes (an empty box means the usual 15).
            if ($key === 'newsletter_code_minutes') {
                $value = is_numeric($value) ? max(1, min(120, (int) $value)) : null;
            }
            if (in_array($key, ['card_image_saturation', 'card_image_contrast', 'card_image_brightness'], true)) {
                $value = is_numeric($value) ? max(30, min(130, (int) $value)) : null;
            }
            if ($key === 'card_image_fit' && ! array_key_exists((string) $value, \App\Support\CardPictures::FITS)) {
                $value = null;
            }
            if ($key === 'card_image_focus' && ! array_key_exists((string) $value, \App\Support\CardPictures::FOCUS)) {
                $value = null;
            }
            if ($key === 'newsletter_popup_delay') {
                $value = is_numeric($value) ? max(0, min(120, (int) $value)) : null;
            }
            if ($key === 'newsletter_popup_days') {
                $value = is_numeric($value) ? max(1, min(365, (int) $value)) : null;
            }
            if ($key === 'newsletter_per_run') {
                $value = is_numeric($value) ? max(1, min(500, (int) $value)) : null;
            }
            // Pictures: the biggest upload (MB), the widest saved picture (pixels) and the WebP quality. An empty box means the usual number.
            if ($key === 'upload_max_mb') {
                $value = is_numeric($value) ? max(1, min(12, (int) $value)) : null;
            }
            if ($key === 'image_max_kb') {
                $value = is_numeric($value) ? max(50, min(1500, (int) $value)) : null;
            }
            if ($key === 'image_max_width') {
                $value = is_numeric($value) ? max(400, min(6000, (int) $value)) : null;
            }
            if ($key === 'image_quality') {
                $value = is_numeric($value) ? max(40, min(95, (int) $value)) : null;
            }
            // Fonts: only a font that is really available is kept; the usual one is stored as "nothing".
            if ($key === 'font_heading' || $key === 'font_body') {
                $value = array_key_exists((string) $value, \App\Support\Fonts::available()) && $value !== 'system' ? $value : null;
            }
            if ($key === 'font_arabic') {
                $value = array_key_exists((string) $value, \App\Support\Fonts::arabic()) && $value !== 'amiri' ? $value : null;
            }
            if ($key === 'font_size') {
                $value = array_key_exists((string) $value, \App\Support\Fonts::SIZES) && $value !== 'normal' ? $value : null;
            }
            Setting::put($key, $value === [] ? null : $value);
        }
        Notification::make()->success()->title('Saved.')->send();
    }
}
