<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Category;
use App\Models\Reaction;
use App\Models\Setting;
use App\Models\Story;
use App\Models\User;
use App\Support\ViewCounter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Step 59: likes, dislikes and views on the home page cards, and the view counter. */
class Step59Test extends TestCase
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
        return Article::create(['title' => ['en' => 'Moon article'], 'slug' => 'moon-article', 'category_id' => $this->cat->id, 'status' => 'published', 'publish_date' => now()->subDay()]);
    }

    private function story(): Story
    {
        return Story::create(['title' => ['en' => 'Moon story'], 'slug' => 'moon-story', 'category_id' => $this->cat->id, 'status' => 'published', 'is_featured' => true, 'publish_date' => now()->subDay()]);
    }

    private function react(Article|Story $item, int $value): void
    {
        $u = User::factory()->create(['role' => 'member', 'status' => 'active']);
        $item->reactions()->create(['user_id' => $u->id, 'value' => $value]);
    }

    public function test_home_cards_show_likes_dislikes_and_views(): void
    {
        $a = $this->article();
        $s = $this->story();
        $this->react($a, Reaction::LIKE);
        $this->react($a, Reaction::LIKE);
        $this->react($a, Reaction::DISLIKE);
        $this->react($s, Reaction::LIKE);
        Article::whereKey($a->id)->toBase()->update(['views' => 1500]);

        $html = $this->get('/')->assertOk()->getContent();
        $this->assertSame(2, substr_count($html, 'class="cstats"'), 'one row for the article, one for the story');
        $this->assertStringContainsString('1.5K', $html);
    }

    public function test_the_switch_hides_the_row(): void
    {
        $this->article();
        Setting::put('card_stats_on', false);
        $this->get('/')->assertOk()->assertDontSee('class="cstats"', false);
    }

    public function test_a_view_is_counted_once_per_visit_and_does_not_change_the_edited_date(): void
    {
        $a = $this->article();
        $stamp = $a->fresh()->updated_at;
        $s = $this->story();

        $this->withHeader('User-Agent', 'Mozilla/5.0')->get('/moon-article');
        $this->get('/moon-article');
        $this->get('/stories/moon-story');

        $this->assertSame(1, (int) $a->fresh()->views);
        $this->assertSame(1, (int) $s->fresh()->views);
        $this->assertEquals($stamp, $a->fresh()->updated_at);
    }

    public function test_robots_and_staff_are_not_counted(): void
    {
        $a = $this->article();
        $this->withHeader('User-Agent', 'Googlebot/2.1')->get('/moon-article');
        $this->assertSame(0, (int) $a->fresh()->views);

        $this->actingAs(User::factory()->create(['role' => 'superuser', 'status' => 'active']))->withHeader('User-Agent', 'Mozilla/5.0')->get('/moon-article');
        $this->assertSame(0, (int) $a->fresh()->views);
    }

    public function test_short_numbers(): void
    {
        $this->assertSame('999', ViewCounter::short(999));
        $this->assertSame('1K', ViewCounter::short(1000));
        $this->assertSame('1.2K', ViewCounter::short(1234));
        $this->assertSame('2.5M', ViewCounter::short(2500000));
    }

    public function test_card_picture_look_is_off_until_set_and_only_accepts_safe_values(): void
    {
        $this->assertSame('', \App\Support\CardPictures::css());
        $this->get('/')->assertDontSee('--card-filter', false);

        Setting::put('card_image_saturation', 85);
        Setting::put('card_image_fit', 'contain');
        Setting::put('card_image_focus', 'top');
        $this->get('/')->assertSee('--card-filter:saturate(85%) contrast(100%) brightness(100%)', false)->assertSee('--card-fit:contain', false)->assertSee('--card-pos:center top', false);

        Setting::put('card_image_fit', '};alert(1)');
        Setting::put('card_image_saturation', 9999);
        $css = \App\Support\CardPictures::css();
        $this->assertStringNotContainsString('alert', $css);
        $this->assertStringContainsString('saturate(130%)', $css);
    }

    public function test_the_card_stats_switch_is_saved_by_site_settings(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'superuser', 'status' => 'active']));
        \Livewire\Livewire::test(\App\Filament\Pages\SiteSettings::class)
            ->fillForm(['card_stats_on' => false, 'card_image_saturation' => 80])->call('save')->assertHasNoFormErrors();
        $this->assertFalse((bool) filter_var(Setting::get('card_stats_on', true), FILTER_VALIDATE_BOOLEAN));
        $this->assertSame(80, (int) Setting::get('card_image_saturation'));
    }
}
