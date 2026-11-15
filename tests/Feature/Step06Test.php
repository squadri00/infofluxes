<?php

namespace Tests\Feature;

use App\Filament\Pages\AiImport as AiImportPage;
use App\Filament\Resources\Articles\Pages\EditArticle;
use App\Models\Article;
use App\Models\Category;
use App\Models\ImportLog;
use App\Models\User;
use App\Support\AiImport;
use App\Support\Prompts;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Step 6: the AI import. A pasted answer is checked first, saved only as drafts, and never published. */
class Step06Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('admin');
    }

    private function person(string $role, array $extra = []): User
    {
        return User::factory()->create(array_merge(['role' => $role, 'status' => 'active'], $extra));
    }

    private function cat(string $name, ?Category $parent = null): Category
    {
        return Category::create(['name' => ['en' => $name], 'slug' => Category::uniqueSlug($name), 'parent_id' => $parent?->id]);
    }

    private function articleText(array $over = []): string
    {
        $f = array_merge([
            'TYPE' => 'article', 'CATEGORY' => 'Shows > Popular Shows', 'ALSO IN' => 'Blogs', 'TITLE' => 'A Fine Title',
            'SHORT' => 'A short line.', 'LONG' => "First paragraph.\n\nSecond paragraph.", 'KEYWORDS' => 'one, two words, one',
            'VIDEOS' => 'https://youtu.be/dQw4w9WgXcQ, https://example.com/no', 'SOURCE' => 'Book One, page 5',
        ], $over);
        $out = '';
        foreach ($f as $key => $value) {
            $out .= $key.': '.($key === 'LONG' ? "\n" : '').$value."\n";
        }

        return $out."END\n";
    }

    private function tree(): array
    {
        $shows = $this->cat('Shows');
        $pop = $this->cat('Popular Shows', $shows);
        $blogs = $this->cat('Blogs');

        return [$shows, $pop, $blogs];
    }

    // ---------- reading an article ----------

    public function test_a_good_article_is_read_with_nothing_missing(): void
    {
        [, $pop, $blogs] = $this->tree();

        $items = AiImport::parse($this->articleText());

        $this->assertCount(1, $items);
        $item = $items[0];
        $this->assertSame([], $item['errors']);
        $this->assertSame('article', $item['kind']);
        $this->assertSame($pop->id, $item['data']['category_id']);
        $this->assertSame([$blogs->id], $item['data']['extra_ids']);
        $this->assertSame(['one', 'two words'], $item['data']['words']);
        $this->assertSame(['https://youtu.be/dQw4w9WgXcQ'], $item['data']['videos']);
        $this->assertStringContainsString('not a YouTube video', implode(' ', $item['warnings']));
        $this->assertSame("First paragraph.\n\nSecond paragraph.", $item['data']['long']);
    }

    public function test_checking_saves_nothing(): void
    {
        $this->tree();
        AiImport::parse($this->articleText());

        $this->assertSame(0, Article::count());
        $this->assertSame(0, ImportLog::count());
    }

    public function test_a_category_can_be_written_as_a_code_a_path_with_a_code_or_a_unique_name(): void
    {
        [$shows, $pop] = $this->tree();

        foreach ([$pop->code, 'Popular Shows', 'popular shows', 'Shows > Popular Shows', 'Shows > Popular Shows ('.$pop->code.')'] as $text) {
            $this->assertSame([], AiImport::parse($this->articleText(['CATEGORY' => $text, 'ALSO IN' => '']))[0]['errors'], $text);
        }
    }

    public function test_a_missing_or_unknown_or_unclear_category_is_a_problem(): void
    {
        $shows = $this->cat('Shows');
        $blogs = $this->cat('Blogs');
        $this->cat('Notes', $shows);
        $this->cat('Notes', $blogs);

        $this->assertStringContainsString('CATEGORY is missing', implode(' ', AiImport::parse($this->articleText(['CATEGORY' => '']))[0]['errors']));
        $this->assertStringContainsString('no category called', implode(' ', AiImport::parse($this->articleText(['CATEGORY' => 'Nowhere']))[0]['errors']));
        $this->assertStringContainsString('no category has the code C-999', implode(' ', AiImport::parse($this->articleText(['CATEGORY' => 'C-999']))[0]['errors']));
        $this->assertStringContainsString('more than one category', implode(' ', AiImport::parse($this->articleText(['CATEGORY' => 'Notes']))[0]['errors']));
        $this->assertSame([], AiImport::parse($this->articleText(['CATEGORY' => 'Shows > Notes', 'ALSO IN' => '']))[0]['errors'], 'a path tells them apart');
    }

    public function test_the_title_the_text_and_the_lengths_are_checked(): void
    {
        $this->tree();

        $errors = fn (array $o) => implode(' | ', AiImport::parse($this->articleText($o))[0]['errors']);
        $this->assertStringContainsString('TITLE is missing', $errors(['TITLE' => '']));
        $this->assertStringContainsString('LONG (the text) is missing', $errors(['LONG' => '']));
        $this->assertStringContainsString('SHORT is longer than 600', $errors(['SHORT' => str_repeat('x', 601)]));
        $this->assertStringContainsString('TITLE is longer than 200', $errors(['TITLE' => str_repeat('x', 201)]));
    }

    public function test_a_fact_needs_a_source_and_an_article_only_gets_a_note(): void
    {
        $this->tree();

        $fact = AiImport::parse($this->articleText(['TYPE' => 'fact', 'SOURCE' => 'NONE']))[0];
        $this->assertStringContainsString('A fact needs a SOURCE', implode(' ', $fact['errors']));

        $article = AiImport::parse($this->articleText(['SOURCE' => 'NONE']))[0];
        $this->assertSame([], $article['errors']);
        $this->assertStringContainsString('no SOURCE line', implode(' ', $article['warnings']));
        $this->assertNull($article['data']['source']);

        $this->assertSame([], AiImport::parse($this->articleText(['TYPE' => 'fact']))[0]['errors']);
    }

    public function test_the_reviewer_is_ignored_because_a_person_writes_it(): void
    {
        $this->tree();
        $item = AiImport::parse($this->articleText(['REVIEWER' => 'Some AI']))[0];

        $this->assertStringContainsString('REVIEWER was ignored', implode(' ', $item['warnings']));
        $this->assertArrayNotHasKey('reviewer', $item['data']);
    }

    public function test_the_same_title_in_the_same_category_is_not_made_twice(): void
    {
        [, $pop] = $this->tree();
        Article::create(['title' => ['en' => 'A fine title'], 'slug' => 'a-fine-title', 'category_id' => $pop->id]);

        $this->assertStringContainsString('already in that category', implode(' ', AiImport::parse($this->articleText())[0]['errors']));
    }

    public function test_many_articles_in_one_paste_are_each_checked_and_a_chat_code_box_is_ignored(): void
    {
        $this->tree();
        $text = "```text\n".$this->articleText(['TITLE' => 'First'])."\n====\n".$this->articleText(['TITLE' => 'Second', 'CATEGORY' => 'Nowhere'])."\n```";

        $items = AiImport::parse($text);

        $this->assertCount(2, $items);
        $this->assertSame('First', $items[0]['title']);
        $this->assertSame([], $items[0]['errors']);
        $this->assertNotSame([], $items[1]['errors']);
    }

    public function test_an_answer_without_a_type_or_with_a_wrong_type_is_a_problem(): void
    {
        $this->assertStringContainsString('must say "TYPE', implode(' ', AiImport::parse("TITLE: No type here\nLONG: x")[0]['errors']));
        $this->assertStringContainsString('must say "TYPE', implode(' ', AiImport::parse("TYPE: poem\nTITLE: x")[0]['errors']));
        $this->assertSame([], AiImport::parse(''));
    }

    public function test_html_and_scripts_are_never_taken_as_html(): void
    {
        $this->tree();
        $made = AiImport::commit(AiImport::parse($this->articleText(['LONG' => "Hello <script>alert(1)</script> <b>bold</b>"])), 'raw', $this->person('superuser'));

        $article = Article::find($made['articles'][0]);
        $this->assertStringNotContainsString('<script>', $article->long_detail);
        $this->assertStringContainsString('&lt;script&gt;', $article->long_detail);
        $this->assertStringContainsString('&lt;b&gt;bold&lt;/b&gt;', $article->long_detail);
    }

    // ---------- saving ----------

    public function test_saving_makes_drafts_marked_as_written_with_ai_help(): void
    {
        [, $pop, $blogs] = $this->tree();
        $user = $this->person('superuser');

        $made = AiImport::commit(AiImport::parse($this->articleText()), 'the raw paste', $user);

        $this->assertCount(1, $made['articles']);
        $a = Article::find($made['articles'][0]);
        $this->assertSame('written', $a->status);
        $this->assertTrue($a->ai_assisted);
        $this->assertNull($a->reviewer_name);
        $this->assertSame($user->id, $a->author_id);
        $this->assertSame($pop->id, $a->category_id);
        $this->assertSame([$blogs->id], $a->extraCategories->pluck('id')->all());
        $this->assertSame('A Fine Title', $a->mainTitle());
        $this->assertSame('Book One, page 5', $a->source_line);
        $this->assertSame(['one', 'two words'], $a->search_words);
        $this->assertSame('dQw4w9WgXcQ', $a->videos->first()->youtube_id);
        $this->assertSame('a-fine-title', $a->slug);
        $this->assertFalse(Article::published()->whereKey($a->id)->exists(), 'never published by the import');

        $log = ImportLog::first();
        $this->assertSame('the raw paste', $log->raw_text);
        $this->assertSame([$a->id], $log->result['articles']);
    }

    public function test_only_the_items_without_problems_are_saved(): void
    {
        $this->tree();
        $text = $this->articleText(['TITLE' => 'Good one'])."====\n".$this->articleText(['TITLE' => 'Bad one', 'CATEGORY' => 'Nowhere']);

        $made = AiImport::commit(AiImport::parse($text), $text, $this->person('superuser'));

        $this->assertCount(1, $made['articles']);
        $this->assertSame(1, $made['skipped']);
        $this->assertSame(['Good one'], Article::all()->map->mainTitle()->all());
    }

    // ---------- categories ----------

    public function test_a_category_list_is_read_with_what_is_new_and_what_exists(): void
    {
        $this->tree();
        $item = AiImport::parse("TYPE: categories\nShows > Popular Shows\nShows > Crime | Crime and mystery\nHistory > Early > Very early\nEND")[0];

        $this->assertSame('categories', $item['kind']);
        $this->assertSame([], $item['errors']);
        $this->assertSame(2, $item['data']['new']);
        $this->assertSame(1, $item['data']['existing']);
    }

    public function test_saving_categories_makes_hidden_ones_under_the_right_parents_and_leaves_existing_alone(): void
    {
        [$shows] = $this->tree();
        $text = "TYPE: categories\nShows > Crime | Crime and mystery\nShows > Popular Shows\nHistory > Early > Very early\nEND";

        $made = AiImport::commit(AiImport::parse($text), $text, $this->person('superuser'));

        $this->assertCount(4, $made['categories'], 'Crime, History, Early, Very early');
        $crime = Category::where('parent_id', $shows->id)->get()->first(fn ($c) => $c->mainName() === 'Crime');
        $this->assertNotNull($crime);
        $this->assertFalse($crime->is_visible, 'made hidden: the owner shows it after looking');
        $this->assertSame('Crime and mystery', $crime->getTranslation('description', 'en'));
        $history = Category::whereNull('parent_id')->get()->first(fn ($c) => $c->mainName() === 'History');
        $early = Category::where('parent_id', $history->id)->first();
        $this->assertSame('Very early', Category::where('parent_id', $early->id)->first()->mainName());
        $this->assertSame(1, Category::get()->filter(fn ($c) => $c->mainName() === 'Popular Shows')->count(), 'no copy of one that exists');
        $this->assertSame([0, 1, 2], [$history->depth, $early->depth, Category::where('parent_id', $early->id)->first()->depth]);
    }

    public function test_a_category_list_has_limits(): void
    {
        $deep = AiImport::parse("TYPE: categories\nA > B > C > D > E > F > G\nEND")[0];
        $this->assertStringContainsString('Too deep', implode(' ', $deep['errors']));

        $empty = AiImport::parse("TYPE: categories\nEND")[0];
        $this->assertStringContainsString('no category lines', implode(' ', $empty['errors']));
    }

    // ---------- the prompts ----------

    public function test_each_prompt_has_the_subject_the_present_categories_and_the_format(): void
    {
        [, $pop] = $this->tree();

        foreach (['article', 'fact'] as $kind) {
            $p = Prompts::make($kind, 'The Night Journey');
            $this->assertStringContainsString('The Night Journey', $p);
            $this->assertStringContainsString('Shows > Popular Shows ('.$pop->code.')', $p);
            $this->assertStringContainsString('TYPE: '.$kind, $p);
            $this->assertStringContainsString('Never invent a source', $p);
            $this->assertStringContainsString('Prophet Muhammad ﷺ', $p);
            $this->assertStringNotContainsString('{{', $p);
        }
        $c = Prompts::make('categories', 'Hinduism');
        $this->assertStringContainsString('TYPE: categories', $c);
        $this->assertStringContainsString('Hinduism', $c);
        $this->assertStringNotContainsString('{{', $c);
    }

    public function test_a_prompt_with_no_categories_says_so_and_an_unknown_kind_is_refused(): void
    {
        $this->assertStringContainsString('there are no categories yet', Prompts::make('article', 'x'));
        $this->expectException(\Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);
        Prompts::make('poem', 'x');
    }

    public function test_what_the_prompt_asks_for_is_what_the_reader_accepts(): void
    {
        // The shape shown in the article prompt, filled in, must be read without problems.
        $this->tree();
        $answer = "TYPE: article\nCATEGORY: Shows > Popular Shows\nALSO IN:\nTITLE: Round trip\nSHORT: Short.\nLONG:\nOne.\n\nTwo.\nKEYWORDS: a, b\nVIDEOS:\nSOURCE: A book\nEND";

        $this->assertSame([], AiImport::parse($answer)[0]['errors']);
        $this->assertStringContainsString("TITLE: (the title)\nSHORT:", Prompts::make('article', 'x'));
    }

    // ---------- the screen ----------

    public function test_the_import_screen_is_for_people_with_the_screen_only(): void
    {
        $this->actingAs($this->person('author'));
        $this->get('/admin/ai-import')->assertForbidden();

        $this->actingAs($this->person('moderator'));
        $this->get('/admin/ai-import')->assertForbidden();

        $this->actingAs($this->person('author', ['admin_screens' => ['articles', 'import']]));
        $this->get('/admin/ai-import')->assertOk();

        $this->actingAs($this->person('superuser'));
        $this->get('/admin/ai-import')->assertOk()->assertSee('Step 1')->assertSee('Copy the prompt');
    }

    public function test_the_screen_makes_the_prompt_checks_and_saves_as_drafts(): void
    {
        [, $pop] = $this->tree();
        $this->actingAs($this->person('superuser'));

        Livewire::test(AiImportPage::class)
            ->set('topic', 'The Hijra')
            ->assertSee('The Hijra')
            ->set('kind', 'fact')
            ->assertSee('TYPE: fact')
            ->set('paste', $this->articleText())
            ->call('check')
            ->assertSee('A Fine Title')
            ->assertSee('Save as drafts')
            ->call('save');

        $this->assertSame(1, Article::count());
        $this->assertSame('written', Article::first()->status);
        $this->assertSame(1, ImportLog::count());
    }

    public function test_the_screen_refuses_to_save_when_everything_has_a_problem_and_when_checked_empty(): void
    {
        $this->tree();
        $this->actingAs($this->person('superuser'));

        Livewire::test(AiImportPage::class)->call('check')->assertSet('checked', false);

        Livewire::test(AiImportPage::class)
            ->set('paste', $this->articleText(['CATEGORY' => 'Nowhere']))
            ->call('check')->assertSee('Problem:')->assertSee('Nothing can be saved yet')
            ->call('save');
        $this->assertSame(0, Article::count());
    }

    public function test_the_screen_saves_what_the_pasted_text_says_not_what_the_browser_claims(): void
    {
        $this->tree();
        $this->actingAs($this->person('superuser'));

        Livewire::test(AiImportPage::class)
            ->set('paste', $this->articleText(['CATEGORY' => 'Nowhere']))
            ->set('items', [['kind' => 'article', 'title' => 'Forged', 'errors' => [], 'warnings' => [], 'data' => ['title' => 'Forged', 'category_id' => 1]]])
            ->call('save');

        $this->assertSame(0, Article::count());
    }

    public function test_categories_need_the_categories_screen_too(): void
    {
        $this->actingAs($this->person('author', ['admin_screens' => ['articles', 'import']]));

        Livewire::test(AiImportPage::class)->set('paste', "TYPE: categories\nNew Thing\nEND")->call('check')->call('save');

        $this->assertSame(0, Category::count());
    }

    // ---------- the reviewer rule ----------

    public function test_an_article_written_with_ai_help_needs_a_reviewer_before_it_is_published(): void
    {
        $this->tree();
        $mod = $this->person('moderator');
        $made = AiImport::commit(AiImport::parse($this->articleText()), 'raw', $mod);
        $a = Article::find($made['articles'][0]);
        $this->actingAs($mod);

        Livewire::test(EditArticle::class, ['record' => $a->getRouteKey()])->fillForm(['status' => 'published', 'reviewer_name' => ''])->call('save');
        $this->assertSame('written', $a->fresh()->status, 'not published without a reviewer');

        Livewire::test(EditArticle::class, ['record' => $a->getRouteKey()])->fillForm(['status' => 'published', 'reviewer_name' => 'Dr Check', 'custom_data' => ['review' => ['done' => array_keys(\App\Support\ReviewChecklist::standard())]]])->call('save')->assertHasNoFormErrors();
        $this->assertSame('published', $a->fresh()->status);
        $this->assertSame('Dr Check', $a->fresh()->reviewer_name);
    }

    public function test_an_ordinary_article_needs_no_reviewer_to_be_published(): void
    {
        [, $pop] = $this->tree();
        $a = Article::create(['title' => ['en' => 'Plain'], 'slug' => 'plain', 'category_id' => $pop->id, 'status' => 'draft']);
        $this->actingAs($this->person('moderator'));

        Livewire::test(EditArticle::class, ['record' => $a->getRouteKey()])->fillForm(['status' => 'published'])->call('save');
        $this->assertSame('published', $a->fresh()->status);
    }
}
