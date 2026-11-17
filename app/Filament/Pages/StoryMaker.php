<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Stories\StoryResource;
use App\Models\Category;
use App\Models\Story;
use App\Models\StoryRun;
use App\Support\StoryMaker\Pictures;
use App\Support\StoryMaker\Prompt;
use App\Support\StoryMaker\Reader;
use App\Support\StoryMaker\Rules;
use App\Support\StoryMaker\Saver;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Livewire\WithFileUploads;
use UnitEnum;

/**
 * Story maker: makes the prompt for a story in pictures, checks the answer of the AI and saves it as a draft story.
 * It is its own tool: it does not use the AI import or the AI prompts of the articles. Every run is kept, so it can be opened again.
 * Step 1 is the story in English. (The other languages come in step 2, later.)
 */
class StoryMaker extends Page
{
    use WithFileUploads;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 9;

    protected static ?string $navigationLabel = 'Story maker';

    protected static ?string $title = 'Story maker';

    protected static ?string $slug = 'story-maker';

    protected string $view = 'filament.pages.story-maker';

    public ?int $runId = null;

    public string $topic = '';

    public ?int $categoryId = null;

    public int $slides = 8;

    /** On when a saved draft is being told again in a story voice (see startRewrite). */
    public bool $rewrite = false;

    /** How long each slide is: short, story or extra (see Rules::lengths()). */
    public string $length = 'story';

    /** On: the AI decides the length of each slide and the number of slides, so the story can flow freely. */
    public bool $openFlow = false;

    public string $notes = '';

    public string $style = '';

    public string $paste = '';

    /** @var array<string, mixed> what the last check found (errors, warnings, check, story) */
    public array $result = [];

    public bool $checked = false;

    /** The panel that shows the whole story is open / was opened for this answer (the safety "read it first" needs the second). */
    public bool $readOpen = false;

    public bool $readOpened = false;

    /** @var array<string, mixed>|null */
    public ?array $done = null;

    public string $ruleText = '';

    public string $ruleStyle = '';

    /** @var array<string, array{min: int, max: int}> the words of each length (super user) */
    public array $lengthEdits = [];

    public bool $ruleRequireRead = false;

    // ---------- the pictures (bulk upload) ----------

    /** The story whose pictures are being added. */
    public ?int $pictureStoryId = null;

    /** @var array<int, \Livewire\Features\SupportFileUploads\TemporaryUploadedFile> */
    public array $pictures = [];

    public bool $firstIsCover = true;

    public bool $replacePictures = false;

    /** name = by file name (the usual); chosen = the order set with the arrows */
    public string $pictureOrder = 'name';

    /** @var array<string, mixed>|null what the last upload did */
    public ?array $pictureReport = null;

    public static function canAccess(): bool
    {
        return auth()->user()?->canUseScreen('storymaker') ?? false;
    }

    public function mount(): void
    {
        $this->style = Rules::style();
        $this->ruleText = Rules::master();
        $this->ruleStyle = Rules::style();
        $this->loadLengths();
    }

    private function loadLengths(): void
    {
        $this->ruleRequireRead = Rules::requireRead();
        $this->lengthEdits = [];
        foreach (Rules::lengths() as $code => $row) {
            $this->lengthEdits[$code] = ['min' => $row['min'], 'max' => $row['max']];
        }
    }

    /** @return array<string, array{label: string, min: int, max: int, extra: bool, about: string}> */
    public function lengths(): array
    {
        return Rules::lengths();
    }

    public function openReading(): void
    {
        $this->readOpen = true;
        $this->readOpened = true;
    }

    public function closeReading(): void
    {
        $this->readOpen = false;
    }

    /** A new answer is a new thing to read. */
    public function updatedPaste(): void
    {
        $this->readOpen = false;
        $this->readOpened = false;
    }

    /** The notes of the check that belong to one slide, by the number of the slide. @return array<int, list<string>> */
    public function slideNotes(): array
    {
        return Reader::warningsBySlide((array) ($this->result['warnings'] ?? []));
    }

