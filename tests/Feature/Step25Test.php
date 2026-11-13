<?php

namespace Tests\Feature;

use App\Filament\Resources\DidYouKnow\Pages\ManageDidYouKnow;
use App\Models\Article;
use App\Models\Category;
use App\Models\DidYouKnow;
use App\Models\Page;
use App\Models\Setting;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Step 25: "Did You Know?" (the renamed Fact Files heading) and its own FAQ-style admin and page. */
class Step25Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::forget();
        Filament::setCurrentPanel('admin');
    }

    private function fact(string $title, array $extra = []): DidYouKnow
    {
        return DidYouKnow::create(array_merge([
            'title' => ['en' => $title], 'body' => ['en' => '<p>The answer to '.$title.'.</p>'], 'source' => 'https://example.org/'.str($title)->slug(),
            'status' => 'published', 'sort_order' => 1,
        ], $extra));
    }

    private function superuser(): User
    {
        return tap(User::factory()->create(['role' => 'superuser', 'status' => 'active']), fn ($u) => $this->actingAs($u));
    }

    public function test_the_page_lists_published_facts_like_a_faq_in_order(): void
    {
        $this->fact('Second fact', ['sort_order' => 2]);
        $this->fact('First fact', ['sort_order' => 1]);

        $html = $this->get(route('did-you-know'))->assertOk()->assertSee('Did You Know?')->getContent();

        $this->assertLessThan(strpos($html, 'Second fact'), strpos($html, 'First fact'), 'by the chosen order');
        $this->assertStringContainsString('<details class="dyk__item"', $html, 'each one opens and closes');
        $this->assertStringContainsString('The answer to First fact.', $html);
        $this->assertStringContainsString('href="https://example.org/first-fact"', $html, 'the source is a link when it is a web address');
    }

    public function test_the_facts_are_four_columns_of_cards_and_opening_one_closes_the_other(): void
    {
        $this->fact('One');
        $this->fact('Two', ['sort_order' => 2]);

        $html = $this->get(route('did-you-know'))->assertOk()->getContent();

        $this->assertStringContainsString('wrap wrap--wide', $html, 'the wide page, so four columns fit');
        $this->assertStringContainsString('class="dyk" data-dyk', $html);
        $this->assertStringContainsString('dyk__num', $html, 'a number on each card');
        $this->assertStringContainsString('o.open = false', $html, 'opening one closes the others');

        $css = (string) file_get_contents(public_path('css/site.css'));
        $this->assertStringContainsString('.dyk { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr));', $css);
        $this->assertStringContainsString('@media (max-width: 520px) { .dyk { grid-template-columns: minmax(0, 1fr); } }', $css, 'one column on a phone');
    }

    public function test_drafts_and_facts_without_a_source_are_never_shown(): void
    {
        $this->fact('A draft', ['status' => 'draft']);
        $this->fact('No source here', ['source' => null]);
        $this->fact('Good one');

        $this->get(route('did-you-know'))->assertOk()->assertSee('Good one')->assertDontSee('A draft')->assertDontSee('No source here');
    }

    public function test_a_source_written_in_words_is_shown_as_words(): void
    {
        $this->fact('A book fact', ['source' => 'Sahih al-Bukhari, Book of Faith, hadith 8']);

        $this->get(route('did-you-know'))->assertSee('Sahih al-Bukhari, Book of Faith, hadith 8')->assertDontSee('href="Sahih', false);
    }

    public function test_the_who_checked_it_line_and_the_ai_note_show(): void
    {
        $this->fact('Checked fact', ['reviewer_name' => 'Dr Amina Khan', 'ai_assisted' => true]);

        $this->get(route('did-you-know'))->assertSee('Reviewed by Dr Amina Khan')->assertSee('Written with AI help');
    }

    public function test_unsafe_words_in_an_answer_are_removed(): void
    {
        $this->fact('Careful', ['body' => ['en' => '<p>Fine</p><script>alert(1)</script>']]);

        $this->get(route('did-you-know'))->assertSee('Fine')->assertDontSee('<script>alert(1)', false);
    }

    public function test_facts_put_on_a_category_show_on_its_page_only(): void
    {
        $mine = Category::create(['name' => ['en' => 'Did You Know?'], 'slug' => 'did-you-know']);
        $other = Category::create(['name' => ['en' => 'History'], 'slug' => 'history']);
        $this->fact('On my page', ['category_id' => $mine->id]);
        $this->fact('Elsewhere', ['category_id' => $other->id]);
        $this->fact('Nowhere in particular');

        $this->get(route('category.show', 'did-you-know'))->assertOk()->assertSee('On my page')->assertDontSee('Elsewhere')->assertDontSee('Nowhere in particular');
        $this->get(route('did-you-know'))->assertSee('On my page')->assertSee('Elsewhere')->assertSee('Nowhere in particular');
    }

    public function test_a_category_can_leave_its_articles_off_its_page_and_show_only_its_facts(): void
    {
        $cat = Category::create(['name' => ['en' => 'Did You Know?'], 'slug' => 'did-you-know', 'custom_data' => ['hide_articles' => true]]);
        Article::create(['title' => ['en' => 'An article in it'], 'slug' => 'an-article-in-it', 'category_id' => $cat->id, 'status' => 'published', 'publish_date' => now()->subDay()]);
        $this->fact('A fact for the page', ['category_id' => $cat->id]);

        $html = $this->get(route('category.show', 'did-you-know'))->assertOk()->assertSee('A fact for the page')->getContent();

        $this->assertStringNotContainsString('An article in it', $html);
        $this->assertStringContainsString('class="view-bar"', $html, 'the facts are items too, so they get the Grid / List / Index buttons');
        $this->assertStringNotContainsString('block__title">Articles', $html);
        $this->get(route('category.show', ['slug' => 'did-you-know', 'view' => 'index']))->assertOk()->assertDontSee('An article in it');

        // The article itself and the search still work.
        $this->get('/an-article-in-it')->assertOk();
        $this->get(route('search', ['q' => 'article in it']))->assertSee('An article in it');
    }

    public function test_a_category_with_facts_but_no_articles_does_not_show_an_empty_articles_block(): void
    {
        $cat = Category::create(['name' => ['en' => 'Did You Know?'], 'slug' => 'did-you-know']);
        $this->fact('A fact for the page', ['category_id' => $cat->id]);

        $html = $this->get(route('category.show', 'did-you-know'))->assertOk()->assertSee('A fact for the page')->getContent();

        $this->assertStringNotContainsString('No articles here yet', $html);
        $this->assertStringContainsString('class="view-bar"', $html);
        $this->assertStringContainsString('class="mosaic"', $html, 'the facts are normal cards');
    }

    public function test_the_same_category_with_articles_works_like_every_other_category(): void
    {
        $cat = Category::create(['name' => ['en' => 'Did You Know?'], 'slug' => 'did-you-know']);
        Article::create(['title' => ['en' => 'An article in it'], 'slug' => 'an-article-in-it', 'category_id' => $cat->id, 'status' => 'published', 'publish_date' => now()->subDay()]);
        $this->fact('A fact for the page', ['category_id' => $cat->id]);

        $this->get(route('category.show', 'did-you-know'))->assertOk()
            ->assertSee('An article in it')->assertSee('view-bar', false)->assertSee('A fact for the page');
    }

    public function test_an_empty_category_without_facts_still_says_there_are_no_articles(): void
    {
        Category::create(['name' => ['en' => 'Empty one'], 'slug' => 'empty-one']);

        $this->get(route('category.show', 'empty-one'))->assertOk()->assertSee('No articles here yet');
    }

    public function test_a_normal_category_still_lists_its_articles(): void
    {
        $cat = Category::create(['name' => ['en' => 'History'], 'slug' => 'history']);
        Article::create(['title' => ['en' => 'An article in it'], 'slug' => 'an-article-in-it', 'category_id' => $cat->id, 'status' => 'published', 'publish_date' => now()->subDay()]);

        $this->get(route('category.show', 'history'))->assertSee('An article in it')->assertSee('view-bar', false);
    }

    public function test_the_category_form_has_the_switch(): void
    {
        $this->superuser();

        \Livewire\Livewire::test(\App\Filament\Resources\Categories\Pages\ListCategories::class)->assertSuccessful();
        $this->assertStringContainsString('custom_data.hide_articles', (string) file_get_contents(app_path('Filament/Resources/Categories/CategoryResource.php')));
    }

    public function test_the_page_and_the_category_can_share_the_name_without_clashing(): void
    {
        Category::create(['name' => ['en' => 'Did You Know?'], 'slug' => 'did-you-know']);

        $this->get('/did-you-know')->assertOk();
        $this->get('/category/did-you-know')->assertOk();
    }

    public function test_the_address_is_kept_for_the_page_so_a_new_article_or_page_cannot_take_it(): void
    {
        $this->assertSame('did-you-know-2', Article::uniqueSlug('Did you know'));
        $this->assertTrue(Page::slugTaken('did-you-know'));
    }

    public function test_the_footer_and_the_sitemap_list_the_page_only_when_there_is_a_published_fact(): void
    {
        $this->get('/')->assertDontSee(route('did-you-know'), false);
        $this->get('/sitemap.xml')->assertDontSee('/did-you-know', false);

        $this->fact('Good one');

        $this->get('/')->assertSee(route('did-you-know'), false);
        $this->get('/sitemap.xml')->assertSee('/did-you-know', false);
    }

    public function test_an_empty_page_says_so(): void
    {
        $this->get(route('did-you-know'))->assertOk()->assertSee('No facts are listed yet.');
    }

    // ---------------- the admin ----------------

    public function test_the_admin_list_is_for_staff_with_the_tick_and_the_super_user(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'author', 'status' => 'active']));
        Livewire::test(ManageDidYouKnow::class)->assertForbidden();

        $this->actingAs($mod = User::factory()->create(['role' => 'moderator', 'status' => 'active']));
        $mod->update(['admin_screens' => ['facts']]);
        Livewire::test(ManageDidYouKnow::class)->assertSuccessful();
    }

    public function test_a_new_fact_goes_to_the_end_of_the_list_as_clean_text(): void
    {
        $this->superuser();
        $this->fact('Existing', ['sort_order' => 4]);

        Livewire::test(ManageDidYouKnow::class)
            ->callAction('create', ['title' => ['en' => 'New fact'], 'body' => ['en' => '<p>Text</p><script>x</script>'], 'status' => 'draft', 'ai_assisted' => false])
            ->assertHasNoActionErrors();

        $new = DidYouKnow::where('id', '!=', 1)->first();
        $this->assertSame(5, $new->sort_order);
        $this->assertStringNotContainsString('<script', $new->getTranslation('body', 'en'));
        $this->assertSame('D-'.$new->id, $new->code);
    }

    public function test_a_fact_cannot_be_published_without_a_source(): void
    {
        $this->superuser();

        Livewire::test(ManageDidYouKnow::class)
            ->callAction('create', ['title' => ['en' => 'No source'], 'body' => ['en' => '<p>Text</p>'], 'status' => 'published', 'ai_assisted' => false, 'source' => ''])
            ->assertHasActionErrors(['source']);

        $this->assertSame(0, DidYouKnow::count());
    }

    public function test_an_ai_written_fact_cannot_be_published_without_a_reviewer(): void
    {
        $this->superuser();

        Livewire::test(ManageDidYouKnow::class)
            ->callAction('create', ['title' => ['en' => 'AI fact'], 'body' => ['en' => '<p>Text</p>'], 'status' => 'published', 'ai_assisted' => true, 'source' => 'https://example.org/x', 'reviewer_name' => ''])
            ->assertHasActionErrors(['reviewer_name']);

        Livewire::test(ManageDidYouKnow::class)
            ->callAction('create', ['title' => ['en' => 'AI fact'], 'body' => ['en' => '<p>Text</p>'], 'status' => 'published', 'ai_assisted' => true, 'source' => 'https://example.org/x', 'reviewer_name' => 'Dr Khan'])
            ->assertHasNoActionErrors();
    }

    public function test_clone_makes_a_draft_at_the_end(): void
    {
        $this->superuser();
        $fact = $this->fact('Original', ['sort_order' => 3]);

        Livewire::test(ManageDidYouKnow::class)->callTableAction('replicate', $fact);

        $copy = DidYouKnow::where('id', '!=', $fact->id)->first();
        $this->assertSame(['draft', 4], [$copy->status, $copy->sort_order]);
    }

    public function test_the_old_fact_files_name_is_gone_from_the_menu_headings_the_import_makes(): void
    {
        $this->assertContains('Did You Know?', \App\Support\OldSiteImport::HEADINGS);
        $this->assertNotContains('Fact Files', \App\Support\OldSiteImport::HEADINGS);
    }
}
