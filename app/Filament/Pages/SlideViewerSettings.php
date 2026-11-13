<?php

namespace App\Filament\Pages;

use App\Filament\Support\Translated;
use App\Models\Article;
use App\Models\HeroMessage;
use App\Models\HeroSlide;
use App\Models\Setting;
use App\Support\Hero;
use App\Support\ViewerSettings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
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
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Sliders: the one place to manage every slider of the site.
 *  - the home page slider (the banner: words, pictures, buttons, timing), and
 *  - the story slider (the slide viewer of an article with story slides, its pictures, Listen and the PDF book).
 * A control that is not saved here has its usual value. Only the super user opens it.
 */
class SlideViewerSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartLine;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = 'Sliders';

    protected static ?string $title = 'Sliders';

    protected static ?string $slug = 'sliders';

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

    /** @return array<string, mixed> every control with its value now (or its usual one), and the words and pictures of the home slider */
    private function current(): array
    {
        $out = [];
        foreach (ViewerSettings::definitions() as $key => [, $type, $usual]) {
            $value = Setting::get($key, $usual);
            $out[$key] = $type === 'toggle' ? (string) $value === '1' : (string) $value;
        }

        // the home page slider
        $out['hero_on'] = Hero::enabled();
        $out['hero_slide_seconds'] = (string) Hero::seconds();
        $out['hero_layout'] = Hero::layout();
        foreach (['home_headline', 'home_intro', 'home_message'] as $key) {
            $out[$key] = Setting::all_()[$key] ?? [];
        }
        $out['messages'] = HeroMessage::query()->orderBy('sort_order')->orderBy('id')->get()->map(fn (HeroMessage $m) => [
            'id' => $m->id, 'title' => $m->getTranslations('title'), 'subtitle' => $m->getTranslations('subtitle'), 'description' => $m->getTranslations('description'), 'is_active' => $m->is_active,
        ])->all();
        $out['slides'] = HeroSlide::query()->orderBy('sort_order')->orderBy('id')->get()->map(fn (HeroSlide $s) => [
            'id' => $s->id, 'image' => $s->image ? [$s->image] : [], 'image_alt' => $s->getTranslations('image_alt'), 'heading' => $s->getTranslations('heading'), 'text' => $s->getTranslations('text'),
            'button_label' => $s->getTranslations('button_label'), 'button_url' => $s->button_url, 'new_tab' => $s->new_tab, 'is_active' => $s->is_active, 'focus' => $s->custom_data['focus'] ?? 'middle',
        ])->all();

        return $out;
    }

    public function form(Schema $schema): Schema
    {
        $main = config('infofluxes.default_locale');

        $storySections = [];
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
            $storySections[] = Section::make($heading)->collapsible()->collapsed(! $first)->columns(1)->schema($fields);
            $first = false;
        }

        $home = [
            Section::make('The banner')
                ->description('The big banner at the top of the home page: words on one side, and a slider of pictures on the other.')
                ->schema([
                    Toggle::make('hero_on')->label('Show the home page banner'),
                    Select::make('hero_layout')->label('Banner style')->options(['split' => 'Words on the left, slider on the right', 'full' => 'Full-size gallery (the words sit on top of the pictures)'])->required()->native(true),
                    TextInput::make('hero_slide_seconds')->label('Seconds before the next picture (0 = it never moves by itself)')->numeric()->required()->minValue(0)->maxValue(60)->placeholder('6'),
                ])->columns(2),

            Section::make('The words of the banner')
                ->description('Each visit shows one of the sets that are in use, picked at random. A set has a big title, one line under it, and a description.')
                ->collapsible()
                ->schema([
                    Repeater::make('messages')
                        ->hiddenLabel()
                        ->addActionLabel('Add a set of words')
                        ->defaultItems(0)
                        ->collapsible()->collapsed()
                        ->reorderable()
                        ->itemLabel(fn (array $state) => $state['title'][$main] ?? 'Words')
                        ->schema([
                            Hidden::make('id'),
                            ...Translated::text('title', 'Title (the big words)', required: true),
                            ...Translated::text('subtitle', 'Subtitle (one line under the title)', max: 500),
                            ...Translated::textarea('description', 'Description', rows: 5),
                            Toggle::make('is_active')->label('In use')->default(true)->helperText('Switch off to keep this set but stop showing it.'),
                        ]),
                ]),

            Section::make('The pictures of the slider')
                ->description('A wide picture, about 1600 x 1000 and under 5 MB. The words and the button sit over its lower part. The order here is the order on the page: drag to change it.')
                ->collapsible()
                ->schema([
                    Repeater::make('slides')
                        ->hiddenLabel()
                        ->addActionLabel('Add a picture')
                        ->defaultItems(0)
                        ->collapsible()->collapsed()
                        ->reorderable()
                        ->itemLabel(fn (array $state) => ($state['heading'][$main] ?? null) ?: (($state['image_alt'][$main] ?? null) ?: 'Picture'))
                        ->schema([
                            Hidden::make('id'),
                            \App\Filament\Support\ImageUpload::make('image', 'hero')->label('Picture')->required()
                                ->helperText(fn () => 'Any picture works, up to '.(\App\Support\ImageStore::maxKb() / 1024).' MB: it is saved lighter, and the site cuts it to the gallery shape (2000 x 920) by itself and keeps it sharp. Use a picture at least 2000 pixels wide for the best result.'),
                            Select::make('focus')->label('If the picture is cut, keep the')->options(['top' => 'Top part', 'middle' => 'Middle part', 'bottom' => 'Bottom part'])->default('middle')->native(true),
                            ...Translated::text('image_alt', 'Describe the picture (for people who cannot see it)', required: true),
                            ...Translated::text('heading', 'Words over the picture: heading'),
                            ...Translated::text('text', 'Words over the picture: one short line', max: 500),
                            ...Translated::text('button_label', 'Button: the words', help: 'Leave empty for no button.'),
                            TextInput::make('button_url')->label('Button: where it goes')->maxLength(2048)
                                ->helperText('A page of this site (for example /is-hell-eternal) or a full web address starting with https://')
                                ->required(fn (Get $get) => filled($get('button_label.'.config('infofluxes.default_locale'))))
                                ->rules([fn () => function (string $attribute, $value, \Closure $fail) {
                                    if (filled($value) && Hero::safeUrl($value) === null) {
                                        $fail('Use a full web address (https://...), mailto:, tel:, or a path that starts with /.');
                                    }
                                }]),
                            Toggle::make('new_tab')->label('Open the button in a new tab'),
                            Toggle::make('is_active')->label('Shown')->default(true),
                        ]),
                ]),

            Section::make('Words on the home page')
                ->description('Shown under the banner. When the banner is off, the headline is the big title of the page. Leave a box empty to show nothing.')
                ->collapsible()->collapsed()
                ->schema([
                    ...Translated::text('home_headline', 'Headline (the big title)'),
                    ...Translated::textarea('home_intro', 'Introduction', rows: 4),
                    ...Translated::textarea('home_message', 'Message', rows: 4),
                ]),
        ];

        return $schema->statePath('data')->components([
            Tabs::make('sliders')->persistTabInQueryString()->tabs([
                Tab::make('Home page slider')->icon('heroicon-o-home')->schema($home),
                Tab::make('Story slider')->icon('heroicon-o-presentation-chart-line')
                    ->badge('articles with story slides')
                    ->schema($storySections),
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

        // the story slider
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

        // the home page slider
        $this->keep('hero_on', ($data['hero_on'] ?? false) ? '1' : '0', '1');
        $this->keep('hero_layout', (($data['hero_layout'] ?? 'split') === 'full') ? 'full' : 'split', 'split');
        $this->keep('hero_slide_seconds', (string) max(0, min(60, (int) ($data['hero_slide_seconds'] ?? 6))), '6');
        foreach (['home_headline', 'home_intro', 'home_message'] as $key) {
            $words = array_filter((array) ($data[$key] ?? []), fn ($v) => filled($v));
            $words === [] ? Setting::query()->where('key', $key)->delete() : Setting::put($key, $words);
        }

        $this->sync(HeroMessage::class, (array) ($data['messages'] ?? []), fn (array $m) => [
            'title' => $this->words($m['title'] ?? []) ?? [config('infofluxes.default_locale') => ''],
            'subtitle' => $this->words($m['subtitle'] ?? []),
            'description' => $this->words($m['description'] ?? []),
            'is_active' => (bool) ($m['is_active'] ?? true),
        ]);
        $this->sync(HeroSlide::class, (array) ($data['slides'] ?? []), fn (array $s) => [
            'image' => is_array($s['image']) ? (string) (array_values($s['image'])[0] ?? '') : (string) $s['image'],
            'image_alt' => $this->words($s['image_alt'] ?? []),
            'heading' => $this->words($s['heading'] ?? []),
            'text' => $this->words($s['text'] ?? []),
            'button_label' => $this->words($s['button_label'] ?? []),
            'button_url' => filled($s['button_url'] ?? null) ? trim($s['button_url']) : null,
            'new_tab' => (bool) ($s['new_tab'] ?? false),
            'is_active' => (bool) ($s['is_active'] ?? true),
            'custom_data' => ['focus' => in_array($s['focus'] ?? 'middle', ['top', 'middle', 'bottom'], true) ? $s['focus'] : 'middle'],
        ]);

        Setting::forget();
        Notification::make()->success()->title('Saved.')->send();
    }

    /** A setting at its usual value is not kept. */
    private function keep(string $key, string $value, string $usual): void
    {
        $value === $usual ? Setting::query()->where('key', $key)->delete() : Setting::put($key, $value);
    }

    /** The words of each language that are filled in, or null when there are none. */
    private function words(array $texts): ?array
    {
        $texts = array_filter(array_map(fn ($t) => is_string($t) ? trim($t) : $t, $texts), fn ($t) => filled($t));

        return $texts === [] ? null : $texts;
    }

    /**
     * Make the rows of a table the same as the list on the screen: change the ones that are there (by id), add the new ones,
     * and remove the ones that were taken off the list. The order of the list is the order of the rows.
     */
    private function sync(string $model, array $items, \Closure $attributes): void
    {
        $keep = [];
        foreach (array_values($items) as $i => $item) {
            $row = filled($item['id'] ?? null) ? $model::find($item['id']) : null;
            $values = $attributes($item) + ['sort_order' => $i + 1];
            if ($row) {
                $row->update($values);
            } else {
                $row = $model::create($values);
            }
            $keep[] = $row->id;
        }
        $model::query()->whereNotIn('id', $keep)->delete();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('seeHome')
                ->label('See the home page')
                ->icon('heroicon-o-home')
                ->color('gray')
                ->url(fn () => route('home'), shouldOpenInNewTab: true),
            Action::make('see')
                ->label('See a story')
                ->icon('heroicon-o-eye')
                ->color('gray')
                ->url(fn () => ($a = Article::published()->whereHas('chapters')->orderBy('id')->first()) ? route('article.show', $a->slug) : null, shouldOpenInNewTab: true)
                ->visible(fn () => Article::published()->whereHas('chapters')->exists()),
            Action::make('reset')
                ->label('Back to the usual story slider settings')
                ->color('danger')
                ->requiresConfirmation()
                ->modalDescription('Every control of the story slider goes back to its usual value. The home page slider, your articles and pictures are not changed.')
                ->action(function () {
                    Setting::query()->whereIn('key', array_keys(ViewerSettings::definitions()))->delete();
                    Setting::forget();
                    $this->form->fill($this->current());
                    Notification::make()->success()->title('Back to the usual settings.')->send();
                }),
        ];
    }
}