    /**
     * When this run updates a draft that already has pictures and the new text has other chapters or slides, the pictures (which are kept by their
     * place) may end up on the wrong slide. Said before saving.
     */
    public function updateNote(): ?string
    {
        $run = $this->run();
        $story = $run?->story_id ? Story::query()->with('chapters.slides')->find($run->story_id) : null;
        if (! $story || $story->status !== 'draft' || ! $story->slides()->whereNotNull('image')->exists()) {
            return null;
        }
        $old = $story->chapters->map(fn ($c) => $c->slides->count())->all();
        $new = collect((array) ($this->result['story']['chapters'] ?? []))->map(fn ($c) => count((array) ($c['slides'] ?? [])))->all();
        if ($old === $new) {
            return null;
        }

        return 'This run updates the draft '.$story->code.'. It has '.array_sum($old).' slides in '.count($old).' chapters, the new text has '.array_sum($new).' slides in '.count($new)
            .' chapters. The pictures you added stay in the same places (the first picture stays on the first slide, and so on), so some may no longer fit their slide. Check them after saving.';
    }

    /** @return array<int, string> */
    public function categoryChoices(): array
    {
        return Category::choices();
    }

    /** The saved draft that is being told again, when the "rewrite in story voice" mode is on. */
    public function rewriteStory(): ?Story
    {
        if (! $this->rewrite) {
            return null;
        }
        $story = $this->run()?->story;

        return $story && $story->status === 'draft' ? $story : null;
    }

    /** Opens a saved run whose story is still a draft, and makes the prompt that asks the AI to tell the same story again as one connected story. */
    public function startRewrite(int $runId): void
    {
        $run = StoryRun::query()->where('kind', 'story')->with('story')->find($runId);
        if (! $run?->story || $run->story->status !== 'draft') {
            Notification::make()->warning()->title('Only a story that is still a draft can be told again.')->send();

            return;
        }
        $this->openRun($runId);
        $this->rewrite = true;
        $this->paste = '';
        $this->result = [];
        $this->checked = false;
        $this->slides = max(1, (int) $run->story->chapters()->withCount('slides')->get()->sum('slides_count'));
        $this->persist('prompt');
    }

    public function stopRewrite(): void
    {
        $this->rewrite = false;
        if ($this->run()) {
            $this->persist('prompt');
        }
    }

    public function prompt(): string
    {
        if ($story = $this->rewriteStory()) {
            return Prompt::rewrite($story, ['notes' => $this->notes, 'style' => $this->style, 'length' => $this->length, 'open' => $this->openFlow]);
        }

        return Prompt::english(['topic' => $this->topic, 'category_id' => $this->categoryId, 'slides' => $this->slides, 'notes' => $this->notes, 'style' => $this->style, 'length' => $this->length, 'open' => $this->openFlow]);
    }

    public function updatedSlides(): void
    {
        $this->slides = max(Prompt::MIN_SLIDES, min(Prompt::MAX_SLIDES, (int) $this->slides));
    }

    public function updatedCategoryId(): void
    {
        $this->categoryId = $this->categoryId ?: null;
    }

    public function run(): ?StoryRun
    {
        return $this->runId ? StoryRun::find($this->runId) : null;
    }

