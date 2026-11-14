<?php

namespace App\Filament\Pages;

use App\Models\AiPrompt;
use App\Models\Category;
use App\Models\ImportLog;
use App\Support\AiImport as Importer;
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

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'AI import';

    protected static ?string $title = 'AI import';

    protected string $view = 'filament.pages.ai-import';

    public string $kind = 'research';

    public string $topic = '';

    /** The form of a researched article. */
    public ?int $categoryId = null;

    public string $specialist = '';

    public int $words = 1500;

    public string $depth = 'normal';

    public string $comments = '';
    public string $paste = '';

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
                'words' => $this->words, 'depth' => $this->depth, 'comments' => $this->comments,
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

    public function updatedWords(): void
    {
        $this->words = max(300, min(8000, (int) $this->words));
    }

    /** @return array<int, string> */
    public function categoryChoices(): array
    {
        return Category::choices();
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
        $this->items = Importer::parse($this->paste);
        $this->checked = true;
    }

    public function save(): void
    {
        // The pasted text is read again here, so what is saved is never what a browser claims it checked.
        $items = Importer::parse($this->paste);
        $user = auth()->user();

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
        $this->paste = '';
        $this->items = [];
        $this->checked = false;

        Notification::make()->success()->title('Saved.')
            ->body(count($made['articles']).' draft article(s), '.count($made['categories']).' new hidden categor'.(count($made['categories']) === 1 ? 'y' : 'ies').', '.$made['skipped'].' item(s) skipped because of a problem.')
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
