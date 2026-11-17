<?php

namespace App\Filament\Pages;

use App\Models\Story;
use App\Models\StoryRun;
use App\Support\StoryMaker\Rules;
use App\Support\StoryMaker\TranslationPrompt;
use App\Support\StoryMaker\TranslationReader;
use App\Support\StoryMaker\TranslationSaver;
use App\Support\StoryMaker\Translations;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Story translations: step 2 of the Story maker. It makes the prompt that asks the AI to translate a saved English story into the
 * chosen languages, checks the answer language by language, and saves each language into the story (hidden until you switch it on).
 * Every run is kept, so it can be opened and saved again. It does not use the AI import or the AI prompts of the articles.
 */
class StoryTranslations extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLanguage;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 10;

    protected static ?string $navigationLabel = 'Story translations';

    protected static ?string $title = 'Story translations';

    protected static ?string $slug = 'story-translations';

    protected string $view = 'filament.pages.story-translations';

    public ?int $storyId = null;

    public string $storyQuery = '';

    /** @var list<string> the languages to ask for */
    public array $codes = [];

    public ?int $runId = null;

    public string $paste = '';

    /** @var array<string, mixed> */
    public array $result = [];

    public bool $checked = false;

    /** @var array<string, mixed>|null */
    public ?array $done = null;

    // the rules (super user)
    public string $ruleText = '';

    /** @var array<string, array{quran: string, words: string}> */
    public array $langEdits = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->canUseScreen('storymaker') ?? false;
    }

    public function mount(): void
    {
        $this->loadRules();
        $id = (int) request()->query('story');
        if ($id > 0 && ($story = Story::find($id))) {
            $this->chooseStory($story->id);
        }
    }

    private function loadRules(): void
    {
        $this->ruleText = Rules::translation();
        $this->langEdits = [];
        foreach (Rules::languages() as $code => $l) {
            $this->langEdits[$code] = ['quran' => $l['quran'], 'words' => $l['words']];
        }
    }

    /** @return array<string, array{name: string, native: string, rtl: bool, quran: string, words: string}> */
    public function languages(): array
    {
        return Rules::languages();
    }

    public function story(): ?Story
    {
        return $this->storyId ? Story::query()->with('chapters.slides')->find($this->storyId) : null;
    }

    /** Stories to choose from: the ones that match the words typed, or the newest ones. @return \Illuminate\Support\Collection<int, Story> */
    public function storyResults()
    {
        $q = trim($this->storyQuery);
        $query = Story::query()->whereHas('chapters.slides')->latest('id')->limit(10);
        if ($q !== '') {
            $id = preg_match('/^\s*S?-?(\d+)\s*$/i', $q, $m) ? (int) $m[1] : null;
            $en = config('infofluxes.default_locale');
            $query->where(fn ($w) => $w->when($id, fn ($x) => $x->orWhere('id', $id))->orWhere('title->'.$en, 'like', '%'.$q.'%'));
        }

        return $query->get();
    }

    public function chooseStory(int $id): void
    {
        $story = Story::query()->whereHas('chapters.slides')->find($id);
        if (! $story) {
            return;
        }
        $this->storyId = $story->id;
        $this->storyQuery = '';
        $this->runId = null;
        $this->paste = '';
        $this->result = [];
        $this->checked = false;
        $this->done = null;
        // Ask first for the languages the story does not have yet.
        $have = array_keys(Translations::all($story));
        $this->codes = array_values(array_diff(array_keys(Rules::languages()), $have)) ?: array_keys(Rules::languages());
    }

    public function changeStory(): void
    {
        $this->reset(['storyId', 'runId', 'paste', 'result', 'checked', 'done']);
    }

    public function prompt(): string
    {
        $story = $this->story();

        return $story ? TranslationPrompt::build($story, $this->chosenCodes()) : '';
    }

    /** @return list<string> */
    private function chosenCodes(): array
    {
        $known = array_keys(Rules::languages());

        return array_values(array_filter($known, fn ($c) => in_array($c, $this->codes, true)));
    }

    public function run(): ?StoryRun
    {
        return $this->runId ? StoryRun::find($this->runId) : null;
    }

    private function persist(string $status): ?StoryRun
    {
        $story = $this->story();
        if (! $story) {
            return null;
        }
        $run = $this->run() ?? new StoryRun(['kind' => 'translation', 'user_id' => auth()->id()]);
        $order = array_keys(StoryRun::STATUSES);
        if ($run->exists && array_search($run->status, $order, true) > array_search($status, $order, true)) {
            $status = $run->status;
        }
        $run->fill([
            'story_id' => $story->id,
            'topic' => mb_substr($story->mainTitle(), 0, 300),
            'options' => ['languages' => $this->chosenCodes()],
            'prompt' => $this->prompt(),
            'answer' => $this->paste !== '' ? $this->paste : null,
            'result' => $this->checked ? $this->result : null,
            'status' => $status,
        ])->save();
        $this->runId = $run->id;

        return $run;
    }

    public function saveRun(): void
    {
        if (! $this->story()) {
            Notification::make()->warning()->title('Choose the story first.')->send();

            return;
        }
        $this->persist(trim($this->paste) !== '' ? 'pasted' : 'prompt');
        Notification::make()->success()->title('This run is saved. You can open it again from "Saved translation runs" below.')->send();
    }

    public function check(): void
    {
        $this->done = null;
        $story = $this->story();
        if (! $story) {
            Notification::make()->warning()->title('Choose the story first.')->send();

            return;
        }
        if (trim($this->paste) === '') {
            $this->result = [];
            $this->checked = false;
            Notification::make()->warning()->title('Paste the answer of the AI first.')->send();

            return;
        }
        $this->result = TranslationReader::read($this->paste, $story, $this->chosenCodes());
        $this->checked = true;
        $this->persist('checked');
    }

    public function saveLanguages(): void
    {
        $story = $this->story();
        if (! $story) {
            return;
        }
        // The pasted text is read again here, so what is saved is never what a browser claims it checked.
        $read = TranslationReader::read($this->paste, $story, $this->chosenCodes());
        $this->result = $read;
        $this->checked = true;
        $run = $this->persist('checked');

        $saved = [];
        $skipped = [];
        foreach ($read['languages'] as $code => $lang) {
            if ($lang['errors'] !== []) {
                $skipped[$code] = $lang['errors'];

                continue;
            }
            TranslationSaver::save($story->fresh(), $code, $lang['data'], $run?->id);
            $saved[] = $code;
        }

        if ($saved === []) {
            Notification::make()->danger()->title('No language could be saved yet.')->body('Fix the problems that are shown, or ask the AI again.')->send();

            return;
        }
        $run?->update(['status' => 'saved', 'result' => $read]);
        $this->done = ['saved' => $saved, 'skipped' => $skipped, 'code' => $story->code];
        Notification::make()->success()->title('Saved: '.implode(', ', array_map(fn ($c) => Rules::languages()[$c]['name'], $saved)).'. They are hidden until you switch them on.')->send();
    }

    public function setStatus(string $code, string $status): void
    {
        $story = $this->story();
        if ($story) {
            if ($status === 'on' && (Translations::all($story)[$code]['outdated'] ?? false)) {
                Notification::make()->warning()->title('This language is out of date.')->body('The English text changed after it was made. Make it again, or read it with "Edit the text" and save it. Then you can show it.')->send();

                return;
            }
            Translations::set($story, $code, $status);
        }
    }

    /** @return \Illuminate\Support\Collection<int, StoryRun> */
    public function runs()
    {
        return StoryRun::query()->with('story')->where('kind', 'translation')->latest('id')->limit(60)->get();
    }

    public function openRun(int $id): void
    {
        $run = StoryRun::query()->where('kind', 'translation')->find($id);
        if (! $run || ! $run->story_id || ! Story::find($run->story_id)) {
            return;
        }
        $this->storyId = $run->story_id;
        $this->runId = $run->id;
        $this->codes = array_values(array_intersect((array) ($run->options['languages'] ?? []), array_keys(Rules::languages()))) ?: array_keys(Rules::languages());
        $this->paste = (string) $run->answer;
        $this->result = (array) $run->result;
        $this->checked = $this->result !== [];
        $this->done = null;
        Notification::make()->success()->title('Run opened. The prompt is made again from the saved story and choices.')->send();
    }

    public function deleteRun(int $id): void
    {
        StoryRun::query()->where('kind', 'translation')->whereKey($id)->delete();
        if ($this->runId === $id) {
            $this->reset(['runId', 'paste', 'result', 'checked', 'done']);
        }
    }

    // ---------- the rules (the super user only) ----------

    public function saveRules(): void
    {
        abort_unless(auth()->user()?->isSuperuser(), 403);
        Rules::saveTranslation($this->ruleText);
        foreach ($this->langEdits as $code => $edit) {
            Rules::saveLanguage($code, (string) ($edit['quran'] ?? ''), (string) ($edit['words'] ?? ''));
        }
        $this->loadRules();
        Notification::make()->success()->title('The translation rules and the word lists are saved.')->send();
    }

    public function resetRules(): void
    {
        abort_unless(auth()->user()?->isSuperuser(), 403);
        Rules::resetTranslation();
        foreach (array_keys(Rules::languageDefaults()) as $code) {
            Rules::resetLanguage($code);
        }
        $this->loadRules();
        Notification::make()->success()->title('The original translation rules and word lists are back.')->send();
    }
}
