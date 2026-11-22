<?php

namespace App\Filament\Pages;

use App\Models\AiPrompt;
use App\Models\Article;
use App\Support\IndexEntries;
use App\Models\Category;
use App\Models\ImportLog;
use App\Support\AiImport as Importer;
use App\Support\ArticleReplace;
use App\Support\Prompts;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * AI import, in three steps on one screen:
 * 1. make the prompt and copy it into an AI chat, 2. paste the answer and press Check, 3. save what is right as drafts.
 * Nothing is ever published from here.
 */
class AiImport extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSparkles;

    protected static string|UnitEnum|null $navigationGroup = 'Tools';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'AI import';

    /** It is opened from the Create screen and the "+ Create" button, so it is not repeated in the side menu. */
    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    protected static ?string $title = 'AI import';

    protected string $view = 'filament.pages.ai-import';

    public string $kind = 'research';

    public string $topic = '';

    /** The form of a researched article. */
    public ?int $categoryId = null;

    public string $specialist = '';

    /** The number box can be emptied while somebody types a new number, so it may be empty for a moment. */
    public int|string|null $words = 1500;

    public string $depth = 'normal';

    public string $comments = '';

    /** A text the contributor already has: the prompt then only puts it into the shape of an article. */
    public string $ownText = '';

    /** Also ask the AI for word notes (explainers) for the hard words of the article. */
    public bool $explainers = true;

    /** The blank index entry this article fills (chosen on the form, or opened from the Articles list). */
    public ?int $entryId = null;

    public string $entryQuery = '';

    /** The existing article that the answer replaces (optional). A published article stays published. */
    public ?int $replaceId = null;

    public string $replaceQuery = '';

    /** Ticked by the person to confirm that a published article gets the new text at once. */
    public bool $confirmReplace = false;

    public function mount(): void
    {
        $id = (int) request()->query('entry');
        if ($id > 0 && ($entry = Article::find($id)) && IndexEntries::isBlank($entry)) {
            $this->chooseEntry($entry);
        }
        $replace = (int) request()->query('replace');
        if ($replace > 0 && ($target = Article::find($replace)) && ArticleReplace::canReplace($target, auth()->user())) {
            $this->chooseReplace($target);
        }
    }

    /** The article to replace, as an Article (or null when it is gone or this person may not replace it). */
    public function replaceTarget(): ?Article
    {
        $target = $this->replaceId ? Article::query()->with('category')->find($this->replaceId) : null;

        return $target && ArticleReplace::canReplace($target, auth()->user()) ? $target : null;
    }

    /** @return \Illuminate\Support\Collection<int, Article> */
    public function replaceResults()
    {
        return ArticleReplace::search($this->replaceQuery, auth()->user());
    }

    public function selectReplace(int $id): void
    {
        $target = Article::find($id);
        if ($target && ArticleReplace::canReplace($target, auth()->user())) {
            $this->chooseReplace($target);
        }
        $this->replaceQuery = '';
    }

    public function clearReplace(): void
    {
        $this->replaceId = null;
        $this->confirmReplace = false;
    }

    private function chooseReplace(Article $target): void
    {
        $this->replaceId = $target->id;
        $this->entryId = null;
        $this->confirmReplace = false;
        $this->categoryId = $target->category_id;
        $this->topic = $target->mainTitle();
        $this->specialist = AiPrompt::suggestFor($target->category) ?? $this->specialist;
    }

    /** The entry chosen, as an Article (or null). */
    public function entry(): ?Article
    {
        $entry = $this->entryId ? Article::query()->with('category')->find($this->entryId) : null;

        return $entry && IndexEntries::isBlank($entry) ? $entry : null;
    }

    /** @return \Illuminate\Support\Collection<int, Article> */
    public function entryResults()
    {
        return trim($this->entryQuery) === '' ? collect() : IndexEntries::search($this->entryQuery, $this->categoryId);
    }

    public function selectEntry(int $id): void
    {
        $entry = Article::find($id);
        if ($entry && IndexEntries::isBlank($entry)) {
            $this->chooseEntry($entry);
        }
        $this->entryQuery = '';
    }

    public function clearEntry(): void
    {
        $this->entryId = null;
    }

    /** From a suggestion in the result: fill that entry instead, and check again. */
    public function useEntry(int $id): void
    {
        $this->selectEntry($id);
        $this->check();
    }

    private function chooseEntry(Article $entry): void
    {
        $this->entryId = $entry->id;
        $this->replaceId = null;
        $this->confirmReplace = false;
        $this->categoryId = $entry->category_id;
        if (trim($this->topic) === '') {
            $this->topic = $entry->mainTitle();
        }
        $this->specialist = AiPrompt::suggestFor($entry->category) ?? $this->specialist;
    }    public string $paste = '';

    /** @var array<int, array<string, mixed>> */
    public array $items = [];

    public bool $checked = false;

    /** @var array<string, mixed>|null */
    public ?array $done = null;

    public static function canAccess(): bool
    {
        return auth()->user()?->canUseScreen('import') ?? false;
    }

    public function prompt(): string
    {
        if ($this->kind === 'research') {
            return Prompts::research([
                'topic' => $this->topic, 'category_id' => $this->categoryId, 'specialist' => $this->specialist ?: null,
                'words' => $this->wordsCount(), 'depth' => $this->depth, 'comments' => $this->comments, 'explainers' => $this->explainers,
                'own_text' => $this->ownText,
                'entry' => ($e = $this->entry()) ? IndexEntries::label($e).' '.$e->mainTitle() : null,
            ]);
        }

        return Prompts::make(array_key_exists($this->kind, Prompts::KINDS) ? $this->kind : 'article', $this->topic);
    }

    /** The category decides which specialist prompt is suggested. The contributor can still change it. */
    public function updatedCategoryId(): void
    {
        $this->categoryId = $this->categoryId ?: null;
        $this->specialist = AiPrompt::suggestFor($this->categoryId ? Category::find($this->categoryId) : null) ?? $this->specialist;
    }

    /** Lines in "I already have an article text" that look like the talk of an AI chat. */
    public function ownTextLeftovers(): array
    {
        return trim($this->ownText) === '' ? [] : Importer::chatLeftovers($this->ownText);
    }

    public function updatedWords(): void
    {
        if (trim((string) $this->words) === '') {
            return;   // being retyped: wait for the new number
        }
        $this->words = max(300, min(8000, (int) $this->words));
    }

    /** The length asked for: the number typed, kept between 300 and 8000, or 1500 while the box is empty. */
    public function wordsCount(): int
    {
        return trim((string) $this->words) === '' ? 1500 : max(300, min(8000, (int) $this->words));
    }

    /** @return array<int, string> */
    public function categoryChoices(): array
    {
        return Category::leafChoices();
    }

    /** @return array<string, string> */
    public function specialists(): array
    {
        return AiPrompt::query()->where('kind', 'specialist')->orderBy('sort_order')->pluck('title', 'slug')->all();
    }

    /** @return array<string, string> */
    public function depths(): array
    {
        return Prompts::DEPTHS;
    }

    public function kinds(): array
    {
        return Prompts::KINDS;
    }

    public function check(): void
    {
        $this->done = null;
        if (trim($this->paste) === '') {
            $this->items = [];
            $this->checked = false;
            Notification::make()->warning()->title('Paste the answer of the AI first.')->send();

            return;
        }
        $this->items = Importer::parse($this->paste, true, $this->entryId, $this->replaceTarget()?->id);
        $this->checked = true;
    }

    public function save(): void
    {
        // The pasted text is read again here, so what is saved is never what a browser claims it checked.
        $target = $this->replaceTarget();
        $items = Importer::parse($this->paste, true, $this->entryId, $target?->id);
        $user = auth()->user();

        // A published article gets the new text at once, so the person must tick the box first.
        if ($target && ArticleReplace::isLive($target) && ! $this->confirmReplace
            && collect($items)->contains(fn ($i) => ! empty($i['data']['replace_id']) && $i['errors'] === [])) {
            Notification::make()->danger()->title('Tick the box first.')
                ->body($target->code.' is published. Tick "I understand: visitors will see the new text at once" before you replace it.')->send();

            return;
        }

        if (collect($items)->contains(fn ($i) => $i['kind'] === 'categories' && $i['errors'] === []) && ! $user->canUseScreen('categories')) {
            Notification::make()->danger()->title('You may not add categories.')->body('Ask the super user to tick the Categories screen for you.')->send();

            return;
        }
        if (collect($items)->every(fn ($i) => $i['errors'] !== [])) {
            $this->items = $items;
            Notification::make()->danger()->title('Nothing can be saved yet.')->body('Fix the problems shown, paste again and press Check.')->send();

            return;
        }

        $made = Importer::commit($items, $this->paste, $user);
        $this->done = $made;
        $this->entryId = null;       // the entry is filled now; the next article starts fresh
        $this->replaceId = null;
        $this->confirmReplace = false;
        $this->paste = '';
        $this->items = [];
        $this->checked = false;

        Notification::make()->success()->title('Saved.')
            ->body((count($made['articles']) - count($made['replaced'] ?? [])).' draft article(s), '.count($made['replaced'] ?? []).' replaced, '.count($made['categories']).' new hidden categor'.(count($made['categories']) === 1 ? 'y' : 'ies').', '.$made['skipped'].' item(s) skipped because of a problem.')
            ->send();
    }

    /** @return \Illuminate\Support\Collection<int, ImportLog> */
    public function recent()
    {
        return ImportLog::with('user')->orderByDesc('id')->limit(5)->get();
    }

    public function okCount(): int
    {
        return collect($this->items)->filter(fn ($i) => $i['errors'] === [])->count();
    }
}
