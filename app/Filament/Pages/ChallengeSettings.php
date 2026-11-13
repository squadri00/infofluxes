<?php

namespace App\Filament\Pages;

use App\Models\QuizAttempt;
use App\Models\Setting;
use BackedEnum;
use Filament\Actions\Action;
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
 * "Test settings": switch each test on or off, how many questions a round has, and whether only the passages a person has checked are used.
 * Every control is an ordinary Setting; one at its usual value is not kept. A short list of the saved scores sits at the bottom of the page.
 */
class ChallengeSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static string|UnitEnum|null $navigationGroup = 'Challenge yourself';

    protected static ?int $navigationSort = 0;

    protected static ?string $navigationLabel = 'Test settings';

    protected static ?string $title = 'Test settings';

    protected static ?string $slug = 'test-settings';

    /** key => [label, type, usual value, help] for the three kinds of control */
    public const TESTS = [
        'quiz' => 'Which one is from the Quran?',
        'scripture' => 'Which scripture?',
        'facts' => 'Fact or myth?',
        'who' => 'Who said it?',
    ];

    /** @var array<string, mixed> */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->canUseScreen('tests') ?? false;
    }

    public function mount(): void
    {
        $this->form->fill($this->current());
    }

    /** @return array<string, mixed> */
    private function current(): array
    {
        $out = [];
        foreach (array_keys(static::TESTS) as $t) {
            $out[$t.'_on'] = (string) Setting::get($t.'_on', '1') === '1';
            $out[$t.'_questions'] = (string) Setting::get($t.'_questions', 10);
            $out[$t.'_only_checked'] = (string) Setting::get($t.'_only_checked', '0') === '1';
        }
        $out['quiz_options_min'] = (string) Setting::get('quiz_options_min', 4);
        $out['quiz_options_max'] = (string) Setting::get('quiz_options_max', 5);
        $out['order_on'] = (string) Setting::get('order_on', '1') === '1';
        $out['order_size'] = (string) Setting::get('order_size', 12);

        return $out;
    }

    public function form(Schema $schema): Schema
    {
        $sections = [];
        foreach (static::TESTS as $t => $label) {
            $fields = [
                Toggle::make($t.'_on')->label('Switched on'),
                TextInput::make($t.'_questions')->label('Questions in a round')->numeric()->required()->minValue(1)->maxValue(30),
                Toggle::make($t.'_only_checked')->label('Use only the ones a person has checked'),
            ];
            if ($t === 'quiz') {
                $fields[] = TextInput::make('quiz_options_min')->label('Fewest choices in a question')->numeric()->required()->minValue(2)->maxValue(5);
                $fields[] = TextInput::make('quiz_options_max')->label('Most choices in a question')->numeric()->required()->minValue(2)->maxValue(5);
            }
            $sections[] = Section::make($label)->schema($fields)->columns(3)->collapsible();
        }
        $sections[] = Section::make('Put the chapters in order')->schema([
            Toggle::make('order_on')->label('Switched on'),
            TextInput::make('order_size')->label('Chapters in a round')->numeric()->required()->minValue(6)->maxValue(20),
        ])->columns(3)->collapsible();

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

        $rules = ['_questions' => [1, 30, 10], 'quiz_options_min' => [2, 5, 4], 'quiz_options_max' => [2, 5, 5], 'order_size' => [6, 20, 12]];
        foreach (array_keys(static::TESTS) as $t) {
            $this->keep($t.'_on', ($data[$t.'_on'] ?? false) ? '1' : '0', '1');
            $this->keep($t.'_only_checked', ($data[$t.'_only_checked'] ?? false) ? '1' : '0', '0');
            [$min, $max, $usual] = $rules['_questions'];
            $this->keep($t.'_questions', (string) max($min, min($max, (int) ($data[$t.'_questions'] ?? $usual))), (string) $usual);
        }
        foreach (['quiz_options_min', 'quiz_options_max', 'order_size'] as $key) {
            [$min, $max, $usual] = $rules[$key];
            $this->keep($key, (string) max($min, min($max, (int) ($data[$key] ?? $usual))), (string) $usual);
        }
        $this->keep('order_on', ($data['order_on'] ?? false) ? '1' : '0', '1');

        Setting::forget();
        Notification::make()->success()->title('Saved.')->send();
    }

    /** A setting at its usual value is not kept, so the site keeps following the usual value. */
    private function keep(string $key, string $value, string $usual): void
    {
        $value === $usual ? Setting::query()->where('key', $key)->delete() : Setting::put($key, $value);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('see')->label('See the tests')->icon('heroicon-o-eye')->color('gray')->url(fn () => route('challenge'), shouldOpenInNewTab: true),
            Action::make('scores')->label('Saved scores')->icon('heroicon-o-chart-bar')->color('gray')
                ->modalHeading('Saved scores (no name, no address: only the score)')
                ->modalSubmitAction(false)
                ->modalContent(fn () => view('filament.pages.challenge-scores', [
                    'rows' => QuizAttempt::query()->selectRaw('quiz, count(*) as rounds, sum(score) as score, sum(total) as total')->groupBy('quiz')->get(),
                ])),
        ];
    }
}
