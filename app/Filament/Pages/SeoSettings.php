<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Support\Seo;
use BackedEnum;
use Filament\Actions\Action;
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
 * SEO and tracking: how the site appears in Google and when shared, the codes that prove the site is yours, robots.txt,
 * Google Tag Manager / Google Analytics, and custom code for the head and the body. Only the super user opens it.
 */
class SeoSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMagnifyingGlassCircle;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'SEO and tracking';

    protected static ?string $title = 'SEO and tracking';

    protected static ?int $navigationSort = 2;

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

    /** @return list<string> */
    private static function keys(): array
    {
        return ['seo_noindex_site', 'seo_home_title', 'seo_title_pattern', 'seo_default_description', 'seo_og_image', 'seo_google_verification', 'seo_bing_verification', 'seo_facebook_verification', 'seo_robots_extra', 'seo_gtm_id', 'seo_ga4_id', 'seo_track_staff', 'consent_on', 'consent_policy_url', 'seo_code_head', 'seo_code_body_start', 'seo_code_body_end'];
    }

    private static function switches(): array
    {
        return ['seo_noindex_site', 'seo_track_staff', 'consent_on'];
    }

    /** @return array<string, mixed> */
    private function current(): array
    {
        $out = [];
        foreach (static::keys() as $key) {
            $value = Setting::all_()[$key] ?? null;
            if (in_array($key, static::switches(), true)) {
                $value = ($key === 'consent_on' && ($value === null || $value === '')) ? true : filter_var($value, FILTER_VALIDATE_BOOLEAN);
            }
            $out[$key] = $value;
        }

        return $out;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Show the site to search engines')
                    ->schema([
                        Toggle::make('seo_noindex_site')->label('Hide the whole site from search engines')
                            ->helperText('Switch this ON while the site is being built or tested, so Google does not list it. It adds "noindex" to every page and closes robots.txt. Switch it OFF on the day you launch.')->columnSpanFull(),
                    ]),
                Section::make('How the site looks in Google and when it is shared')
                    ->description('Every page already has its own title, description and picture (articles and stories have their own boxes). These settings fill what is missing and set the pattern.')
                    ->schema([
                        TextInput::make('seo_home_title')->label('Title of the home page')->maxLength(70)->helperText('Best 50 to 60 characters. Left empty, the site name is used.')->columnSpanFull(),
                        TextInput::make('seo_title_pattern')->label('Title pattern for all other pages')->maxLength(80)->placeholder(Seo::DEFAULT_PATTERN)
                            ->helperText('Use :page for the page name and :site for the site name. Example: ":page – :site". A title typed in full on an article is never changed.')->columnSpanFull(),
                        Textarea::make('seo_default_description')->label('Description used when a page has none')->rows(2)->maxLength(300)->helperText('Best up to 160 characters. Left empty, the tagline is used.')->columnSpanFull(),
                        \App\Filament\Support\ImageUpload::make('seo_og_image', 'seo')->label('Picture shown when a link is shared (Facebook, WhatsApp, X)')->helperText('Used when the page has no picture of its own. Best 1200 × 630 pixels.')->columnSpanFull(),
                    ])->columns(2),
                Section::make('Prove the site is yours')
                    ->description('Paste only the code (the long text after content=), not the whole tag.')
                    ->schema([
                        TextInput::make('seo_google_verification')->label('Google Search Console')->maxLength(120),
                        TextInput::make('seo_bing_verification')->label('Bing Webmaster Tools')->maxLength(120),
                        TextInput::make('seo_facebook_verification')->label('Facebook domain')->maxLength(120),
                    ])->columns(3),
                Section::make('robots.txt and the sitemap')
                    ->description('The site makes /robots.txt and /sitemap.xml by itself. Private parts (admin, account, login, search) are closed and the sitemap is named in it.')
                    ->schema([
                        Textarea::make('seo_robots_extra')->label('Extra lines for robots.txt (optional)')->rows(3)->helperText('For example: Disallow: /some-private-page')->columnSpanFull(),
                    ]),
                Section::make('Google Tag Manager and Google Analytics')
                    ->description('Runs only on the real website, never on localhost, and (unless you switch it on below) never when someone from the team is logged in.')
                    ->schema([
                        TextInput::make('seo_gtm_id')->label('Google Tag Manager ID')->placeholder('GTM-XXXXXXX')->maxLength(20)
                            ->helperText('The best way: put Google Analytics inside Tag Manager. The head and body parts are added for you.'),
                        TextInput::make('seo_ga4_id')->label('Google Analytics 4 ID (only if you do not use Tag Manager)')->placeholder('G-XXXXXXXXXX')->maxLength(20)
                            ->helperText('If both are set, only Tag Manager is used, so visits are never counted twice.'),
                        Toggle::make('seo_track_staff')->label('Also count visits of the team (logged-in staff)')->columnSpanFull(),
                    ])->columns(2),
                Section::make('Cookie notice')
                    ->description('A small notice at the bottom of the page. Counting visits (Tag Manager / Analytics above) starts only after the visitor presses OK. Visitors can change their mind with "Cookie settings" in the footer. Custom code below is NOT held back, so do not put counting code there.')
                    ->schema([
                        Toggle::make('consent_on')->label('Show the cookie notice')->helperText('Keep it ON if you have visitors from the EU, the UK or other places with cookie laws.'),
                        TextInput::make('consent_policy_url')->label('Address of your privacy page')->placeholder('/privacy')->maxLength(200)
                            ->helperText('Shown as a link in the notice. Use a page of the site, for example /privacy. Left empty, no link is shown.'),
                    ])->columns(2),
                Section::make('Custom code')
                    ->description('Careful: this code is printed exactly as you type it, on every page. A mistake can break the site, and code from a stranger can do harm. Only paste code from a source you trust.')
                    ->schema([
                        Textarea::make('seo_code_head')->label('Before </head>')->rows(5)->extraInputAttributes(['style' => 'font-family:monospace'])->columnSpanFull(),
                        Textarea::make('seo_code_body_start')->label('Right after <body>')->rows(4)->extraInputAttributes(['style' => 'font-family:monospace'])->columnSpanFull(),
                        Textarea::make('seo_code_body_end')->label('Before </body>')->rows(5)->extraInputAttributes(['style' => 'font-family:monospace'])->columnSpanFull(),
                    ]),
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
            if (in_array($key, static::switches(), true)) {
                $value = (bool) $value;
            } elseif (is_string($value)) {
                $value = in_array($key, ['seo_code_head', 'seo_code_body_start', 'seo_code_body_end', 'seo_robots_extra', 'seo_default_description'], true) ? trim($value) : trim(strip_tags($value));
            }
            // The codes hold only letters, digits, - and _ (so nothing else can end up in a tag).
            if (in_array($key, ['seo_google_verification', 'seo_bing_verification', 'seo_facebook_verification'], true) && is_string($value)) {
                $value = preg_replace('/[^A-Za-z0-9_\-]/', '', $value);
            }
            if (in_array($key, ['seo_gtm_id', 'seo_ga4_id'], true) && is_string($value)) {
                $value = strtoupper(preg_replace('/\s+/', '', $value));
            }
            Setting::put($key, $value === '' ? null : $value);
        }
        Notification::make()->success()->title('Saved.')->send();
    }
}