    /** Keeps this run: the form, the prompt, the pasted answer and what the last check found. A saved run is never put back to an earlier state. */
    private function persist(string $status): StoryRun
    {
        $run = $this->run() ?? new StoryRun(['kind' => 'story', 'user_id' => auth()->id()]);
        $order = array_keys(StoryRun::STATUSES);
        if ($run->exists && array_search($run->status, $order, true) > array_search($status, $order, true)) {
            $status = $run->status;
        }
        $run->fill([
            'topic' => mb_substr(trim($this->topic), 0, 300),
            'category_id' => $this->categoryId,
            'options' => ['slides' => $this->slides, 'notes' => $this->notes, 'style' => $this->style, 'length' => $this->length, 'rewrite' => $this->rewrite, 'open' => $this->openFlow],
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
        if (trim($this->topic) === '' && trim($this->paste) === '') {
            Notification::make()->warning()->title('Write the subject first.')->send();

            return;
        }
        $this->persist(trim($this->paste) !== '' ? 'pasted' : 'prompt');
        Notification::make()->success()->title('This run is saved. You can open it again from "Saved runs" below.')->send();
    }

    public function check(): void
    {
        $this->done = null;
        if (trim($this->paste) === '') {
            $this->result = [];
            $this->checked = false;
            Notification::make()->warning()->title('Paste the answer of the AI first.')->send();

            return;
        }
        $this->result = Reader::read($this->paste, $this->run()?->story_id, $this->slides, $this->length, $this->openFlow);
        $this->checked = true;
        $this->persist('checked');
    }

    public function saveStory(): void
    {
        // The pasted text is read again here, so what is saved is never what a browser claims it checked.
        $read = Reader::read($this->paste, $this->run()?->story_id, $this->slides, $this->length, $this->openFlow);
        $this->result = $read;
        $this->checked = true;

        if ($read['errors'] !== []) {
            Notification::make()->danger()->title('The story cannot be saved yet.')->body('Fix the problems that are shown, or ask the AI again.')->send();

            return;
        }
        if (! $this->categoryId) {
            Notification::make()->danger()->title('Choose the category first.')->send();

            return;
        }
        if (Rules::requireRead() && ! $this->readOpened) {
            Notification::make()->warning()->title('Read the whole story first.')->body('Press "Read the whole story" and read it. Then you can save it as a draft.')->send();

            return;
        }
        $note = $this->updateNote();

        $run = $this->persist('checked');
        $updating = (bool) $run->story_id;
        try {
            $story = Saver::save($read['story'], $this->categoryId, $run, auth()->user());
        } catch (\RuntimeException $e) {
            Notification::make()->danger()->title($e->getMessage())->send();

            return;
        }
        $run->update(['story_id' => $story->id, 'status' => 'saved', 'result' => $read]);
        $this->openPicturesOf($story);
        $this->done = [
            'code' => $story->code, 'title' => $story->mainTitle(), 'updated' => $updating,
            'url' => StoryResource::getUrl('edit', ['record' => $story]),
            'slides' => $read['story']['slide_count'],
        ];
        Notification::make()->success()->title($updating ? 'The draft story '.$story->code.' is updated.' : 'Saved as the draft story '.$story->code.'.')
            ->body($note ?? ($updating && ($n = count(\App\Support\StoryMaker\Translations::all($story))) > 0 ? 'The English text was updated, so its '.$n.' saved language(s) are hidden and marked out of date.' : null))->send();
    }

    /** @return \Illuminate\Support\Collection<int, StoryRun> */
    public function runs()
    {
        return StoryRun::query()->with('story')->where('kind', 'story')->latest('id')->limit(60)->get();
    }

    public function openRun(int $id): void
    {
        $run = StoryRun::query()->where('kind', 'story')->find($id);
        if (! $run) {
            return;
        }
        $o = (array) $run->options;
        $this->runId = $run->id;
        $this->topic = (string) $run->topic;
        $this->categoryId = $run->category_id;
        $this->slides = (int) ($o['slides'] ?? 8);
        $this->length = isset(Rules::lengths()[(string) ($o['length'] ?? '')]) ? (string) $o['length'] : 'story';
        $this->openFlow = (bool) ($o['open'] ?? false);
        $this->notes = (string) ($o['notes'] ?? '');
        $this->rewrite = (bool) ($o['rewrite'] ?? false);
        $this->style = (string) ($o['style'] ?? '') ?: Rules::style();
        $this->paste = (string) $run->answer;
        $this->result = (array) $run->result;
        $this->checked = $this->result !== [];
        $this->done = null;
        $this->readOpen = false;
        $this->readOpened = false;
        Notification::make()->success()->title('Run opened. The prompt is made again from the saved choices.')->send();
    }

    public function deleteRun(int $id): void
    {
        StoryRun::query()->where('kind', 'story')->whereKey($id)->delete();
        if ($this->runId === $id) {
            $this->newRun();
        }
    }

    public function newRun(): void
    {
        $this->reset(['runId', 'topic', 'categoryId', 'notes', 'paste', 'result', 'checked', 'done', 'readOpen', 'readOpened', 'rewrite', 'openFlow']);
        $this->slides = 8;
        $this->length = 'story';
        $this->style = Rules::style();
    }

    // ---------- the pictures ----------

    public function pictureStory(): ?Story
    {
        return $this->pictureStoryId ? Story::query()->with('chapters.slides')->find($this->pictureStoryId) : null;
    }

    private function openPicturesOf(Story $story): void
    {
        $this->pictureStoryId = $story->id;
        $this->pictures = [];
        $this->pictureReport = null;
        $this->pictureOrder = 'name';
        $this->replacePictures = false;
        $this->firstIsCover = blank($story->main_image);   // the first picture is the cover while the story has none
    }

    public function openPictures(int $runId): void
    {
        $story = StoryRun::query()->find($runId)?->story;
        if ($story) {
            $this->openPicturesOf($story);
        }
    }

    public function closePictures(): void
    {
        $this->pictureStoryId = null;
        $this->pictures = [];
        $this->pictureReport = null;
    }

    /** The files in the order they will be used. */
    public function orderedPictures(): array
    {
        return Pictures::order($this->pictures, $this->pictureOrder);
    }

    public function picturePlan(): ?array
    {
        $story = $this->pictureStory();

        return $story ? Pictures::plan($story, count($this->pictures), $this->firstIsCover, $this->replacePictures) : null;
    }

    public function updatedPictures(): void
    {
        $this->pictureReport = null;
        $this->pictureOrder = 'name';
        if (count($this->pictures) > Pictures::MAX_FILES) {
            $this->pictures = array_slice($this->pictures, 0, Pictures::MAX_FILES);
            Notification::make()->warning()->title('Only the first '.Pictures::MAX_FILES.' pictures are used.')->send();
        }
    }

    public function movePicture(int $index, int $step): void
    {
        $files = $this->orderedPictures();
        $to = $index + $step;
        if (! isset($files[$index], $files[$to])) {
            return;
        }
        [$files[$index], $files[$to]] = [$files[$to], $files[$index]];
        $this->pictures = $files;
        $this->pictureOrder = 'chosen';
    }

    public function removePicture(int $index): void
    {
        $files = $this->orderedPictures();
        unset($files[$index]);
        $this->pictures = array_values($files);
        $this->pictureOrder = 'chosen';
    }

    public function clearPictures(): void
    {
        $this->pictures = [];
        $this->pictureReport = null;
    }

    public function applyPictures(): void
    {
        $story = $this->pictureStory();
        if (! $story) {
            return;
        }
        if ($this->pictures === []) {
            Notification::make()->warning()->title('Choose the pictures first.')->send();

            return;
        }
        $this->validate(['pictures.*' => ['image', 'mimes:jpg,jpeg,png,webp,gif', 'max:12288']], [], ['pictures.*' => 'picture']);

        $this->pictureReport = Pictures::apply($story, $this->orderedPictures(), $this->firstIsCover, $this->replacePictures);
        $this->pictures = [];
        $this->pictureOrder = 'name';
        $story->refresh();
        $this->firstIsCover = blank($story->main_image);
        Notification::make()->success()->title('The pictures are on the story.')->send();
    }

    public function pictureLines(): string
    {
        $story = $this->pictureStory();

        return $story ? Pictures::lines($story, (string) ($story->custom_data['picture_style'] ?? Rules::style())) : '';
    }

    // ---------- the rules (the super user only) ----------

    public function saveRules(): void
    {
        abort_unless(auth()->user()?->isSuperuser(), 403);
        Rules::save($this->ruleText);
        Rules::saveStyle($this->ruleStyle);
        Rules::saveLengths($this->lengthEdits);
        Rules::saveRequireRead($this->ruleRequireRead);
        $this->ruleText = Rules::master();
        $this->ruleStyle = Rules::style();
        $this->loadLengths();
        Notification::make()->success()->title('The rules of the Story maker are saved.')->send();
    }

    public function resetRules(): void
    {
        abort_unless(auth()->user()?->isSuperuser(), 403);
        Rules::reset();
        Rules::saveStyle('');
        Rules::resetLengths();
        Rules::saveRequireRead(false);
        $this->ruleText = Rules::master();
        $this->ruleStyle = Rules::style();
        $this->loadLengths();
        Notification::make()->success()->title('The original rules are back.')->send();
    }
}
