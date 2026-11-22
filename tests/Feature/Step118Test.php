<?php

namespace Tests\Feature;

use App\Filament\Resources\DidYouKnow\Pages\ManageDidYouKnow;
use App\Models\DidYouKnow;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Step 118: a "Did You Know?" fact can be featured on the home page. */
class Step118Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::forget();
    }

    protected function tearDown(): void
    {
        Setting::forget();
        parent::tearDown();
    }

    private function fact(string $title, array $extra = []): DidYouKnow
    {
        return DidYouKnow::create(array_merge(['title' => ['en' => $title], 'slug' => str($title)->slug()->toString(), 'body' => ['en' => '<p>The answer.</p>'], 'source' => 'A book', 'status' => 'published'], $extra));
    }

    public function test_the_home_page_shows_the_featured_facts_in_their_order_and_nothing_else(): void
    {
        $this->fact('Third fact', ['is_featured' => true, 'featured_order' => 0]);
        $this->fact('Second fact', ['is_featured' => true, 'featured_order' => 2]);
        $this->fact('First fact', ['is_featured' => true, 'featured_order' => 1]);
        $this->fact('Not featured fact');
        $this->fact('Draft fact', ['is_featured' => true, 'status' => 'draft']);
        $this->fact('No source fact', ['is_featured' => true, 'source' => null]);

        $page = $this->get(route('home'))->assertOk();

        // The same cards in the same layout as the Featured articles: the mosaic of "pcard" cards.
        preg_match('#<section class="block" data-home-facts>.*?</section>#s', $page->getContent(), $part);
        $this->assertStringContainsString('class="mosaic"', $part[0] ?? '');
        $this->assertSame(3, substr_count($part[0] ?? '', 'class="pcard '));
        $this->assertStringNotContainsString('class="related"', $part[0] ?? '');

        $page->assertSee('data-home-facts', false)->assertSee('First fact')->assertSee('Second fact')->assertSee('Third fact')->assertSee('All facts')
            ->assertDontSee('Not featured fact')->assertDontSee('Draft fact')->assertDontSee('No source fact');
        $this->assertSame(['First fact', 'Second fact', 'Third fact'], DidYouKnow::featuredOnHome()->get()->map(fn ($f) => $f->getTranslation('title', 'en'))->all());
        $html = $page->getContent();
        $this->assertLessThan(strpos($html, 'Second fact'), strpos($html, 'First fact'));
        $this->assertLessThan(strpos($html, 'Third fact'), strpos($html, 'Second fact'));
    }

    public function test_without_a_featured_fact_there_is_no_part_and_the_number_can_be_set_or_zero(): void
    {
        $this->fact('Plain fact');
        $this->get(route('home'))->assertOk()->assertDontSee('data-home-facts', false);

        foreach (range(1, 6) as $n) {
            $this->fact('Featured '.$n, ['is_featured' => true, 'featured_order' => $n]);
        }
        $this->get(route('home'))->assertSee('Featured 4')->assertDontSee('Featured 5');   // the usual number is 4

        Setting::put('home_dyk_count', '2');
        $this->get(route('home'))->assertSee('Featured 2')->assertDontSee('Featured 3');

        Setting::put('home_dyk_count', '0');
        $this->get(route('home'))->assertDontSee('data-home-facts', false);
    }

    public function test_a_moderator_can_switch_the_star_in_the_list_and_an_editor_cannot(): void
    {
        $fact = $this->fact('A fact');
        $editor = User::factory()->create(['role' => 'author', 'status' => 'active', 'admin_screens' => ['facts']]);
        $this->actingAs($editor);
        Livewire::test(ManageDidYouKnow::class)->assertTableColumnExists('is_featured');

        $this->actingAs(User::factory()->create(['role' => 'moderator', 'status' => 'active', 'admin_screens' => ['facts']]));
        Livewire::test(ManageDidYouKnow::class)->assertTableColumnExists('is_featured')->assertCanSeeTableRecords([$fact]);
        $fact->update(['is_featured' => true]);
        $this->assertTrue((bool) $fact->fresh()->is_featured);
    }

    public function test_an_editor_who_cannot_publish_cannot_feature_a_fact_through_the_form(): void
    {
        $data = ['is_featured' => true, 'featured_order' => 1, 'body' => ['en' => '<p>x</p>']];

        $this->actingAs(User::factory()->create(['role' => 'author', 'status' => 'active']));
        $this->assertArrayNotHasKey('is_featured', \App\Filament\Resources\DidYouKnow\DidYouKnowResource::clean($data));

        $this->actingAs(User::factory()->create(['role' => 'moderator', 'status' => 'active']));
        $this->assertTrue(\App\Filament\Resources\DidYouKnow\DidYouKnowResource::clean($data)['is_featured']);
    }
}
