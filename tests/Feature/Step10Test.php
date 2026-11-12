<?php

namespace Tests\Feature;

use App\Filament\Pages\SiteSettings;
use App\Filament\Resources\Articles\Pages\CreateArticle;
use App\Filament\Resources\Articles\Pages\EditArticle;
use App\Filament\Resources\Articles\Pages\ListArticles;
use App\Models\Article;
use App\Models\Category;
use App\Models\Setting;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Step 10: the home page has Featured (chosen on the article) and Latest (the newest), like the old site. */
class Step10Test extends TestCase
{
    use RefreshDatabase;

    private Category $cat;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::forget();
        Filament::setCurrentPanel('admin');
        $this->cat = Category::create(['name' => ['en' => 'Shows'], 'slug' => 'shows']);
    }

    private function article(string $title, array $extra = []): Article
    {
        return Article::create(array_merge([
            'title' => ['en' => $title], 'slug' => Article::uniqueSlug($title), 'category_id' => $this->cat->id,
            'status' => 'published', 'publish_date' => now()->subDay(),
        ], $extra));
    }

    private function as(string $role): User
    {
        $u = User::factory()->create(['role' => $role, 'status' => 'active']);
        $this->actingAs($u);

        return $u;
    }

    /** The titles in one part of the home page, in the order drawn. */
    private function part(string $heading): array
    {
        $html = $this->get('/')->getContent();
        $start = strpos($html, '>'.$heading.'</h2>');
        if ($start === false) {
            return [];
        }
        $end = strpos($html, '</section>', $start);
        preg_match_all('/pcard__title"><a href="[^"]*">([^<]*)<\/a>/', substr($html, $start, $end - $start), $m);

        return $m[1];
    }

    public function test_the_home_page_order_is_featured_then_latest_then_browse(): void
    {
        $this->article('A featured one', ['is_featured' => true]);
        $this->article('A plain one');

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertLessThan(strpos($html, '>Latest articles</h2>'), strpos($html, '>Featured</h2>'));
        $this->assertLessThan(strpos($html, '>Browse by category</h2>'), strpos($html, '>Latest articles</h2>'));
        $this->assertStringContainsString('data-view-toggle', $html, 'the grid / list switch is on the home page too');
    }

    public function test_featured_shows_only_featured_published_articles_in_the_chosen_order(): void
    {
        $this->article('Third in order', ['is_featured' => true, 'featured_order' => 3]);
        $this->article('First in order', ['is_featured' => true, 'featured_order' => 1]);
        $this->article('Second in order', ['is_featured' => true, 'featured_order' => 2]);
        $this->article('Not featured');
        $this->article('Featured draft', ['is_featured' => true, 'status' => 'draft']);
        $this->article('Featured but not yet', ['is_featured' => true, 'publish_date' => now()->addDay()]);
        $this->article('Featured and in review', ['is_featured' => true, 'status' => 'in_review']);

        $this->assertSame(['First in order', 'Second in order', 'Third in order'], $this->part('Featured'));
    }

    public function test_featured_articles_with_no_order_come_after_the_numbered_ones_newest_first(): void
    {
        $this->article('Unnumbered old', ['is_featured' => true, 'featured_order' => 0, 'publish_date' => now()->subDays(5)]);
        $this->article('Unnumbered new', ['is_featured' => true, 'featured_order' => 0, 'publish_date' => now()->subDay()]);
        $this->article('Numbered', ['is_featured' => true, 'featured_order' => 1]);

        $this->assertSame(['Numbered', 'Unnumbered new', 'Unnumbered old'], $this->part('Featured'));
    }

    public function test_latest_is_the_newest_published_and_does_not_repeat_the_featured_ones(): void
    {
        $this->article('Oldest', ['publish_date' => now()->subDays(9)]);
        $this->article('Middle', ['publish_date' => now()->subDays(5)]);
        $this->article('Newest', ['publish_date' => now()->subHours(2)]);
        $this->article('Featured newest', ['is_featured' => true, 'publish_date' => now()->subHour()]);
        $this->article('A draft', ['status' => 'draft']);
        $this->article('Future', ['publish_date' => now()->addDay()]);

        $this->assertSame(['Featured newest'], $this->part('Featured'));
        $this->assertSame(['Newest', 'Middle', 'Oldest'], $this->part('Latest articles'), 'newest first; featured is not repeated; no drafts or future dates');
    }

    public function test_the_numbers_are_six_featured_and_eight_latest_until_the_settings_say_otherwise(): void
    {
        foreach (range(1, 9) as $i) {
            $this->article("Featured $i", ['is_featured' => true, 'featured_order' => $i]);
        }
        foreach (range(1, 12) as $i) {
            $this->article("Plain $i", ['publish_date' => now()->subDays($i)]);
        }

        $this->assertCount(6, $this->part('Featured'));
        $this->assertCount(8, $this->part('Latest articles'));

        Setting::put('home_featured_count', 3);
        Setting::put('home_latest_count', 12);
        $this->assertCount(3, $this->part('Featured'));
        $this->assertCount(12, $this->part('Latest articles'), 'the 6 featured ones that were cut are still not shown twice, and there are enough others');
    }

    public function test_a_part_set_to_zero_is_not_shown(): void
    {
        $this->article('A featured one', ['is_featured' => true]);
        $this->article('A plain one');
        Setting::put('home_featured_count', 0);

        $html = $this->get('/')->getContent();
        $this->assertStringNotContainsString('>Featured</h2>', $html);
        $this->assertStringContainsString('>Latest articles</h2>', $html);
        $this->assertStringContainsString('A featured one', $html, 'it is simply a latest article now');

        Setting::put('home_latest_count', 0);
        $this->get('/')->assertDontSee('>Latest articles</h2>', false)->assertSee('No articles here yet.');
    }

    public function test_a_demo_with_no_published_articles_still_shows_the_categories(): void
    {
        $this->get('/')->assertOk()->assertSee('No articles here yet.')->assertSee('Browse by category')->assertSee('Shows');
    }

    // ---------- on the article ----------

    public function test_a_moderator_features_an_article_with_an_order_on_the_edit_page(): void
    {
        $this->as('moderator');
        $a = $this->article('To feature', ['status' => 'draft', 'publish_date' => null]);

        Livewire::test(EditArticle::class, ['record' => $a->getRouteKey()])
            ->assertFormFieldExists('is_featured')
            ->fillForm(['is_featured' => true, 'featured_order' => 2, 'status' => 'published'])
            ->call('save')->assertHasNoFormErrors();

        $this->assertTrue($a->fresh()->is_featured);
        $this->assertSame(2, $a->fresh()->featured_order);
        $this->assertSame(['To feature'], $this->part('Featured'));
    }

    public function test_the_order_box_only_shows_when_the_article_is_featured(): void
    {
        $this->as('moderator');
        $a = $this->article('Plain');

        Livewire::test(EditArticle::class, ['record' => $a->getRouteKey()])
            ->assertFormFieldIsHidden('featured_order')
            ->fillForm(['is_featured' => true])
            ->assertFormFieldIsVisible('featured_order');
    }

    public function test_an_author_cannot_feature_an_article_even_with_a_forged_form(): void
    {
        $this->as('author');
        $cat = $this->cat;

        $data = \App\Filament\Support\ArticleRules::apply(['title' => ['en' => 'Forged'], 'status' => 'draft', 'is_featured' => true, 'featured_order' => 1]);
        $this->assertFalse($data['is_featured']);
        $this->assertArrayNotHasKey('featured_order', $data);

        $edit = \App\Filament\Support\ArticleRules::apply(['title' => ['en' => 'Forged'], 'status' => 'draft', 'is_featured' => true, 'featured_order' => 1], 'draft', 'a-slug');
        $this->assertArrayNotHasKey('is_featured', $edit, 'an edit by an author never changes it');

        $mine = $this->article('Authors piece', ['author_id' => auth()->id(), 'status' => 'draft']);
        Livewire::test(EditArticle::class, ['record' => $mine->getRouteKey()])->fillForm(['is_featured' => true])->call('save');
        $this->assertFalse($mine->fresh()->is_featured);
    }

    public function test_an_author_creating_an_article_cannot_make_it_featured(): void
    {
        $this->as('author');

        Livewire::test(CreateArticle::class)
            ->fillForm(['title' => ['en' => 'New by author'], 'category_id' => $this->cat->id, 'status' => 'draft', 'is_featured' => true])
            ->call('create')->assertHasNoFormErrors();

        $this->assertFalse(Article::where('slug', 'new-by-author')->first()->is_featured);
    }

    public function test_a_clone_is_not_featured(): void
    {
        $this->as('moderator');
        $a = $this->article('Original', ['is_featured' => true, 'featured_order' => 1]);

        Livewire::test(ListArticles::class)->callAction(TestAction::make('replicate')->table($a));

        $copy = Article::where('id', '!=', $a->id)->first();
        $this->assertFalse($copy->is_featured);
        $this->assertSame(0, $copy->featured_order);
    }

    public function test_the_star_in_the_list_features_and_unfeatures_in_one_click(): void
    {
        $this->as('moderator');
        $a = $this->article('Click me');

        Livewire::test(ListArticles::class)->call('updateTableColumnState', 'is_featured', $a->getKey(), true);
        $this->assertTrue($a->fresh()->is_featured);
        $this->assertSame(['Click me'], $this->part('Featured'));

        Livewire::test(ListArticles::class)->call('updateTableColumnState', 'is_featured', $a->getKey(), false);
        $this->assertFalse($a->fresh()->is_featured);
    }

    public function test_the_list_can_be_filtered_to_the_featured_ones(): void
    {
        $this->as('moderator');
        $this->article('Star one', ['is_featured' => true]);
        $this->article('Plain two');

        Livewire::test(ListArticles::class)->filterTable('is_featured', true)->assertSee('Star one')->assertDontSee('Plain two');
    }

    // ---------- the settings ----------

    public function test_the_super_user_sets_how_many_are_shown(): void
    {
        $this->as('superuser');
        foreach (range(1, 5) as $i) {
            $this->article("Featured $i", ['is_featured' => true, 'featured_order' => $i]);
        }

        Livewire::test(SiteSettings::class)
            ->assertFormFieldExists('home_featured_count')
            ->fillForm(['home_featured_count' => '2', 'home_latest_count' => '30'])
            ->call('save')->assertHasFormErrors(['home_latest_count']);   // never more than 24

        Livewire::test(SiteSettings::class)
            ->fillForm(['home_featured_count' => '2', 'home_latest_count' => '24'])
            ->call('save')->assertHasNoFormErrors();

        Setting::forget();
        $this->assertSame(2, Setting::get('home_featured_count'));
        $this->assertSame(24, Setting::get('home_latest_count'));
        $this->assertCount(2, $this->part('Featured'));
    }

    public function test_an_empty_box_means_the_usual_number(): void
    {
        $this->as('superuser');
        Setting::put('home_featured_count', 2);

        Livewire::test(SiteSettings::class)->fillForm(['home_featured_count' => '', 'home_latest_count' => ''])->call('save');

        Setting::forget();
        $this->assertNull(Setting::get('home_featured_count'));
        foreach (range(1, 7) as $i) {
            $this->article("F$i", ['is_featured' => true, 'featured_order' => $i]);
        }
        $this->assertCount(6, $this->part('Featured'));
    }
}
