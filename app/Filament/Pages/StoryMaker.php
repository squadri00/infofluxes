<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Stories\StoryResource;
use App\Models\Category;
use App\Models\StoryRun;
use App\Support\StoryMaker\Prompt;
use App\Support\StoryMaker\Reader;
use App\Support\StoryMaker\Rules;
use App\Support\StoryMaker\Saver;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Story maker: makes the prompt for a story in pictures, checks the answer of the AI and saves it as a draft story.
 * It is its own tool: it does not use the AI import or the AI prompts of the articles. Every run is kept, so it can be opened again.
 * Step 1 is the story in English. (The other languages come in step 2, later.)
 */
class StoryMaker extends Page
{
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

    public string $notes = '';

    public string $style = '';

    public string $paste = '';

    /** @var array<string, mixed> what the last check found (errors, warnings, check, story) */
    public array $result = [];

    public bool $checked = false;

    /** @var array<string, mixed>|null */
    public ?array $done = null;

    public string $ruleText = '';

    public string $ruleStyle = '';

    public static function canAccess(): bool
    {
        return auth()->user()?->canUseScreen('storymaker') ?? false;
    }

    public function mount(): void
    {
        $this->style = Rules::style();
        $this->ruleText = Rules::master();
        $this->ruleStyle = Rules::style();
    }

    /** @return array<int, string> */
    public function categoryChoices(): array
    {
        return Category::choices();
    }

    public function prompt(): string
    {
        return Prompt::english(['topic' => $this->topic, 'category_id' => $this->categoryId, 'slides' => $this->slides, 'notes' => $this->notes, 'style' => $this->style]);
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
            'options' => ['slides' => $this->slides, 'notes' => $this->notes, 'style' => $this->style],
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
        $this->result = Reader::read($this->paste, $this->run()?->story_id);
        $this->checked = true;
        $this->persist('checked');
    }

    public function saveStory(): void
    {
        // The pasted text is read again here, so what is saved is never what a browser claims it checked.
        $read = Reader::read($this->paste, $this->run()?->story_id);
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

        $run = $this->persist('checked');
        $updating = (bool) $run->story_id;
        try {
            $story = Saver::save($read['story'], $this->categoryId, $run, auth()->user());
        } catch (\RuntimeException $e) {
            Notification::make()->danger()->title($e->getMessage())->send();

            return;
        }
        $run->update(['story_id' => $story->id, 'status' => 'saved', 'result' => $read]);
        $this->done = [
            'code' => $story->code, 'title' => $story->mainTitle(), 'updated' => $updating,
            'url' => StoryResource::getUrl('edit', ['record' => $story]),
            'slides' => $read['story']['slide_count'],
        ];
        Notification::make()->success()->title($updating ? 'The draft story '.$story->code.' is updated.' : 'Saved as the draft story '.$story->code.'.')->send();
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
        $this->notes = (string) ($o['notes'] ?? '');
        $this->style = (string) ($o['style'] ?? '') ?: Rules::style();
        $this->paste = (string) $run->answer;
        $this->result = (array) $run->result;
        $this->checked = $this->result !== [];
        $this->done = null;
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
        $this->reset(['runId', 'topic', 'categoryId', 'notes', 'paste', 'result', 'checked', 'done']);
        $this->slides = 8;
        $this->style = Rules::style();
    }

    // ---------- the rules (the super user only) ----------

    public function saveRules(): void
    {
        abort_unless(auth()->user()?->isSuperuser(), 403);
        Rules::save($this->ruleText);
        Rules::saveStyle($this->ruleStyle);
        $this->ruleText = Rules::master();
        $this->ruleStyle = Rules::style();
        Notification::make()->success()->title('The rules of the Story maker are saved.')->send();
    }

    public function resetRules(): void
    {
        abort_unless(auth()->user()?->isSuperuser(), 403);
        Rules::reset();
        Rules::saveStyle('');
        $this->ruleText = Rules::master();
        $this->ruleStyle = Rules::style();
        Notification::make()->success()->title('The original rules are back.')->send();
    }
}
