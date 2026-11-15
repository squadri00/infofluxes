<?php

namespace Tests\Feature;

use App\Filament\Pages\SiteControlsPage;
use App\Models\Article;
use App\Models\Category;
use App\Models\Setting;
use App\Models\User;
use App\Support\SiteControls;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Step 64: Site controls: On/Off switches, Quiet look and Normal look. */
class Step64Test extends TestCase
{
    use RefreshDatabase;

    private Category $cat;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::forget();
        $this->cat = Category::create(['name' => ['en' => 'Shows'], 'slug' => 'shows']);
    }

    private function article(): Article
    {
        return Article::create(['title' => ['en' => 'Moon article'], 'slug' => 'moon-article', 'category_id' => $this->cat->id, 'status' => 'published', 'publish_date' => now()->subDay(), 'reviewer_name' => 'Dr Reviewer']);
    }

    private function superuser(): User
    {
        $u = User::factory()->create(['role' => 'superuser', 'status' => 'active']);
        $this->actingAs($u);

        return $u;
    }

    public function test_everything_is_on_until_switched_off(): void
    {
        foreach (array_keys(SiteControls::all()) as $key) {
            $this->assertTrue(SiteControls::on($key), $key);
        }
        $this->assertFalse(SiteControls::isQuiet());
    }

    public function test_the_buttons_under_an_article_follow_their_switches(): void
    {
        $this->article();
        $this->get('/moon-article')->assertSee('id="engage"', false)->assertSee('engage__share', false)->assertSee('Dr Reviewer');

        SiteControls::set('share', false);
        $this->get('/moon-article')->assertSee('id="engage"', false)->assertDontSee('engage__share', false);

        SiteControls::set('reactions', false);
        SiteControls::set('save', false);
        $this->get('/moon-article')->assertDontSee('id="engage"', false);

        SiteControls::set('reviewed_by', false);
        $this->get('/moon-article')->assertDontSee('Dr Reviewer');
    }

    public function test_a_switched_off_like_or_save_is_refused_on_the_server_too(): void
    {
        $a = $this->article();
        $u = User::factory()->create(['role' => 'member', 'status' => 'active', 'email_verified_at' => now()]);
        $this->actingAs($u);

        SiteControls::set('reactions', false);
        $this->post("/react/article/{$a->id}", ['value' => 'like'])->assertNotFound();
        SiteControls::set('save', false);
        $this->post("/save/article/{$a->id}")->assertNotFound();

        SiteControls::set('reactions', true);
        $this->post("/react/article/{$a->id}", ['value' => 'like'])->assertRedirect();
    }

    public function test_header_and_home_parts_follow_their_switches(): void
    {
        $this->get('/')->assertSee('lang-menu', false)->assertSee('theme-toggle', false)->assertSee('data-view-toggle', false);

        SiteControls::set('language', false);
        SiteControls::set('theme', false);
        SiteControls::set('grid_list', false);
        $this->get('/')->assertDontSee('lang-menu', false)->assertDontSee('theme-toggle', false)->assertDontSee('data-view-toggle', false)->assertDontSee("localStorage.getItem('infofluxes.view')", false);
    }

    public function test_recently_viewed_and_related_can_be_hidden(): void
    {
        $this->article();
        Article::create(['title' => ['en' => 'Other one'], 'slug' => 'other-one', 'category_id' => $this->cat->id, 'status' => 'published', 'publish_date' => now()->subDay()]);

        $html = $this->get('/moon-article')->getContent();
        $this->assertStringContainsString('Other one', $html);

        SiteControls::set('recent', false);
        SiteControls::set('related', false);
        $html = $this->get('/moon-article')->getContent();
        $this->assertStringNotContainsString('data-recent', $html);
        $this->assertStringNotContainsString('block__title">Related', $html);
    }

    public function test_the_social_buttons_hide_with_their_switch(): void
    {
        \App\Models\SocialLink::create(['platform' => 'facebook', 'url' => 'https://facebook.com/x', 'is_active' => true, 'sort_order' => 1]);
        $this->get('/')->assertSee('social__btn--facebook', false);
        SiteControls::set('social', false);
        $this->get('/')->assertDontSee('social__btn--facebook', false);
    }

    public function test_switches_that_already_existed_keep_their_own_setting(): void
    {
        SiteControls::set('listen', false);
        SiteControls::set('newsletter', false);
        SiteControls::set('comments', false);
        SiteControls::set('card_stats', false);
        SiteControls::set('story_pdf', false);

        $this->assertFalse(\App\Support\Listen::on());
        $this->assertFalse(\App\Support\Newsletter::enabled());
        $this->assertFalse((bool) filter_var(Setting::get('comments_open'), FILTER_VALIDATE_BOOLEAN));
        $this->assertFalse((bool) filter_var(Setting::get('card_stats_on'), FILTER_VALIDATE_BOOLEAN));
        $this->assertFalse(\App\Support\StoryBook::enabled());

        SiteControls::set('listen', true);
        $this->assertTrue(\App\Support\Listen::on());
    }

    public function test_quiet_look_switches_off_only_the_noisy_parts_and_normal_look_brings_them_back(): void
    {
        SiteControls::look(true);
        foreach (SiteControls::keys('noisy') as $k) {
            $this->assertFalse(SiteControls::on($k), $k);
        }
        foreach (SiteControls::keys('extras') as $k) {
            $this->assertTrue(SiteControls::on($k), $k);
        }
        $this->assertTrue(SiteControls::isQuiet());

        SiteControls::look(false);
        foreach (SiteControls::keys('noisy') as $k) {
            $this->assertTrue(SiteControls::on($k), $k);
        }
        $this->assertFalse(SiteControls::isQuiet());
    }

    public function test_the_page_is_for_the_super_user_and_the_buttons_and_save_work(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'moderator', 'status' => 'active']));
        $this->assertFalse(SiteControlsPage::canAccess());

        $this->superuser();
        $this->assertTrue(SiteControlsPage::canAccess());
        Livewire::test(SiteControlsPage::class)
            ->assertSee('The noisy parts')->assertSee('Useful extras')->assertSee('The like and dislike buttons')->assertSee('The Listen button')
            ->call('applyLook', true);
        $this->assertTrue(SiteControls::isQuiet());

        Livewire::test(SiteControlsPage::class)->fillForm(['reactions' => true, 'share' => false])->call('save')->assertHasNoFormErrors();
        $this->assertTrue(SiteControls::on('reactions'));
        $this->assertFalse(SiteControls::on('share'));
    }
}
