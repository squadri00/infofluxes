<?php

namespace App\Support;

use App\Models\Article;
use App\Models\Category;
use App\Models\User;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;

/**
 * Moves the categories and the subject indexes of the OLD InfoFluxes site into this site.
 *
 * - The six main menu headings become top categories.
 * - Each index becomes a sub-category of its heading (Quran Index, Allah (SWT) Index, ...), then its Parts, main categories and
 *   sub-categories become sub-categories below it, in the same order.
 * - Each index topic (the lines with a code like 1.1.001) becomes an EMPTY DRAFT article in its category: the "still to write" list.
 *   The old public ID (for example AL-1.1.001) is kept as the article's working name.
 * - It never changes a category that already exists (it finds it by name and uses it), and it never makes an article twice.
 *   It can be run again safely. check() writes nothing.
 * - The old database is only read.
 */
class OldSiteImport
{
    /** The main menu headings, in order. */
    public const HEADINGS = ['About Islam', 'History and Civilizations', 'Science and Knowledge', 'Comparative Religions', 'Society and World Affairs', 'Fact Files'];

    /**
     * collection => [the heading it sits under, the names the index category may already have (the first is used for a new one), whether it has Parts].
     * A collection with no wrapper name is the content of the heading itself.
     */
    public const COLLECTIONS = [
        'quran' => ['About Islam', ['Quran Index', 'The Quran'], true],
        'allah' => ['About Islam', ['Allah (SWT) Index'], true],
        'sharia' => ['About Islam', ['Sharia Index'], true],
        'history' => ['History and Civilizations', [], false],
        'science' => ['Science and Knowledge', [], false],
        'bible' => ['Comparative Religions', ['Bible Index', 'The Bible'], true],
        'hinduism' => ['Comparative Religions', ['Hinduism Index', 'Hinduism'], true],
        'society' => ['Society and World Affairs', [], false],
    ];

    private bool $dry = true;

    private int $fake = 0;

    /** @var array<string, int> pending categories in a dry run: "parentId|name" => fake id */
    private array $pending = [];

    /** @var array<string, array<string, int>> */
    private array $report = [];

    private ?int $authorId = null;

    private ConnectionInterface $old;

    public function __construct()
    {
        $this->old = DB::connection('old_site');
    }

    /** Can the old database be read? (False on the live site.) */
    public static function available(): bool
    {
        try {
            return filled(config('database.connections.old_site.database'))
                && \Illuminate\Support\Facades\Schema::connection('old_site')->hasTable('index_entries');
        } catch (\Throwable) {
            return false;
        }
    }

    /** What would be made. Writes nothing. */
    public function check(): array
    {
        return $this->run(true);
    }

    /** Make it. All or nothing: if anything goes wrong, nothing is saved. */
    public function move(?User $author = null): array
    {
        $this->authorId = $author?->id;

        return $this->run(false);
    }

    private function run(bool $dry): array
    {
        $this->dry = $dry;
        $this->fake = 0;
        $this->pending = [];
        $this->report = ['headings' => $this->emptyRow()];

        if ($dry) {
            $this->all();

            return $this->summary();
        }

        Category::$pauseRebuild = true;
        try {
            DB::transaction(fn () => $this->all());
        } finally {
            Category::$pauseRebuild = false;
        }
        Category::rebuildPaths();

        return $this->summary();
    }

    private function all(): void
    {
        $roots = $this->headings();

        foreach (self::COLLECTIONS as $collection => [$heading, $wrapperNames, $hasParts]) {
            $this->report[$collection] = $this->emptyRow();
            $entries = $this->old->table('index_entries')->where('collection', $collection)->whereNull('deleted_at')->orderBy('level')->orderBy('sort_order')->orderBy('id')->get();
            if ($entries->isEmpty()) {
                continue;
            }

            $headingId = $roots[$heading];
            $wrapperId = $headingId;
            if ($wrapperNames !== []) {
                $wrapperId = $this->category($collection, $headingId, $wrapperNames, null, 0);
            }

            /** @var array<int, int> old entry id => category id */
            $map = [];
            foreach ($entries as $entry) {
                if ((int) $entry->level === 3) {
                    continue;
                }
                $parentEntryCategory = $entry->parent_id ? ($map[$entry->parent_id] ?? null) : null;
                $parentId = $parentEntryCategory ?? $wrapperId;

                // An index without Parts has one main category called like its heading: that is the heading itself.
                if (! $hasParts && (int) $entry->level === 1 && mb_strtolower(trim($entry->title)) === mb_strtolower($heading)) {
                    $map[$entry->id] = $headingId;

                    continue;
                }
                $map[$entry->id] = $this->category($collection, $parentId, [trim($entry->title)], $entry, $entry->sort_order);
            }

            foreach ($entries->filter(fn ($e) => (int) $e->level === 3) as $topic) {
                $categoryId = $map[$topic->parent_id] ?? null;
                if ($categoryId === null) {
                    continue;
                }
                $this->article($collection, $categoryId, $topic);
            }
        }
    }

    /** @return array<string, int> heading name => category id */
    private function headings(): array
    {
        $ids = [];
        $oldCategories = $this->old->table('categories')->whereNull('parent_id')->whereNull('deleted_at')->get()->keyBy(fn ($c) => mb_strtolower($c->name));
        foreach (self::HEADINGS as $i => $name) {
            $old = $oldCategories->get(mb_strtolower($name));
            $ids[$name] = $this->category('headings', null, [$name], null, ($i + 1), $old->description ?? null, true);
        }

        return $ids;
    }

