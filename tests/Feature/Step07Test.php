<?php

namespace Tests\Feature;

use App\Filament\Pages\MoveFromOldSite;
use App\Models\Article;
use App\Models\Category;
use App\Models\User;
use App\Support\OldSiteImport;
use Filament\Facades\Filament;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

/** Step 7: moving the categories and the subject indexes of the old site across. The old database is a small fake one here. */
class Step07Test extends TestCase
{
    use RefreshDatabase;

    private int $oldId = 0;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('admin');

        config(['database.connections.old_site' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']]);
        DB::purge('old_site');
        $old = Schema::connection('old_site');
        $old->create('categories', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->text('description')->nullable(); $t->unsignedBigInteger('parent_id')->nullable(); $t->softDeletes();
        });
        $old->create('index_entries', function (Blueprint $t) {
            $t->id(); $t->string('public_id'); $t->string('collection'); $t->unsignedTinyInteger('level'); $t->string('code'); $t->string('title');
            $t->text('note')->nullable(); $t->text('summary')->nullable(); $t->text('context')->nullable(); $t->boolean('ai_assisted')->default(false);
            $t->string('ai_reviewed_by')->nullable(); $t->unsignedBigInteger('parent_id')->nullable(); $t->unsignedInteger('sort_order')->default(0); $t->softDeletes();
        });
        $old->create('index_sections', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('index_entry_id'); $t->unsignedInteger('sort_order')->default(0); $t->string('heading'); $t->text('explanation')->nullable(); $t->text('sources_note')->nullable();
        });
        $old->create('index_verses', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('index_section_id'); $t->string('book')->nullable(); $t->unsignedInteger('sort_order')->default(0); $t->unsignedInteger('chapter');
            $t->unsignedInteger('verse_from'); $t->unsignedInteger('verse_to')->nullable(); $t->text('original')->nullable(); $t->text('translation')->nullable(); $t->string('translation_name')->nullable();
        });
    }

    private function entry(string $collection, int $level, string $code, string $title, ?int $parent = null, int $order = 1, array $extra = []): int
    {
        return DB::connection('old_site')->table('index_entries')->insertGetId(array_merge([
            'public_id' => strtoupper(substr($collection, 0, 2)).'-'.$code, 'collection' => $collection, 'level' => $level, 'code' => $code, 'title' => $title,
            'parent_id' => $parent, 'sort_order' => $order,
        ], $extra));
    }

    /** A small old site: a Quran index with Parts and an index without Parts, like History. */
    private function oldSite(): void
    {
        DB::connection('old_site')->table('categories')->insert([
            ['name' => 'About Islam', 'description' => 'What Islam teaches.'],
            ['name' => 'History and Civilizations', 'description' => 'History words.'],
        ]);
        $p1 = $this->entry('quran', 0, 'I', 'God and Creation');
        $m1 = $this->entry('quran', 1, '1', 'Allah (SWT) and attributes', $p1);
        $s1 = $this->entry('quran', 2, '1.1', 'Oneness', $m1, 1, ['note' => 'A note.']);
        $this->entry('quran', 3, '1.1.001', 'Allah is One', $s1, 1);
        $this->entry('quran', 3, '1.1.002', 'Allah is unique', $s1, 2);

        $h1 = $this->entry('history', 1, '3', 'History and Civilizations');
        $h2 = $this->entry('history', 2, '3.1', 'Early history', $h1, 1);
        $this->entry('history', 3, '3.1.001', 'The first state', $h2, 1);
    }

    private function superuser(): User
    {
        return User::factory()->create(['role' => 'superuser', 'status' => 'active']);
    }

    public function test_checking_shows_the_counts_and_saves_nothing(): void
    {
        $this->oldSite();

        $r = (new OldSiteImport)->check();

        $this->assertTrue($r['dry']);
        $this->assertSame(0, Category::count());
        $this->assertSame(0, Article::count());
        // 6 headings + Quran Index + Part + Main + Sub + Early history = 11 categories; 3 articles
        $this->assertSame(11, $r['total']['categories_new']);
        $this->assertSame(3, $r['total']['articles_new']);
        $this->assertSame(2, $r['groups']['quran']['articles_new']);
    }

    public function test_moving_makes_the_headings_the_index_tree_and_empty_draft_articles(): void
    {
        $this->oldSite();
        $author = $this->superuser();

        (new OldSiteImport)->move($author);

        $names = Category::orderBy('tree_path')->get()->map(fn ($c) => str_repeat('-', $c->depth).$c->mainName())->all();
        $this->assertSame([
            'About Islam', '-Quran Index', '--God and Creation', '---Allah (SWT) and attributes', '----Oneness',
            'History and Civilizations', '-Early history',
            'Science and Knowledge', 'Comparative Religions', 'Society and World Affairs', 'Did You Know?',
        ], $names);

        $oneness = Category::get()->first(fn ($c) => $c->mainName() === 'Oneness');
        $this->assertSame('A note.', $oneness->getTranslation('description', 'en'));
        $this->assertSame('QU-1.1', $oneness->working_name);
        $this->assertTrue($oneness->is_visible);

        $a = Article::where('working_name', 'QU-1.1.001')->first();
        $this->assertSame('Allah is One', $a->mainTitle());
        $this->assertSame('draft', $a->status);
        $this->assertSame($oneness->id, $a->category_id);
        $this->assertSame($author->id, $a->author_id);
        $this->assertNull($a->short_detail);
        $this->assertSame(3, Article::count());
        $this->assertFalse(Article::published()->exists(), 'nothing is published');
    }

    public function test_an_index_without_parts_merges_into_its_heading(): void
    {
        $this->oldSite();
        (new OldSiteImport)->move($this->superuser());

        $history = Category::whereNull('parent_id')->get()->first(fn ($c) => $c->mainName() === 'History and Civilizations');
        $this->assertSame('History words.', $history->getTranslation('description', 'en'), 'the heading takes the old description');
        $early = Category::get()->first(fn ($c) => $c->mainName() === 'Early history');
        $this->assertSame($history->id, $early->parent_id, 'no extra "History and Civilizations" level');
        $this->assertSame($early->id, Article::where('working_name', 'HI-3.1.001')->first()->category_id);
    }

    public function test_categories_you_made_by_hand_are_used_not_copied_or_changed(): void
    {
        $this->oldSite();
        $about = Category::create(['name' => ['en' => 'About Islam'], 'slug' => 'about-islam', 'description' => ['en' => 'My own words.']]);
        $mine = Category::create(['name' => ['en' => 'The Quran'], 'slug' => 'the-quran', 'parent_id' => $about->id]);
        $part = Category::create(['name' => ['en' => 'God and Creation'], 'slug' => 'god-and-creation', 'parent_id' => $mine->id, 'working_name' => 'my own']);

        $r = (new OldSiteImport)->move($this->superuser());

        $this->assertSame(1, Category::get()->filter(fn ($c) => $c->mainName() === 'About Islam')->count());
        $this->assertSame('My own words.', $about->fresh()->getTranslation('description', 'en'), 'an existing heading keeps its words');
        $this->assertSame(0, Category::get()->filter(fn ($c) => $c->mainName() === 'Quran Index')->count(), 'your "The Quran" is the index category');
        $this->assertSame('my own', $part->fresh()->working_name);
        $main = Category::get()->first(fn ($c) => $c->mainName() === 'Allah (SWT) and attributes');
        $this->assertSame($part->id, $main->parent_id, 'the old Part found your Part and went under it');
        $this->assertSame(3, $r['total']['categories_existing'], 'About Islam, The Quran, God and Creation');
    }

    public function test_moving_again_adds_only_what_is_missing(): void
    {
        $this->oldSite();
        (new OldSiteImport)->move($this->superuser());
        $categories = Category::count();
        $articles = Article::count();

        $again = (new OldSiteImport)->check();
        $this->assertSame(0, $again['total']['categories_new']);
        $this->assertSame(0, $again['total']['articles_new']);

        (new OldSiteImport)->move($this->superuser());
        $this->assertSame($categories, Category::count());
        $this->assertSame($articles, Article::count());

        $this->entry('quran', 3, '1.1.003', 'A new line later', DB::connection('old_site')->table('index_entries')->where('code', '1.1')->value('id'), 3);
        (new OldSiteImport)->move($this->superuser());
        $this->assertSame($articles + 1, Article::count());
    }

    public function test_a_written_up_entry_keeps_its_text_and_its_verses_word_for_word(): void
    {
        $this->oldSite();
        $id = DB::connection('old_site')->table('index_entries')->where('code', '1.1.001')->value('id');
        DB::connection('old_site')->table('index_entries')->where('id', $id)->update(['summary' => 'Allah is One, says the Quran.', 'context' => 'Some background.', 'ai_assisted' => 1, 'ai_reviewed_by' => 'Shamim Saifullah']);
        $sec = DB::connection('old_site')->table('index_sections')->insertGetId(['index_entry_id' => $id, 'heading' => 'The verse', 'explanation' => '<p>It <strong>says</strong> so.</p><script>x()</script>', 'sources_note' => 'Quran.com']);
        DB::connection('old_site')->table('index_verses')->insert(['index_section_id' => $sec, 'chapter' => 112, 'verse_from' => 1, 'original' => 'قُلْ هُوَ اللَّهُ أَحَدٌ', 'translation' => 'Say, "He is Allah, [who is] One,"', 'translation_name' => 'Saheeh International']);

        $r = (new OldSiteImport)->move($this->superuser());

        $a = Article::where('working_name', 'QU-1.1.001')->first();
        $this->assertSame('Allah is One, says the Quran.', $a->getTranslation('short_detail', 'en'));
        $this->assertTrue($a->ai_assisted);
        $this->assertSame('Shamim Saifullah', $a->reviewer_name);
        $this->assertSame('draft', $a->status);
        $text = $a->getTranslation('long_detail', 'en');
        $this->assertStringContainsString('Some background.', $text);
        $this->assertStringContainsString('<h2>The verse</h2>', $text);
        $this->assertStringContainsString('<strong>says</strong>', $text);
        $this->assertStringNotContainsString('script', $text);
        $this->assertStringContainsString('قُلْ هُوَ اللَّهُ أَحَدٌ', $text);
        $this->assertStringContainsString('Say, &quot;He is Allah, [who is] One,&quot;', $text, 'the translation is kept exactly (only made safe for display)');
        $this->assertStringContainsString('112:1', $text);
        $this->assertStringContainsString('Saheeh International', $text);
        $this->assertStringContainsString('Sources checked: Quran.com', $text);
        $this->assertSame(1, $r['total']['with_text']);
    }

    public function test_an_entry_with_only_a_summary_or_an_empty_section_keeps_only_its_title(): void
    {
        $this->oldSite();
        $id = DB::connection('old_site')->table('index_entries')->where('code', '1.1.002')->value('id');
        DB::connection('old_site')->table('index_entries')->where('id', $id)->update(['summary' => 'Some test words']);
        DB::connection('old_site')->table('index_sections')->insert(['index_entry_id' => $id, 'heading' => 'tes']);

        $r = (new OldSiteImport)->move($this->superuser());

        $a = Article::where('working_name', 'QU-1.1.002')->first();
        $this->assertSame('Allah is unique', $a->mainTitle());
        $this->assertNull($a->short_detail);
        $this->assertSame(1, $r['total']['text_skipped']);
        $this->assertSame(0, $r['total']['with_text']);
    }

    public function test_if_anything_fails_nothing_is_saved_and_the_tree_order_is_back(): void
    {
        $this->oldSite();
        // The second article cannot be saved: that stops the whole move.
        $stop = true;
        Article::creating(function (Article $a) use (&$stop) {
            if ($stop && $a->mainTitle() === 'Allah is unique') {
                throw new \RuntimeException('stop');
            }
        });

        try {
            (new OldSiteImport)->move($this->superuser());
            $this->fail('the move should have stopped');
        } catch (\RuntimeException $e) {
            $this->assertSame('stop', $e->getMessage());
        } finally {
            $stop = false;
        }

        $this->assertSame(0, Category::count());
        $this->assertSame(0, Article::count());
        $this->assertFalse(Category::$pauseRebuild, 'the tree order is switched on again');
    }

    public function test_an_old_entry_that_was_deleted_is_not_moved(): void
    {
        $this->oldSite();
        DB::connection('old_site')->table('index_entries')->where('code', '1.1.002')->update(['deleted_at' => now()]);

        (new OldSiteImport)->move($this->superuser());

        $this->assertNull(Article::where('working_name', 'QU-1.1.002')->first());
        $this->assertNotNull(Article::where('working_name', 'QU-1.1.001')->first());
    }

    public function test_the_tree_order_and_codes_are_right_after_a_big_move(): void
    {
        $this->oldSite();
        (new OldSiteImport)->move($this->superuser());

        $this->assertSame([0, 1, 2, 3, 4], Category::orderBy('tree_path')->limit(5)->pluck('depth')->all());
        $this->assertNotEmpty(Category::first()->tree_path);
        $this->assertSame(0, Category::where('tree_path', '')->count());
        $this->assertSame(Category::count(), Category::pluck('slug')->unique()->count(), 'every web address is its own');
    }

    public function test_the_page_is_for_the_super_user_and_only_where_the_old_database_exists(): void
    {
        $this->actingAs($this->superuser());
        $this->assertTrue(MoveFromOldSite::canAccess());

        $this->actingAs(User::factory()->create(['role' => 'moderator', 'status' => 'active']));
        $this->assertFalse(MoveFromOldSite::canAccess());
        $this->get('/admin/move-from-old-site')->assertForbidden();

        config(['database.connections.old_site.database' => null]);
        $this->actingAs($this->superuser());
        $this->assertFalse(MoveFromOldSite::canAccess(), 'on the live site there is no old database');
        $this->assertFalse(MoveFromOldSite::shouldRegisterNavigation());
    }

    public function test_the_page_checks_and_then_moves(): void
    {
        $this->oldSite();
        $this->actingAs($this->superuser());

        Livewire::test(MoveFromOldSite::class)
            ->call('check')
            ->assertSee('What would be made')->assertSee('Quran Index')->assertSee('Move now')
            ->call('move')
            ->assertSee('Done');

        $this->assertSame(3, Article::count());
        $this->assertSame(11, Category::count());
    }

    public function test_the_command_works_from_the_command_line(): void
    {
        $this->oldSite();
        $this->superuser();

        $this->artisan('site:move-from-old', ['--check' => true])->expectsOutputToContain('Would make: 11 categories and 3 draft articles')->assertSuccessful();
        $this->assertSame(0, Category::count());
        $this->artisan('site:move-from-old')->expectsOutputToContain('Made: 11 categories and 3 draft articles')->assertSuccessful();
        $this->assertSame(3, Article::count());
    }
}
