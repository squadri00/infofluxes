<?php

namespace Tests\Feature;

use App\Filament\Resources\DidYouKnow\DidYouKnowResource;
use App\Filament\Resources\DidYouKnow\Pages\ManageDidYouKnow;
use App\Models\Article;
use App\Models\DidYouKnow;
use App\Models\Setting;
use App\Models\User;
use App\Support\Html;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Step 27: tables in written text, the wide card for a long fact, the article link, a real source, and a tidy paste. */
class Step27Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::forget();
        Filament::setCurrentPanel('admin');
    }

    private const TABLE = '<table><thead><tr><th>No.</th><th>Woman</th><th>Reference</th></tr></thead><tbody><tr><td>1</td><td>Maryam (Mary)</td><td>3:42-47</td></tr><tr><td>2</td><td>Wife of Noah</td><td>66:10</td></tr></tbody></table>';

    private function fact(string $title, array $extra = []): DidYouKnow
    {
        return DidYouKnow::create(array_merge([
            'title' => ['en' => $title], 'body' => ['en' => '<p>Short answer.</p>'], 'source' => 'https://example.org/'.str($title)->slug(),
            'status' => 'published', 'sort_order' => 1,
        ], $extra));
    }

    // ---------------- tables survive, nothing dangerous does ----------------

    public function test_a_table_keeps_its_rows_and_columns(): void
    {
        $this->assertSame(self::TABLE, Html::clean(self::TABLE));
    }

    public function test_a_table_loses_its_styles_scripts_and_wrong_spans(): void
    {
        $dirty = '<table style="x" onclick="bad()" class="a"><colgroup><col style="min-width:25px"></colgroup><tr><th colspan="2" onmouseover="y">A</th><td rowspan="x">B</td></tr></table><script>alert(1)</script>';

        $clean = Html::clean($dirty);

        $this->assertSame('<table><tr><th colspan="2">A</th><td>B</td></tr></table>', $clean);
    }

    public function test_a_fact_with_a_table_shows_a_real_table(): void
    {
        $this->fact('The women', ['body' => ['en' => '<p>Intro.</p>'.self::TABLE]]);

        $html = $this->get(route('did-you-know'))->assertOk()->getContent();

        $this->assertStringContainsString('<table><thead><tr><th>No.</th>', $html);
        $this->assertStringContainsString('<td>Maryam (Mary)</td>', $html);
    }

    // ---------------- the wide card ----------------

    public function test_a_fact_with_a_table_or_a_long_answer_opens_across_the_whole_width(): void
    {
        $this->fact('Table fact', ['body' => ['en' => self::TABLE]]);
        $this->fact('Long fact', ['body' => ['en' => '<p>'.str_repeat('Many words here. ', 60).'</p>'], 'sort_order' => 2]);
        $this->fact('Short fact', ['sort_order' => 3]);

        $html = $this->get(route('did-you-know'))->assertOk()->getContent();

        $this->assertSame(2, substr_count($html, 'class="dyk__item dyk__item--wide"'), 'the table fact and the long fact, not the short one');
        $this->assertSame(2, substr_count($html, ' data-wide>'));
        $this->assertStringContainsString('rowEnd * 2 + 1', $html, 'the script puts the open wide card on its own line under its row');

        $css = (string) file_get_contents(public_path('css/site.css'));
        $this->assertStringContainsString('.dyk__item--wide[open] { grid-column: 1 / -1; }', $css);
        $this->assertStringContainsString('.article__body table { display: block; max-width: 100%; overflow-x: auto;', $css, 'a table scrolls sideways on a small screen');
    }

    // ---------------- the link to a full article ----------------

    public function test_a_fact_can_point_to_a_published_article_with_a_read_more_button(): void
    {
        $article = Article::create(['title' => ['en' => 'The whole story'], 'slug' => 'the-whole-story', 'status' => 'published', 'publish_date' => now()->subDay()]);
        $draft = Article::create(['title' => ['en' => 'Not ready'], 'slug' => 'not-ready', 'status' => 'draft']);
        $this->fact('With a story', ['article_id' => $article->id]);
        $this->fact('With a draft', ['article_id' => $draft->id, 'sort_order' => 2]);
        $this->fact('Alone', ['sort_order' => 3]);

        $html = $this->get(route('did-you-know'))->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'Read the full article'), 'only the published article is offered');
        $this->assertStringContainsString('href="'.route('article.show', 'the-whole-story').'"', $html);
        $this->assertStringNotContainsString('not-ready', $html);
    }

    public function test_deleting_the_article_keeps_the_fact(): void
    {
        $article = Article::create(['title' => ['en' => 'Gone soon'], 'slug' => 'gone-soon', 'status' => 'published', 'publish_date' => now()->subDay()]);
        $fact = $this->fact('Keeps going', ['article_id' => $article->id]);

        $article->forceDelete();

        $this->assertNull($fact->fresh()->article_id);
        $this->assertNotNull($fact->fresh());
    }

    // ---------------- a real source ----------------

    public function test_what_counts_as_a_source(): void
    {
        foreach (['https://example.org/page', 'Sahih al-Bukhari, Book of Faith, hadith 8', 'For further research: Amina Wadud, Quran and Woman'] as $good) {
            $this->assertTrue(DidYouKnowResource::goodSource($good), $good);
        }
        foreach (['AI', 'ChatGPT', 'chat gpt', 'Gemini', 'Claude', 'internet', 'Google', 'Quran', 'n/a', '   '] as $bad) {
            $this->assertFalse(DidYouKnowResource::goodSource($bad), $bad);
        }
    }

    public function test_the_admin_refuses_a_tool_name_as_the_source(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'superuser', 'status' => 'active']));

        Livewire::test(ManageDidYouKnow::class)
            ->callAction('create', ['title' => ['en' => 'Fact'], 'body' => ['en' => '<p>Text</p>'], 'status' => 'published', 'ai_assisted' => false, 'source' => 'ChatGPT'])
            ->assertHasActionErrors(['source']);

        Livewire::test(ManageDidYouKnow::class)
            ->callAction('create', ['title' => ['en' => 'Fact'], 'body' => ['en' => '<p>Text</p>'], 'status' => 'published', 'ai_assisted' => false, 'source' => 'Sahih al-Bukhari, Book of Faith, hadith 8'])
            ->assertHasNoActionErrors();
    }

    // ---------------- a tidy paste ----------------

    public function test_the_source_chip_of_a_chat_tool_and_empty_paragraphs_are_removed_but_the_words_stay(): void
    {
        $pasted = '<p>Only one woman is named.</p><p>Province of Manitoba+1</p><p></p><p>&nbsp;</p><p>It was counted 24+1 times by one count.</p>';

        $tidy = DidYouKnowResource::tidy($pasted);

        $this->assertSame('<p>Only one woman is named.</p><p>It was counted 24+1 times by one count.</p>', $tidy);
    }

    public function test_a_saved_answer_keeps_its_table_and_loses_the_chip(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'superuser', 'status' => 'active']));

        Livewire::test(ManageDidYouKnow::class)
            ->callAction('create', [
                'title' => ['en' => 'The women'], 'body' => ['en' => '<p>Intro.</p><p>Province of Manitoba+1</p>'.self::TABLE],
                'status' => 'draft', 'ai_assisted' => false,
            ])->assertHasNoActionErrors();

        $body = DidYouKnow::first()->getTranslation('body', 'en');
        $this->assertStringContainsString('<table>', $body);
        $this->assertStringNotContainsString('Manitoba', $body);
        $this->assertStringContainsString('Intro.', $body);
    }

    public function test_tables_also_work_in_an_article_text(): void
    {
        Article::create(['title' => ['en' => 'With a table'], 'slug' => 'with-a-table', 'status' => 'published', 'publish_date' => now()->subDay(), 'long_detail' => ['en' => self::TABLE]]);

        $this->get('/with-a-table')->assertOk()->assertSee('<td>Maryam (Mary)</td>', false);
    }
}