    /**
     * The category called one of $names under $parentId: the one that is there, or a new one.
     *
     * @param  list<string>  $names  the names it may already have (the first is used for a new one)
     */
    private function category(string $group, ?int $parentId, array $names, ?object $entry, int $oldOrder, ?string $description = null, bool $isHeading = false): int
    {
        $existing = $this->findExisting($parentId, $names);
        if ($existing !== null) {
            $this->report[$group]['categories_existing']++;

            return $existing;
        }

        $this->report[$group]['categories_new']++;
        $key = ($parentId ?? 0).'|'.mb_strtolower($names[0]);
        if ($this->dry) {
            return $this->pending[$key] = $this->pending[$key] ?? --$this->fake;
        }

        $locale = config('infofluxes.default_locale');
        $text = $entry ? trim((string) ($entry->note ?? '')) : trim((string) $description);
        $category = Category::create([
            'name' => [$locale => $names[0]],
            'slug' => Category::uniqueSlug($names[0]),
            'parent_id' => $parentId,
            'working_name' => $entry->public_id ?? null,
            'description' => $text !== '' ? [$locale => $text] : null,
            'sort_order' => max(0, $oldOrder) * 10,
            'is_visible' => true,
        ]);

        return $category->id;
    }

    /** @param list<string> $names */
    private function findExisting(?int $parentId, array $names): ?int
    {
        foreach ($names as $name) {
            $key = ($parentId ?? 0).'|'.mb_strtolower($name);
            if (isset($this->pending[$key])) {
                return $this->pending[$key];
            }
        }
        if ($parentId !== null && $parentId < 0) {
            return null;   // the parent is only planned (a dry run), so nothing can be under it yet
        }
        $wanted = array_map('mb_strtolower', $names);
        $found = Category::query()->where('parent_id', $parentId)->get()->first(fn ($c) => in_array(mb_strtolower($c->mainName()), $wanted, true));

        return $found?->id;
    }

    private function article(string $collection, int $categoryId, object $topic): void
    {
        $exists = Article::withTrashed()->where('working_name', $topic->public_id)->exists();
        if ($exists) {
            $this->report[$collection]['articles_existing']++;

            return;
        }
        $this->report[$collection]['articles_new']++;

        // A written-up entry (sections with verses) keeps its text. An entry with only a summary and no sections is not carried.
        $sections = $this->old->table('index_sections')->where('index_entry_id', $topic->id)->orderBy('sort_order')->orderBy('id')->get();
        $carry = $sections->isNotEmpty() && $this->old->table('index_verses')->whereIn('index_section_id', $sections->pluck('id')->all())->exists();
        if ($carry) {
            $this->report[$collection]['with_text']++;
        } elseif (filled($topic->summary) || filled($topic->context)) {
            $this->report[$collection]['text_skipped']++;
        }
        if ($this->dry) {
            return;
        }

        $locale = config('infofluxes.default_locale');
        $data = [
            'title' => [$locale => trim($topic->title)],
            'slug' => Article::uniqueSlug($topic->title),
            'category_id' => $categoryId,
            'working_name' => $topic->public_id,
            'status' => 'draft',
            'is_index_line' => true,
            'author_id' => $this->authorId,
            'sort_order' => max(0, (int) $topic->sort_order) * 10,
        ];
        if ($carry) {
            $data['short_detail'] = [$locale => mb_substr(trim((string) $topic->summary), 0, 600)];
            $data['long_detail'] = [$locale => $this->text($topic, $sections)];
            $data['ai_assisted'] = (bool) $topic->ai_assisted;
            $data['reviewer_name'] = $topic->ai_reviewed_by ?: null;
        }
        Article::create($data);
    }

    /** The written-up text of an old entry as safe HTML: the context, then each section with its verses word for word. */
    private function text(object $topic, $sections): string
    {
        $html = '';
        if (filled($topic->context)) {
            $html .= Html::fromPlain($topic->context);
        }
        foreach ($sections as $section) {
            $html .= '<h2>'.e($section->heading).'</h2>';
            $html .= Html::clean((string) $section->explanation);
            $verses = $this->old->table('index_verses')->where('index_section_id', $section->id)->orderBy('sort_order')->orderBy('id')->get();
            foreach ($verses as $verse) {
                $reference = trim(($verse->book ? $verse->book.' ' : '').$verse->chapter.':'.$verse->verse_from.($verse->verse_to ? '-'.$verse->verse_to : ''));
                $html .= '<blockquote>';
                if (filled($verse->original)) {
                    $html .= '<p>'.nl2br(e($verse->original)).'</p>';
                }
                if (filled($verse->translation)) {
                    $html .= '<p>'.nl2br(e($verse->translation)).'</p>';
                }
                $html .= '<p><em>'.e($reference).(filled($verse->translation_name) ? ' · '.e($verse->translation_name) : '').'</em></p></blockquote>';
            }
            if (filled($section->sources_note)) {
                $html .= '<p>'.e('Sources checked: '.$section->sources_note).'</p>';
            }
        }

        return $html;
    }

    private function emptyRow(): array
    {
        return ['categories_new' => 0, 'categories_existing' => 0, 'articles_new' => 0, 'articles_existing' => 0, 'with_text' => 0, 'text_skipped' => 0];
    }

    private function summary(): array
    {
        $total = $this->emptyRow();
        foreach ($this->report as $row) {
            foreach ($total as $k => $_) {
                $total[$k] += $row[$k];
            }
        }

        return ['total' => $total, 'groups' => $this->report, 'dry' => $this->dry];
    }
}
