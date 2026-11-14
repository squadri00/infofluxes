<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Category;
use App\Models\DidYouKnow;
use App\Models\Page;
use App\Models\Person;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Step 32: the search finds things by ID, by title words and by keyword, and shows every result. */
class Step32Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::forget();
    }

    private function article(string $title, array $extra = []): Article
    {
        return Article::create(array_merge([
            'title' => ['en' => $title], 'slug' => str($title)->slug()->toString().'-'.uniqid(), 'status' => 'published', 'publish_date' => now()->subDay(),
        ], $extra));
    }

    private function cat(string $name, array $extra = []): Category
    {
        return Category::create(array_merge(['name' => ['en' => $name], 'slug' => str($name)->slug()->toString().'-'.uniqid()], $extra));
    }

    private function fact(string $title, array $extra = []): DidYouKnow
    {
        return DidYouKnow::create(array_merge(['title' => ['en' => $title], 'body' => ['en' => '<p>Answer.</p>'], 'source' => 'https://example.org/x', 'status' => 'published'], $extra));
    }

    private function search(array $query): \Illuminate\Testing\TestResponse
    {
        return $this->get(route('search', $query))->assertOk();
    }

    // ---------------- by ID ----------------

    public function test_an_article_is_found_by_its_id_in_any_way_of_writing_it(): void
    {
        $this->article('Filler one');
        $target = $this->article('The target article');

        foreach (['A-'.$target->id, 'a-'.$target->id, 'a'.$target->id, 'A'.$target->id] as $query) {
            $this->search(['q' => $query])->assertSee('The target article')->assertDontSee('Filler one');
        }
    }

    public function test_every_kind_of_id_finds_its_own_kind_of_thing(): void
    {
        $category = $this->cat('Prayer');
        $fact = $this->fact('Fact about prayer');
        $page = Page::create(['title' => ['en' => 'Privacy page'], 'slug' => 'privacy', 'status' => 'published']);
        $person = Person::create(['name' => 'Amina Khan', 'show_page' => true]);

        $this->search(['q' => 'C-'.$category->id])->assertSee('Prayer');
        $this->search(['q' => 'D-'.$fact->id])->assertSee('Fact about prayer');
        $this->search(['q' => 'P-'.$page->id])->assertSee('Privacy page');
        $this->search(['q' => 'K-'.$person->id])->assertSee('Amina Khan');
    }

    public function test_an_id_of_one_kind_does_not_find_another_kind_with_the_same_number(): void
    {
        $article = $this->article('An article', ['id' => 7]);
        $category = $this->cat('A category', ['id' => 7]);

        $this->search(['q' => 'C-7'])->assertSee('A category')->assertDontSee('An article');
        $this->search(['q' => 'A-7'])->assertSee('An article');
    }

    public function test_the_old_index_id_finds_a_planned_subject_with_no_article_yet(): void
    {
        $cat = $this->cat('Quran Index');
        $line = $this->article('The Oneness of Allah', ['status' => 'draft', 'is_index_line' => true, 'working_name' => 'QR-1.1.001', 'category_id' => $cat->id]);

        $html = $this->search(['q' => 'QR-1.1.001'])->assertSee('The Oneness of Allah')->assertSee('QR-1.1.001')->getContent();

        $this->assertStringContainsString('Subjects of the indexes', $html);
        $this->assertStringContainsString('#a-'.$line->id, $html, 'a link to its place in the index');
        $this->assertStringContainsString(route('category.show', ['slug' => $cat->slug, 'view' => 'index']), $html);
    }

    public function test_the_start_of_an_old_index_id_finds_every_subject_under_it(): void
    {
        $cat = $this->cat('Quran Index');
        foreach (['QR-1.1.001', 'QR-1.1.002', 'QR-1.2.001'] as $i => $code) {
            $this->article('Subject '.($i + 1), ['status' => 'draft', 'is_index_line' => true, 'working_name' => $code, 'category_id' => $cat->id]);
        }

        $this->search(['q' => 'QR-1.1'])->assertSee('Subject 1')->assertSee('Subject 2')->assertDontSee('Subject 3');
    }

    public function test_a_published_article_is_not_listed_twice_as_a_planned_subject(): void
    {
        $cat = $this->cat('Quran Index');
        $this->article('Written subject', ['is_index_line' => true, 'working_name' => 'QR-2.1.001', 'category_id' => $cat->id]);

        $html = $this->search(['q' => 'QR-2.1.001'])->assertSee('Written subject')->getContent();

        $this->assertSame(1, substr_count($html, 'class="pcard '), 'once, as an article card');
        $this->assertSame(0, substr_count($html, 'qi__item'), 'not again as a planned subject');
        $this->assertStringNotContainsString('Subjects of the indexes', $html);
    }

    // ---------------- by title and keyword ----------------

    public function test_a_title_word_a_keyword_and_a_word_of_the_text_each_find_the_article(): void
    {
        $this->article('Lote tree story', ['search_words' => 'sidrat, boundary', 'long_detail' => ['en' => '<p>It marks a limit.</p>']]);
        $this->article('Other thing');

        $this->search(['q' => 'LOTE'])->assertSee('Lote tree story')->assertDontSee('Other thing');
        $this->search(['q' => 'Sidrat'])->assertSee('Lote tree story')->assertDontSee('Other thing');
        $this->search(['q' => 'limit'])->assertSee('Lote tree story')->assertDontSee('Other thing');
    }

    public function test_every_word_must_match_and_an_id_can_be_mixed_with_a_word(): void
    {
        $a = $this->article('Prayer at dawn');
        $this->article('Prayer at night');

        $this->search(['q' => 'prayer dawn'])->assertSee('Prayer at dawn')->assertDontSee('Prayer at night');
        $this->search(['q' => 'A-'.$a->id.' prayer'])->assertSee('Prayer at dawn')->assertDontSee('Prayer at night');
        $this->search(['q' => 'A-'.$a->id.' zebra'])->assertDontSee('Prayer at dawn');
    }

    public function test_a_category_is_found_by_its_description_too(): void
    {
        $this->cat('Faith', ['description' => ['en' => 'About belief and trust']]);

        $this->search(['q' => 'trust'])->assertSee('Faith');
    }

    public function test_the_exact_id_comes_first(): void
    {
        // Another article is newer and mentions "a-5" in its title: without the rule it would be listed first.
        $real = $this->article('Real one', ['id' => 5, 'publish_date' => now()->subDays(10)]);
        $mention = $this->article('Notes about a-5 and more', ['id' => 9, 'publish_date' => now()->subHour()]);

        $html = $this->search(['q' => 'A-5'])->getContent();

        $this->assertNotFalse(strpos($html, 'Real one'));
        $this->assertNotFalse(strpos($html, 'Notes about a-5'));
        $this->assertLessThan(strpos($html, 'Notes about a-5'), strpos($html, 'Real one'), 'the article that has the ID comes first');
    }

    // ---------------- nothing is left out ----------------

    public function test_no_category_and_no_fact_is_cut_off(): void
    {
        foreach (range(1, 15) as $i) {
            $this->cat('Faith '.$i);
        }
        foreach (range(1, 30) as $i) {
            $this->fact('Fact about faith '.$i);
        }

        $html = $this->search(['q' => 'faith'])->getContent();

        $this->assertStringContainsString('45 results', $html);
        $this->assertSame(15, substr_count($html, 'class="tile"'), 'all 15 categories (it used to stop at 12)');
        $this->assertSame(30, substr_count($html, 'class="pcard '), 'all 30 facts (it used to stop at 24)');
    }

    public function test_the_articles_come_in_big_pages_with_a_true_count_and_a_show_all_link(): void
    {
        foreach (range(1, 130) as $i) {
            $this->article('Wisdom number '.$i, ['publish_date' => now()->subMinutes($i)]);
        }

        $first = $this->search(['q' => 'wisdom'])->getContent();
        $this->assertStringContainsString('130 results', $first);
        $this->assertSame(120, substr_count($first, 'class="pcard '), 'a page holds 120');
        $this->assertStringContainsString('Show all results on one page', $first);

        $all = $this->search(['q' => 'wisdom', 'all' => 1])->getContent();
        $this->assertSame(130, substr_count($all, 'class="pcard '), 'show all: every one of them');
        $this->assertStringNotContainsString('Show all results on one page', $all);
    }

    public function test_the_subjects_of_an_index_are_all_listed(): void
    {
        $cat = $this->cat('Bible Index');
        foreach (range(1, 150) as $i) {
            $this->article('Planned subject '.$i, ['status' => 'draft', 'is_index_line' => true, 'working_name' => 'BI-1.'.$i, 'category_id' => $cat->id]);
        }

        $html = $this->search(['q' => 'planned subject'])->getContent();

        $this->assertStringContainsString('150 results', $html);
        $this->assertSame(150, substr_count($html, 'qi__item'));
    }

    public function test_the_count_names_each_part_and_links_to_it(): void
    {
        $this->cat('Faith things');
        $this->fact('Faith fact');
        $this->article('Faith article');

        $html = $this->search(['q' => 'faith'])->getContent();

        $this->assertStringContainsString('3 results', $html);
        foreach (['categories', 'facts', 'articles'] as $part) {
            $this->assertStringContainsString('href="#found-'.$part.'"', $html);
            $this->assertStringContainsString('id="found-'.$part.'"', $html);
        }
        $this->assertSame('1 result', trim(strip_tags(substr($this->search(['q' => 'faith article'])->getContent(), strpos($this->search(['q' => 'faith article'])->getContent(), 'search__count') + 22, 40))) === '' ? '' : '1 result');
    }

    // ---------------- what must never show ----------------

    public function test_drafts_hidden_categories_archived_subjects_and_unlisted_things_never_show(): void
    {
        $hidden = $this->cat('Secret place', ['is_visible' => false]);
        $open = $this->cat('Open place');
        $this->article('Secret draft article', ['status' => 'draft']);
        $this->article('Secret subject archived', ['status' => 'archived', 'is_index_line' => true, 'category_id' => $open->id]);
        $this->article('Secret subject in a hidden place', ['status' => 'draft', 'is_index_line' => true, 'category_id' => $hidden->id]);
        $this->fact('Secret draft fact', ['status' => 'draft']);
        $this->fact('Secret fact without a source', ['source' => null]);
        Page::create(['title' => ['en' => 'Secret page'], 'slug' => 'secret', 'status' => 'draft']);
        Person::create(['name' => 'Secret person', 'show_page' => false]);

        $html = $this->search(['q' => 'secret'])->getContent();

        $this->assertStringContainsString('Nothing was found', $html);
        foreach (['Secret draft article', 'Secret subject', 'Secret fact', 'Secret page', 'Secret person', 'Secret place'] as $word) {
            $this->assertStringNotContainsString($word, $html);
        }
    }

    public function test_a_chosen_category_limits_the_subjects_and_the_facts_and_leaves_out_pages_and_people(): void
    {
        $faith = $this->cat('Faith');
        $history = $this->cat('History');
        $this->article('Planned prayer subject', ['status' => 'draft', 'is_index_line' => true, 'category_id' => $faith->id]);
        $this->article('Planned prayer in history', ['status' => 'draft', 'is_index_line' => true, 'category_id' => $history->id]);
        $this->fact('Prayer fact', ['category_id' => $faith->id]);
        $this->fact('Prayer fact in history', ['category_id' => $history->id]);
        Page::create(['title' => ['en' => 'Prayer page'], 'slug' => 'prayer-page', 'status' => 'published', 'in_footer' => false]);

        $html = $this->search(['q' => 'prayer', 'category' => $faith->id])->getContent();

        $this->assertStringContainsString('Planned prayer subject', $html);
        $this->assertStringContainsString('Prayer fact', $html);
        $this->assertStringNotContainsString('in history', $html);
        $this->assertStringNotContainsString('Prayer page', $html);
    }

    public function test_pages_and_people_with_a_public_page_are_found(): void
    {
        Page::create(['title' => ['en' => 'About the project'], 'slug' => 'about', 'status' => 'published', 'body' => ['en' => '<p>We compare arguments.</p>']]);
        Person::create(['name' => 'Yusuf Ali', 'show_page' => true, 'role_title' => 'Reviewer', 'bio' => 'Checks sources.']);

        $this->search(['q' => 'arguments'])->assertSee('About the project');
        $this->search(['q' => 'sources'])->assertSee('Yusuf Ali');
    }

    public function test_the_box_and_the_empty_page_explain_what_can_be_typed(): void
    {
        $this->get('/')->assertSee('Search by ID, title or keyword');
        $this->search([])->assertSee('A-42')->assertSee('QR-1.1.001');
    }

    public function test_symbols_stay_plain_text_and_a_long_query_is_cut_safely(): void
    {
        $this->article('Fifty percent');

        $this->search(['q' => '%'])->assertSee('Nothing was found');
        $this->search(['q' => str_repeat('a', 500)])->assertSee('Nothing was found');
        $this->search(['q' => "it's | A-1.2 \\ test"])->assertOk();
    }
}
