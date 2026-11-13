<?php

namespace Tests\Feature;

use App\Filament\Resources\Pages\Pages\ManagePages;
use App\Models\Article;
use App\Models\Page;
use App\Models\Setting;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Step 21: the website pages (About, Privacy...), listed in the footer and opened at /their-address. */
class Step21Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::forget();
        Filament::setCurrentPanel('admin');
    }

    private function page(string $title, array $extra = []): Page
    {
        return Page::create(array_merge([
            'title' => ['en' => $title], 'slug' => str($title)->slug()->toString(), 'body' => ['en' => '<p>Words of '.$title.'.</p>'],
            'status' => 'published', 'in_footer' => true, 'sort_order' => 1,
        ], $extra));
    }

    public function test_a_published_page_opens_at_its_address(): void
    {
        $this->page('About us');

        $this->get('/about-us')->assertOk()->assertSee('About us')->assertSee('Words of About us.');
    }

    public function test_a_draft_page_is_not_found_for_visitors_but_staff_can_preview_it(): void
    {
        $this->page('Not ready', ['status' => 'draft']);

        $this->get('/not-ready')->assertNotFound();

        $this->actingAs(User::factory()->create(['role' => 'superuser', 'status' => 'active']));
        $this->get('/not-ready')->assertOk()->assertSee('This is a draft');
    }

    public function test_an_unknown_address_is_still_not_found(): void
    {
        $this->get('/nothing-here')->assertNotFound();
    }

    public function test_the_footer_lists_the_ticked_published_pages_in_order(): void
    {
        $this->page('Privacy', ['sort_order' => 2]);
        $this->page('About us', ['sort_order' => 1]);
        $this->page('Hidden from footer', ['in_footer' => false]);
        $this->page('Draft page', ['status' => 'draft']);

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('footer-about', $html);
        $this->assertLessThan(strpos($html, '>Privacy<'), strpos($html, '>About us<'), 'by order');
        $this->assertStringNotContainsString('Hidden from footer', $html);
        $this->assertStringNotContainsString('Draft page', $html);
    }

    public function test_no_pages_means_no_footer_column(): void
    {
        $this->get('/')->assertOk()->assertDontSee('footer-about', false);
    }

    public function test_an_article_wins_over_a_page_with_the_same_address_and_new_articles_avoid_page_addresses(): void
    {
        $this->page('Privacy');

        $this->assertSame('privacy-2', Article::uniqueSlug('Privacy'));
        $this->assertTrue(Page::slugTaken('admin'), 'a part of the site');
        $this->assertTrue(Page::slugTaken('privacy'), 'another page');
        $this->assertSame('privacy-2', Page::uniqueSlug('Privacy'));
    }

    public function test_unsafe_words_in_a_page_are_removed_when_it_is_shown(): void
    {
        $this->page('Careful', ['body' => ['en' => '<p>Hello</p><script>alert(1)</script>']]);

        $this->get('/careful')->assertOk()->assertSee('Hello')->assertDontSee('<script>alert(1)', false);
    }

    public function test_pages_are_in_the_sitemap_unless_hidden_from_search_engines(): void
    {
        $this->page('About us');
        $this->page('Secret', ['no_index' => true]);

        $this->get('/sitemap.xml')->assertOk()->assertSee('/about-us', false)->assertDontSee('/secret', false);
    }

    public function test_the_admin_list_opens_for_the_super_user_and_creates_a_page_with_an_address(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'author', 'status' => 'active']));
        Livewire::test(ManagePages::class)->assertForbidden();

        $this->actingAs(User::factory()->create(['role' => 'superuser', 'status' => 'active']));
        Livewire::test(ManagePages::class)
            ->callAction('create', ['title' => ['en' => 'Our promise'], 'body' => ['en' => '<p>We promise.</p>'], 'status' => 'published', 'in_footer' => true, 'sort_order' => 3])
            ->assertHasNoActionErrors();

        $page = Page::first();
        $this->assertSame('our-promise', $page->slug, 'made from the title');
        $this->assertSame('P-'.$page->id, $page->code);
    }

    public function test_a_moderator_can_be_given_the_pages_screen(): void
    {
        $mod = User::factory()->create(['role' => 'moderator', 'status' => 'active']);
        $this->assertFalse($mod->canUseScreen('pages'));

        $mod->update(['admin_screens' => ['pages']]);
        $this->assertTrue($mod->fresh()->canUseScreen('pages'));
    }
}
