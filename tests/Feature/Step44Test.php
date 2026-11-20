<?php

namespace Tests\Feature;

use App\Filament\Pages\AiImport;
use App\Filament\Resources\Articles\Pages\ListArticles;
use App\Models\Article;
use App\Models\Category;
use App\Models\Comment;
use App\Models\User;
use App\Support\AiImport as Importer;
use App\Support\IndexEntries;
use App\Support\IndexMerger;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/** Step 44: an article written with AI help fills the blank index entry instead of making a second article. */
class Step44Test extends TestCase
{
    use RefreshDatabase;

    private Category $cat;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('admin');
        Http::fake(['*' => Http::response('ok', 200)]);
        $this->cat = Category::create(['name' => ['en' => 'Tawhid al-Rububiyyah'], 'slug' => 'rububiyyah']);
    }

    private function entry(string $id, string $title, array $extra = []): Article
    {
        return Article::create(array_merge([
            'title' => ['en' => $title], 'slug' => \Illuminate\Support\Str::slug($title), 'category_id' => $this->cat->id, 'working_name' => $id, 'is_index_line' => true, 'status' => 'draft',
        ], $extra));
    }

    private function answer(string $title = 'Allah (SWT) Alone Is the Creator of All Things'): string
    {
        return "TYPE: article\nCATEGORY: {$this->cat->code}\nTITLE: {$title}\nSHORT: Allah (SWT) alone created everything.\nLONG:\n## What it means\nMuslims believe that Allah (SWT) alone created everything.\n\n## The evidence\nThe Quran says so in many places.\nKEYWORDS: creator, tawhid\nSOURCE: The Quran\nSOURCES:\n- PRIMARY | The Quran, Surah 112:1 | https://quran.example.org/112 | opened: yes\nEND";
    }

    private function admin(): User
    {
        $u = User::factory()->create(['role' => 'superuser', 'status' => 'active']);
        $this->actingAs($u);

        return $u;
    }

    public function test_blank_means_an_index_line_with_no_text(): void
    {
        $blank = $this->entry('AL-2.1.001', 'Allah (SWT) alone creates');
        $written = $this->entry('AL-2.1.002', 'Allah (SWT) alone sustains', ['long_detail' => ['en' => '<p>Words.</p>']]);
        $plain = Article::create(['title' => ['en' => 'Plain'], 'slug' => 'plain', 'category_id' => $this->cat->id]);

        $this->assertTrue(IndexEntries::isBlank($blank));
        $this->assertFalse(IndexEntries::isBlank($written));
        $this->assertFalse(IndexEntries::isBlank($plain));
        $this->assertSame($blank->code, IndexEntries::label($blank));
    }

    public function test_search_finds_blank_entries_by_index_id_code_or_title_and_skips_written_ones(): void
    {
        $a = $this->entry('AL-2.1.001', 'Allah (SWT) alone creates');
        $this->entry('AL-2.1.002', 'Allah (SWT) alone sustains');
        $this->entry('AL-2.1.003', 'Allah (SWT) alone controls', ['long_detail' => ['en' => '<p>Written.</p>']]);

        $this->assertSame([$a->id], IndexEntries::search('2.1.001')->pluck('id')->all());
        $this->assertSame([$a->id], IndexEntries::search('A-'.$a->id)->pluck('id')->all());
        $this->assertSame([$a->id], IndexEntries::search((string) $a->id)->pluck('id')->all());
        $this->assertCount(2, IndexEntries::search('alone'), 'the written one is not offered');
        $this->assertSame([], IndexEntries::search('controls')->all());
    }

    public function test_the_suggestion_finds_the_entry_with_the_same_subject_and_only_that_one(): void
    {
        $creates = $this->entry('AL-2.1.001', 'Allah (SWT) alone creates');
        $this->entry('AL-2.1.002', 'Allah (SWT) alone sustains');
        $this->entry('AL-2.1.003', 'Allah (SWT) alone controls the universe');

        $found = IndexEntries::suggest('Allah (SWT) Alone Is the Creator of All Things', $this->cat->id);

        $this->assertSame([$creates->id], array_column($found, 'id'));
        $this->assertSame($creates->code, $found[0]['code']);
        $this->assertSame([], IndexEntries::suggest('Something quite different', $this->cat->id));
    }

    public function test_check_warns_about_a_blank_entry_when_none_was_chosen(): void
    {
        $blank = $this->entry('AL-2.1.001', 'Allah (SWT) alone creates');

        $item = Importer::parse($this->answer())[0];

        $this->assertSame([], $item['errors'], 'it is a warning, not a stop');
        $this->assertSame($blank->code, $item['data']['index_suggestions'][0]['code']);
        $this->assertStringContainsString('blank index entry '.$blank->code, implode(' ', $item['warnings']));
    }

    public function test_with_an_entry_chosen_the_category_comes_from_the_entry_and_the_title_check_skips_it(): void
    {
        $other = Category::create(['name' => ['en' => 'Elsewhere'], 'slug' => 'elsewhere']);
        $entry = $this->entry('AL-2.1.001', 'Allah (SWT) alone creates');
        $text = str_replace($this->cat->code, $other->code, $this->answer('Allah (SWT) alone creates'));

        $item = Importer::parse($text, false, $entry->id)[0];

        $this->assertSame([], $item['errors'], implode(' | ', $item['errors']));
        $this->assertSame($this->cat->id, $item['data']['category_id']);
        $this->assertStringContainsString('category of the index entry', implode(' ', $item['warnings']));
        $this->assertStringContainsString($entry->code, $item['data']['entry_label']);
        $this->assertSame([], $item['data']['index_suggestions']);
    }

    public function test_an_entry_that_has_text_now_is_refused_and_only_one_article_can_fill_one_entry(): void
    {
        $written = $this->entry('AL-2.1.002', 'Written', ['long_detail' => ['en' => '<p>x</p>']]);
        $item = Importer::parse($this->answer(), false, $written->id)[0];
        $this->assertNotEmpty(array_filter($item['errors'], fn ($e) => str_contains($e, 'not blank any more')));

        $blank = $this->entry('AL-2.1.001', 'Blank one');
        $two = $this->answer('First one')."\n====\n".$this->answer('Second one');
        $items = Importer::parse($two, false, $blank->id);
        $this->assertSame([], $items[0]['errors']);
        $this->assertNotEmpty(array_filter($items[1]['errors'], fn ($e) => str_contains($e, 'only one article')));
    }

    public function test_saving_fills_the_entry_and_makes_no_new_article(): void
    {
        $entry = $this->entry('AL-2.1.001', 'Allah (SWT) alone creates');
        $before = Article::count();
        $admin = $this->admin();

        Livewire::test(AiImport::class)
            ->call('selectEntry', $entry->id)
            ->assertSet('entryId', $entry->id)
            ->assertSet('categoryId', $this->cat->id)
            ->assertSet('topic', 'Allah (SWT) alone creates')
            ->set('paste', $this->answer())->call('check')->call('save')
            ->assertSet('entryId', null);

        $this->assertSame($before, Article::count(), 'no second article');
        $e = $entry->fresh();
        $this->assertSame('AL-2.1.001', $e->working_name);
        $this->assertTrue($e->is_index_line);
        $this->assertSame('Allah (SWT) Alone Is the Creator of All Things', $e->mainTitle());
        $this->assertSame('allah-swt-alone-is-the-creator-of-all-things', $e->slug);
        $this->assertSame('written', $e->status);
        $this->assertTrue($e->ai_assisted);
        $this->assertSame($admin->id, $e->author_id);
        $this->assertSame('Allah (SWT) alone creates', $e->custom_data['index_title']);
        $this->assertCount(1, $e->custom_data['sources']);
        $this->assertTrue($e->custom_data['accordion']);
        $this->assertStringContainsString('<h2>What it means</h2>', $e->getTranslation('long_detail', 'en'));
        $this->assertSame(['creator', 'tawhid'], $e->search_words);
        $this->assertFalse(IndexEntries::isBlank($e->fresh()), 'it has text now, so it is not offered again');
    }

    public function test_the_prompt_names_the_planned_subject(): void
    {
        $entry = $this->entry('AL-2.1.001', 'Allah (SWT) alone creates');
        $this->admin();

        $p = Livewire::test(AiImport::class)->call('selectEntry', $entry->id)->instance()->prompt();

        $this->assertStringContainsString('planned index subject "'.$entry->code.' Allah (SWT) alone creates"', $p);
        $this->assertStringContainsString($this->cat->code, $p);
    }

    public function test_the_page_opens_with_an_entry_from_the_articles_list_link(): void
    {
        $entry = $this->entry('AL-2.1.001', 'Allah (SWT) alone creates');
        $this->admin();

        Livewire::withQueryParams(['entry' => $entry->id])->test(AiImport::class)->assertSet('entryId', $entry->id)->assertSee($entry->code);
    }

    public function test_a_suggestion_can_be_used_with_one_click(): void
    {
        $entry = $this->entry('AL-2.1.001', 'Allah (SWT) alone creates');
        $this->admin();

        Livewire::test(AiImport::class)->set('paste', $this->answer())->call('check')
            ->assertSee('Fill this entry instead')
            ->call('useEntry', $entry->id)
            ->assertSet('entryId', $entry->id)->assertSee('Will fill the index entry');
    }

    public function test_the_articles_list_offers_write_with_ai_only_on_blank_entries(): void
    {
        $blank = $this->entry('AL-2.1.001', 'Allah (SWT) alone creates');
        $written = $this->entry('AL-2.1.002', 'Written one', ['long_detail' => ['en' => '<p>x</p>']]);
        $this->admin();

        Livewire::test(ListArticles::class)
            ->assertActionVisible(TestAction::make('write_with_ai')->table($blank))
            ->assertActionHidden(TestAction::make('write_with_ai')->table($written));
    }

    // ---------- the merge of an article that was made on its own ----------

    private function standalone(): Article
    {
        return Article::create([
            'title' => ['en' => 'Allah (SWT) Alone Is the Creator of All Things'], 'slug' => 'allah-swt-alone-is-the-creator-of-all-things', 'category_id' => $this->cat->id, 'status' => 'published',
            'publish_date' => now()->subDay(), 'short_detail' => ['en' => 'Short.'], 'long_detail' => ['en' => '<h2>One</h2><p>Words.</p>'], 'ai_assisted' => true, 'reviewer_name' => 'Dr Check',
            'custom_data' => ['sources' => [['type' => 'primary', 'ref' => 'A book']], 'accordion' => true], 'source_line' => 'A book',
        ]);
    }

    public function test_the_merge_does_nothing_without_apply(): void
    {
        $from = $this->standalone();
        $entry = $this->entry('AL-2.1.001', 'Allah (SWT) alone creates');

        $r = IndexMerger::run($from, $entry, false);

        $this->assertTrue($r['ok']);
        $this->assertFalse($r['applied']);
        $this->assertTrue(IndexEntries::isBlank($entry->fresh()));
        $this->assertFalse($from->fresh()->trashed());
    }

    public function test_the_merge_moves_everything_and_the_old_code_and_address_still_work(): void
    {
        $from = $this->standalone();
        $entry = $this->entry('AL-2.1.001', 'Allah (SWT) alone creates');
        $from->images()->create(['image' => 'a.webp', 'sort_order' => 1]);
        $from->videos()->create(['url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'sort_order' => 1]);
        $reader = User::factory()->create();
        $both = User::factory()->create();
        Comment::create(['article_id' => $from->id, 'user_id' => $reader->id, 'body' => 'Nice.', 'status' => 'approved']);
        $from->reactions()->create(['user_id' => $reader->id, 'value' => 1]);
        $from->reactions()->create(['user_id' => $both->id, 'value' => 1]);
        $entry->reactions()->create(['user_id' => $both->id, 'value' => -1]);
        $from->saves()->create(['user_id' => $reader->id]);
        $extra = Category::create(['name' => ['en' => 'Also here'], 'slug' => 'also-here']);
        $from->extraCategories()->attach($extra->id);

        $r = IndexMerger::run($from, $entry, true);

        $this->assertTrue($r['applied']);
        $e = $entry->fresh();
        $this->assertSame('AL-2.1.001', $e->working_name);
        $this->assertTrue($e->is_index_line);
        $this->assertSame($this->cat->id, $e->category_id);
        $this->assertSame('Allah (SWT) Alone Is the Creator of All Things', $e->mainTitle());
        $this->assertSame('allah-swt-alone-is-the-creator-of-all-things', $e->slug);
        $this->assertSame('published', $e->status);
        $this->assertSame('Dr Check', $e->reviewer_name);
        $this->assertSame('Allah (SWT) alone creates', $e->custom_data['index_title']);
        $this->assertSame('A book', $e->custom_data['sources'][0]['ref']);
        $this->assertSame(1, $e->images()->count());
        $this->assertSame(1, $e->videos()->count());
        $this->assertSame(1, Comment::where('article_id', $e->id)->count());
        $this->assertSame([$extra->id], $e->extraCategories()->pluck('categories.id')->all());
        $this->assertSame(2, $e->reactions()->count(), 'the reader moved, the person who reacted to both keeps the one on the entry');
        $this->assertSame(-1, $e->reactions()->where('user_id', $both->id)->value('value'));
        $this->assertSame(1, $e->saves()->count());

        $gone = Article::withTrashed()->find($from->id);
        $this->assertTrue($gone->trashed());
        $this->assertSame($e->id, $gone->custom_data['moved_to_article']);

        $this->get('/allah-swt-alone-is-the-creator-of-all-things')->assertOk()->assertSee('Allah (SWT) Alone Is the Creator of All Things');
        $this->get('/id/'.$from->code)->assertRedirect(route('article.show', $e->slug));
    }

    public function test_the_merge_refuses_a_written_entry_an_index_entry_as_source_and_the_same_article(): void
    {
        $from = $this->standalone();
        $written = $this->entry('AL-2.1.002', 'Written', ['long_detail' => ['en' => '<p>x</p>']]);
        $blank = $this->entry('AL-2.1.001', 'Blank');

        $this->assertFalse(IndexMerger::run($from, $written, true)['ok']);
        $this->assertFalse(IndexMerger::run($blank, $blank, true)['ok']);
        $this->assertFalse(IndexMerger::run($blank, $this->entry('AL-2.1.003', 'Another blank'), true)['ok']);
        $this->assertFalse($from->fresh()->trashed());
    }

    public function test_the_command_works_with_numbers_codes_and_index_ids(): void
    {
        $from = $this->standalone();
        $entry = $this->entry('AL-2.1.001', 'Allah (SWT) alone creates');

        $this->artisan('index:merge', ['article' => $from->code, 'entry' => 'AL-2.1.001'])->expectsOutputToContain('Nothing was changed')->assertSuccessful();
        $this->assertTrue(IndexEntries::isBlank($entry->fresh()));

        $this->artisan('index:merge', ['article' => (string) $from->id, 'entry' => 'AL-2.1.001', '--apply' => true])->expectsOutputToContain('Done')->assertSuccessful();
        $this->assertSame('published', $entry->fresh()->status);

        $this->artisan('index:merge', ['article' => '99999', 'entry' => 'AL-2.1.001'])->assertFailed();
    }
}
