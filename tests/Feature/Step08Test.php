<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Category;
use App\Models\User;
use App\Support\CategoryIndex;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Step 8: the Index view of a category page, a quick list of the whole branch like the old index pages. */
class Step08Test extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CategoryIndex::$budget = 1500;
        parent::tearDown();
    }

    private function cat(string $name, ?Category $parent = null, array $extra = []): Category
    {
        return Category::create(array_merge(['name' => ['en' => $name], 'slug' => Category::uniqueSlug($name), 'parent_id' => $parent?->id, 'sort_order' => 1], $extra));
    }

    private function line(string $title, Category $cat, array $extra = []): Article
    {
        return Article::create(array_merge(['title' => ['en' => $title], 'slug' => Article::uniqueSlug($title), 'category_id' => $cat->id, 'status' => 'draft', 'is_index_line' => true], $extra));
    }

    private function published(string $title, Category $cat, array $extra = []): Article
    {
        return $this->line($title, $cat, array_merge(['status' => 'published', 'publish_date' => now()->subDay(), 'is_index_line' => false], $extra));
    }

    /** An index like the old Allah index: index > Part > main category > sub-category > subjects. */
    private function tree(): array
    {
        $index = $this->cat('Allah Index');
        $part = $this->cat('Existence and Oneness', $index, ['working_name' => 'AL-I']);
        $main = $this->cat('Existence and Identity', $part, ['working_name' => 'AL-1']);
        $sub = $this->cat('Existence of Allah (SWT)', $main, ['working_name' => 'AL-1.1']);

        return [$index, $part, $main, $sub];
    }

    public function test_the_category_page_has_an_index_button_and_the_normal_page_is_unchanged(): void
    {
        [$index, , , $sub] = $this->tree();
        $this->published('A real article', $sub);

        $html = $this->get('/category/'.$index->slug)->assertOk()->getContent();
        $this->assertStringContainsString('data-view-link="index"', $html);
        $this->assertStringContainsString('?view=index', $html);
        $this->assertStringContainsString('data-view-btn="grid"', $html);
        $this->assertStringNotContainsString('data-qi', $html, 'the normal page has no index block');
        $this->assertStringNotContainsString('data-active', $html);
    }

    public function test_the_index_view_shows_the_whole_branch_as_nested_headings_with_their_subjects(): void
    {
        [$index, $part, $main, $sub] = $this->tree();
        $this->line('Allah (SWT) existence', $sub, ['working_name' => 'AL-1.1.001']);
        $this->published('Written about Allah (SWT)', $sub, ['working_name' => 'AL-1.1.002']);

        $html = $this->get('/category/'.$index->slug.'?view=index')->assertOk()->getContent();

        $this->assertStringContainsString('data-qi', $html);
        $this->assertStringContainsString('<h2 class="qi__part-title"><span class="qi__code">AL-I</span> Existence and Oneness</h2>', $html);
        $this->assertStringContainsString('<span class="qi__code">AL-1</span> Existence and Identity</summary>', $html);
        $this->assertStringContainsString('<h3 class="qi__sub-title"><span class="qi__code">AL-1.1</span> Existence of Allah (SWT)</h3>', $html);
        $this->assertStringContainsString('<span class="qi__code">AL-1.1.001</span>', $html, 'the old index ID is shown');
        // The order: Part, then the main row, then the sub-title, then the lines.
        $this->assertLessThan(strpos($html, 'AL-1</span>'), strpos($html, 'AL-I</span>'));
        $this->assertLessThan(strpos($html, 'AL-1.1</span>'), strpos($html, 'AL-1</span>'));
        $this->assertLessThan(strpos($html, 'AL-1.1.001'), strpos($html, 'AL-1.1</span>'));
        $this->assertStringContainsString('2 subjects', $html);
    }

    public function test_every_subject_counted_is_also_drawn_even_when_it_sits_directly_in_a_part_or_a_row(): void
    {
        [$index, $part, $main, $sub] = $this->tree();
        $this->published('Directly in the part', $part);
        $this->published('Directly in the row', $main);
        $this->published('In the sub-category', $sub);
        $this->published('Directly in the index', $index);

        $html = $this->get('/category/'.$index->slug.'?view=index')->getContent();

        $this->assertStringContainsString('4 subjects', $html);
        foreach (['Directly in the part', 'Directly in the row', 'In the sub-category', 'Directly in the index'] as $title) {
            $this->assertStringContainsString($title, $html, $title);
        }
        $this->assertSame(4, substr_count($html, 'data-qi-item'));
    }

    public function test_an_article_in_two_categories_is_listed_under_both_and_counted_once(): void
    {
        [$allah, , , $sub] = $this->tree();
        $quran = $this->cat('Quran Index');
        $heavens = $this->cat('Creation of the Heavens', $quran, ['working_name' => 'QR-1.2']);
        $article = $this->published('Allah (SWT) as the Creator', $sub, ['working_name' => 'AL-1.1.003']);
        $article->extraCategories()->attach($heavens->id);

        // Under its main category: no note.
        $a = $this->get('/category/'.$allah->slug.'?view=index')->getContent();
        $this->assertStringContainsString('Allah (SWT) as the Creator', $a);
        $this->assertStringNotContainsString('qi__also', $a);
        $this->assertStringContainsString('1 subjects', $a);

        // Under the other category: listed, with a small note that names the main category, and it opens the same article.
        $q = $this->get('/category/'.$quran->slug.'?view=index')->getContent();
        $this->assertStringContainsString('<a href="'.route('article.show', $article->slug).'">Allah (SWT) as the Creator</a>', $q);
        $this->assertStringContainsString('<span class="qi__also">(main place: Existence of Allah (SWT))</span>', $q);
        $this->assertStringContainsString('1 subjects', $q);
        $this->assertStringContainsString('AL-1.1.003', $q, 'the same code in both places');
    }

    public function test_an_article_in_two_places_of_one_branch_is_shown_twice_but_counted_once(): void
    {
        [$index, $part, $main, $sub] = $this->tree();
        $article = $this->published('In two rows', $sub);
        $article->extraCategories()->attach($main->id);

        $html = $this->get('/category/'.$index->slug.'?view=index')->getContent();

        $this->assertSame(2, substr_count($html, 'In two rows</a>'), 'shown under both');
        $this->assertStringContainsString('1 subjects', $html, 'counted once');
        $ids = [];
        preg_match_all('/data-qi-item id="([^"]+)"/', $html, $m);
        $this->assertSame(count($m[1]), count(array_unique($m[1])), 'every id on the page is its own');
    }

    public function test_the_note_never_names_a_hidden_category_and_private_articles_are_not_copied(): void
    {
        [$index] = $this->tree();
        $secret = $this->cat('Secret home', null, ['is_visible' => false]);
        $shown = $this->cat('Public place');
        $ok = $this->published('Published with a hidden main place', $secret);
        $ok->extraCategories()->attach($shown->id);
        $draft = $this->line('A private draft', $secret, ['is_index_line' => false]);
        $draft->extraCategories()->attach($shown->id);

        $html = $this->get('/category/'.$shown->slug.'?view=index')->getContent();

        $this->assertStringContainsString('Published with a hidden main place', $html);
        $this->assertStringNotContainsString('Secret home', $html);
        $this->assertStringNotContainsString('qi__also', $html);
        $this->assertStringNotContainsString('A private draft', $html);
    }

    public function test_an_article_that_lists_its_own_main_category_as_also_in_is_not_doubled(): void
    {
        [$index, , , $sub] = $this->tree();
        $article = $this->published('Own place twice', $sub);
        $article->extraCategories()->attach($sub->id);

        $html = $this->get('/category/'.$index->slug.'?view=index')->getContent();

        $this->assertSame(1, substr_count($html, 'Own place twice</a>'));
    }

    public function test_a_written_subject_is_a_link_and_a_planned_one_says_details_coming(): void
    {
        [$index, , , $sub] = $this->tree();
        $coming = $this->line('Still to write', $sub);
        $done = $this->published('Already written', $sub);

        $html = $this->get('/category/'.$index->slug.'?view=index')->getContent();

        $this->assertStringContainsString('<a href="'.route('article.show', $done->slug).'">Already written</a>', $html);
        $this->assertStringContainsString('<span class="qi__read">Read</span>', $html);
        $this->assertStringContainsString('Still to write', $html);
        $this->assertStringNotContainsString(route('article.show', $coming->slug), $html, 'a planned subject is not a link');
        $this->assertStringContainsString('<span class="qi__soon">details coming</span>', $html);
    }

    public function test_a_code_is_the_old_index_id_when_there_is_one_and_the_new_code_otherwise(): void
    {
        [$index, , , $sub] = $this->tree();
        $plain = $this->published('No old id', $sub, ['working_name' => 'my own note']);

        $html = $this->get('/category/'.$index->slug.'?view=index')->getContent();

        $this->assertStringContainsString('<span class="qi__code">'.$plain->code.'</span>', $html);
        $this->assertStringNotContainsString('my own note', $html, 'a working name that is not an index ID is never shown');
    }

    public function test_private_things_never_appear_in_the_index(): void
    {
        [$index, , , $sub] = $this->tree();
        $this->line('A member article waiting', $sub, ['is_index_line' => false]);                          // a draft, not an index line
        $this->line('Waiting in review', $sub, ['status' => 'in_review', 'is_index_line' => false]);
        $this->line('Archived line', $sub, ['status' => 'archived']);
        $this->published('Future one', $sub, ['publish_date' => now()->addDay()]);                        // published but not yet, not an index line
        $hidden = $this->cat('Hidden branch', $index, ['is_visible' => false]);
        $this->line('Inside the hidden branch', $hidden);
        $this->line('Visible planned line', $sub);

        $html = $this->get('/category/'.$index->slug.'?view=index')->getContent();

        foreach (['A member article waiting', 'Waiting in review', 'Archived line', 'Future one', 'Hidden branch', 'Inside the hidden branch'] as $secret) {
            $this->assertStringNotContainsString($secret, $html, $secret);
        }
        $this->assertStringContainsString('Visible planned line', $html);
    }

    public function test_a_planned_line_that_is_dated_for_later_still_says_details_coming(): void
    {
        [$index, , , $sub] = $this->tree();
        $later = $this->line('Planned for later', $sub, ['status' => 'published', 'publish_date' => now()->addDay()]);

        $html = $this->get('/category/'.$index->slug.'?view=index')->getContent();

        $this->assertStringContainsString('Planned for later', $html);
        $this->assertStringNotContainsString(route('article.show', $later->slug), $html);
    }

    public function test_staff_can_open_every_line_to_preview_it(): void
    {
        [$index, , , $sub] = $this->tree();
        $draft = $this->line('A draft line', $sub);
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create(['role' => 'moderator', 'status' => 'active']));

        $this->get('/category/'.$index->slug.'?view=index')->assertSee(route('article.show', $draft->slug), false);
    }

    public function test_a_heading_with_only_subjects_is_a_row_that_opens(): void
    {
        // An index without Parts, like History: the heading's sub-categories are rows that hold the subjects.
        $heading = $this->cat('History and Civilizations');
        $row = $this->cat('Pre-Islamic History', $heading, ['working_name' => 'HX-3.1']);
        $this->line('Ancient Arabia', $row, ['working_name' => 'HX-3.1.001']);

        $html = $this->get('/category/'.$heading->slug.'?view=index')->getContent();

        $this->assertStringContainsString('<details class="qi__cat" data-qi-cat', $html);
        $this->assertStringContainsString('<span class="qi__code">HX-3.1</span> Pre-Islamic History</summary>', $html);
        $this->assertStringContainsString('Ancient Arabia', $html);
        $this->assertStringNotContainsString('qi__part-title', $html, 'no section title when there are no sub-headings');
    }

    public function test_the_index_view_has_the_search_box_open_all_and_close_all_and_its_script(): void
    {
        [$index] = $this->tree();

        $html = $this->get('/category/'.$index->slug.'?view=index')->getContent();

        $this->assertStringContainsString('data-qi-search', $html);
        $this->assertStringContainsString('data-qi-open', $html);
        $this->assertStringContainsString('data-qi-close', $html);
        $this->assertStringContainsString('js/subject-index.js', $html);
        $this->assertFileExists(public_path('js/subject-index.js'));
        // Grid and List become links back to the normal page, and Index is the pressed one.
        $this->assertStringContainsString('data-active="index"', $html);
        $this->assertMatchesRegularExpression('#<a class="view-toggle__btn" href="[^"]*/category/'.$index->slug.'" data-view-link="grid"#', $html);
        $this->assertMatchesRegularExpression('#data-view-link="index" aria-pressed="true"#', $html);
    }

    public function test_every_line_can_be_found_by_the_quick_search_words(): void
    {
        [$index, , , $sub] = $this->tree();
        $this->line('Allah (SWT) as the Creator', $sub, ['working_name' => 'AL-1.1.003']);

        $html = $this->get('/category/'.$index->slug.'?view=index')->getContent();

        // The words the search looks in: the code, the title and the names of the places above it.
        $this->assertMatchesRegularExpression('#data-text="[^"]*al-1\.1\.003[^"]*allah \(swt\) as the creator[^"]*existence of allah \(swt\)[^"]*existence and identity[^"]*"#', $html);
    }

    public function test_a_very_big_branch_is_cut_into_link_lines_so_the_page_stays_light(): void
    {
        $top = $this->cat('Heading');
        $one = $this->cat('First index', $top);
        $two = $this->cat('Second index', $top);
        foreach (range(1, 5) as $i) {
            $this->line("First subject $i", $one);
            $this->line("Second subject $i", $two);
        }
        CategoryIndex::$budget = 9;

        $html = $this->get('/category/'.$top->slug.'?view=index')->getContent();

        $this->assertStringContainsString('First subject 5', $html, 'the first branch fits and is drawn in full');
        $this->assertStringNotContainsString('Second subject 1', $html, 'the second does not fit');
        $this->assertStringContainsString('class="qi__more"', $html);
        $this->assertStringContainsString('/category/'.$two->slug.'?view=index', $html);
        $this->assertStringContainsString('10 subjects', $html, 'the total counts the cut part too');
        $this->assertStringContainsString('5 subjects', $html);
    }

    public function test_the_index_is_built_with_a_small_number_of_database_queries(): void
    {
        [$index, , , $sub] = $this->tree();
        foreach (range(1, 60) as $i) {
            $this->line("Subject $i", $sub);
        }
        DB::enableQueryLog();

        CategoryIndex::build($index, false);

        $this->assertLessThanOrEqual(7, count(DB::getQueryLog()), 'a few queries for the whole page, however many subjects');
    }

    public function test_an_empty_branch_says_so_and_a_hidden_category_has_no_index(): void
    {
        $empty = $this->cat('Nothing here');
        $this->get('/category/'.$empty->slug.'?view=index')->assertOk()->assertSee('No articles here yet.');

        $hidden = $this->cat('Secret', null, ['is_visible' => false]);
        $this->get('/category/'.$hidden->slug.'?view=index')->assertNotFound();
    }

    public function test_lines_moved_from_the_old_site_are_marked_as_index_lines(): void
    {
        $line = $this->line('Marked', $this->cat('Anywhere'));
        $this->assertTrue($line->fresh()->is_index_line);
        $plain = Article::create(['title' => ['en' => 'Plain'], 'slug' => 'plain-one', 'status' => 'draft']);
        $this->assertFalse($plain->fresh()->is_index_line);
    }
}
