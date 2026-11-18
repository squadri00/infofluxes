<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Setting;
use App\Models\Story;
use App\Support\TopicTabs;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Step 88: a section with many items shows tabs (All and one for each sub-category). */
class Step88Test extends TestCase
{
    use RefreshDatabase;

    private Category $root;

    private Category $history;

    private Category $people;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::forget();
        $this->root = Category::create(['name' => ['en' => 'Stories in pictures'], 'slug' => 'stories-in-pictures', 'custom_data' => ['topic_tabs' => true]]);
        $this->history = Category::create(['name' => ['en' => 'History'], 'slug' => 'history', 'parent_id' => $this->root->id, 'sort_order' => 1, 'description' => ['en' => 'Places and times.']]);
        $this->people = Category::create(['name' => ['en' => 'Famous People'], 'slug' => 'famous-people', 'parent_id' => $this->root->id, 'sort_order' => 2]);
        Category::create(['name' => ['en' => 'Empty topic'], 'slug' => 'empty-topic', 'parent_id' => $this->root->id, 'sort_order' => 3]);
    }

    private function story(string $title, Category $cat): Story
    {
        return Story::create(['title' => ['en' => $title], 'slug' => Story::uniqueSlug($title), 'category_id' => $cat->id, 'status' => 'published', 'publish_date' => now()->subDay()]);
    }

    public function test_the_page_shows_all_and_a_tab_for_each_topic_that_has_stories(): void
    {
        $this->story('Old Mecca', $this->history);
        $this->story('Nuruddin', $this->people);
        $this->story('Loose story', $this->root);

        $html = $this->get('/category/stories-in-pictures')->assertOk()->getContent();

        $this->assertStringContainsString('topic-tabs', $html);
        $this->assertMatchesRegularExpression('/>\s*All\s*<span class="topic-tabs__count">3</', $html);
        $this->assertMatchesRegularExpression('/>\s*History\s*<span class="topic-tabs__count">1</', $html);
        $this->assertStringNotContainsString('topic=empty-topic', $html, 'a topic without stories has no tab');
        foreach (['Old Mecca', 'Nuruddin', 'Loose story'] as $title) {
            $this->assertStringContainsString($title, $html);
        }
    }

    public function test_a_tab_shows_only_its_own_stories(): void
    {
        $this->story('Old Mecca', $this->history);
        $this->story('Nuruddin', $this->people);

        $this->get('/category/stories-in-pictures?topic=history')->assertOk()->assertSee('Old Mecca')->assertDontSee('Nuruddin')->assertSee('Places and times.');
        $this->get('/category/stories-in-pictures?topic=nothing-like-this')->assertOk()->assertSee('Old Mecca')->assertSee('Nuruddin');   // an unknown tab is All
    }

    public function test_a_tab_is_paged_and_the_page_keeps_the_tab(): void
    {
        for ($i = 1; $i <= TopicTabs::PER_PAGE + 1; $i++) {
            $this->story('History story '.sprintf('%02d', $i), $this->history);
        }

        $first = $this->get('/category/stories-in-pictures?topic=history')->assertOk();
        $first->assertSee('Page 1 of 2')->assertSee('page=2', false)->assertSee('topic=history', false);
    }

    public function test_the_second_page_of_a_tab(): void
    {
        for ($i = 1; $i <= TopicTabs::PER_PAGE + 1; $i++) {
            $this->story('History story '.sprintf('%02d', $i), $this->history);
        }

        $this->get('/category/stories-in-pictures?topic=history&page=2')->assertOk()->assertSee('Page 2 of 2');
    }

    public function test_a_section_without_the_switch_has_no_tabs(): void
    {
        $this->root->update(['custom_data' => []]);
        $this->story('Old Mecca', $this->history);

        $html = $this->get('/category/stories-in-pictures')->assertOk()->getContent();
        $this->assertStringNotContainsString('topic-tabs', $html);
    }

    public function test_the_index_view_still_works_for_a_section_with_tabs(): void
    {
        $this->story('Old Mecca', $this->history);

        $this->get('/category/stories-in-pictures?view=index')->assertOk();
    }

    public function test_the_category_form_has_the_switch(): void
    {
        $this->actingAs(\App\Models\User::factory()->create(['role' => 'superuser', 'status' => 'active']));

        \Livewire\Livewire::test(\App\Filament\Resources\Categories\Pages\EditCategory::class, ['record' => $this->root->getRouteKey()])
            ->assertSee('Show its sub-categories as tabs')->assertFormSet(['custom_data.topic_tabs' => true]);
    }

    public function test_the_tabs_change_smoothly_with_a_small_script_and_stay_real_links(): void
    {
        $this->story('Old Mecca', $this->history);

        $html = $this->get('/category/stories-in-pictures')->assertOk()->getContent();
        $this->assertStringContainsString('data-topic-nav', $html);
        $this->assertStringContainsString('data-topic-panel', $html);
        $this->assertStringContainsString('js/topic-tabs.js', $html);
        $this->assertStringContainsString('href="http://localhost/category/stories-in-pictures?topic=history"', $html, 'a tab is a real link, so it works without the script');

        $js = file_get_contents(public_path('js/topic-tabs.js'));
        $this->assertStringContainsString('prefers-reduced-motion', $js);
        $this->assertStringContainsString('popstate', $js);
        $this->assertStringContainsString('.topic-tabs__ink', file_get_contents(public_path('css/site.css')));
    }
}
