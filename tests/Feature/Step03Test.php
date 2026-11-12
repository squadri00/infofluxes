<?php

namespace Tests\Feature;

use App\Filament\Resources\Articles\Pages\CreateArticle;
use App\Filament\Resources\Articles\Pages\EditArticle;
use App\Filament\Resources\Articles\Pages\ListArticles;
use App\Filament\Resources\Categories\Pages\ListCategories;
use App\Models\Article;
use App\Models\Category;
use App\Models\User;
use App\Support\YouTube;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Step 3: articles with a main category, other categories, search words, videos, pictures, status and publish rules. */
class Step03Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel('admin');
    }

    private function as(string $role): User
    {
        $user = User::factory()->create(['role' => $role, 'status' => 'active']);
        $this->actingAs($user);

        return $user;
    }

    private function cat(string $name, ?Category $parent = null): Category
    {
        return Category::create(['name' => ['en' => $name], 'slug' => str($name)->slug()->toString(), 'parent_id' => $parent?->id]);
    }

    private function article(string $title, ?Category $cat = null, array $extra = []): Article
    {
        return Article::create(array_merge(['title' => ['en' => $title], 'slug' => str($title)->slug()->toString(), 'category_id' => $cat?->id], $extra));
    }

    public function test_an_article_has_a_permanent_code_and_titles_per_language(): void
    {
        $a = $this->article('First one');
        $this->assertSame('A-'.$a->id, $a->code);
        $a->setTranslation('title', 'ar', 'الأول')->save();
        $this->assertSame('First one', $a->fresh()->mainTitle());
    }

    public function test_one_article_sits_in_a_main_category_and_in_others(): void
    {
        $shows = $this->cat('Shows');
        $blogs = $this->cat('Blogs');
        $a = $this->article('Both places', $shows);
        $a->extraCategories()->attach($blogs->id);

        $this->assertSame(['Shows', 'Blogs'], $a->fresh()->allCategories()->map->mainName()->all());
        $this->assertSame(1, $shows->articleCount());
        $this->assertSame(1, $blogs->articleCount());
    }

    public function test_youtube_addresses_give_the_video_code(): void
    {
        $this->assertSame('dQw4w9WgXcQ', YouTube::id('https://www.youtube.com/watch?v=dQw4w9WgXcQ'));
        $this->assertSame('dQw4w9WgXcQ', YouTube::id('https://youtu.be/dQw4w9WgXcQ?t=3'));
        $this->assertSame('dQw4w9WgXcQ', YouTube::id('https://www.youtube.com/embed/dQw4w9WgXcQ'));
        $this->assertSame('dQw4w9WgXcQ', YouTube::id('https://www.youtube.com/shorts/dQw4w9WgXcQ'));
        $this->assertNull(YouTube::id('https://example.com/watch?v=dQw4w9WgXcQ'));
        $this->assertNull(YouTube::id('javascript:alert(1)'));
        $this->assertNull(YouTube::id(''));
    }

    public function test_the_list_shows_the_code_the_title_and_the_categories_with_their_codes(): void
    {
        $this->as('superuser');
        $shows = $this->cat('Shows');
        $a = $this->article('Listed article', $shows);

        Livewire::test(ListArticles::class)->assertOk()->assertSee($a->code)->assertSee('Listed article')->assertSee('Shows ('.$shows->code.')');
    }

    public function test_the_list_is_searched_by_title_search_word_and_code(): void
    {
        $this->as('superuser');
        $cat = $this->cat('Shows');
        $a = $this->article('Alpha show', $cat, ['search_words' => ['zebra word']]);
        $b = $this->article('Beta show', $cat);

        Livewire::test(ListArticles::class)->searchTable('zebra')->assertSee('Alpha show')->assertDontSee('Beta show');
        Livewire::test(ListArticles::class)->searchTable($b->code)->assertSee('Beta show')->assertDontSee('Alpha show');
    }

    public function test_a_new_article_is_saved_with_everything_and_gets_its_address(): void
    {
        $this->as('superuser');
        $shows = $this->cat('Shows');
        $blogs = $this->cat('Blogs');

        Livewire::test(CreateArticle::class)
            ->fillForm([
                'title' => ['en' => 'My First Article'],
                'category_id' => $shows->id,
                'extraCategories' => [$blogs->id],
                'short_detail' => ['en' => 'Short words.'],
                'long_detail' => ['en' => '<p>Long words.</p>'],
                'search_words' => ['one', 'two words'],
                'status' => 'draft',
                'videos' => [['url' => 'https://youtu.be/dQw4w9WgXcQ']],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $a = Article::first();
        $this->assertSame('my-first-article', $a->slug);
        $this->assertSame(['one', 'two words'], $a->search_words);
        $this->assertSame([$blogs->id], $a->extraCategories->pluck('id')->all());
        $this->assertSame('dQw4w9WgXcQ', $a->videos->first()->youtube_id);
    }

    public function test_a_title_and_a_main_category_are_required(): void
    {
        $this->as('superuser');
        Livewire::test(CreateArticle::class)->fillForm(['title' => ['en' => ''], 'category_id' => null])->call('create')->assertHasFormErrors(['title.en', 'category_id']);
    }

    public function test_a_video_address_must_be_a_youtube_video(): void
    {
        $this->as('superuser');
        $cat = $this->cat('Shows');
        Livewire::test(CreateArticle::class)
            ->fillForm(['title' => ['en' => 'Bad video'], 'category_id' => $cat->id, 'videos' => [['url' => 'https://example.com/clip']]])
            ->call('create')
            ->assertHasFormErrors();
        $this->assertSame(0, Article::count());
    }

    public function test_publishing_without_a_date_uses_now_and_a_future_date_hides_the_article(): void
    {
        $this->as('superuser');
        $cat = $this->cat('Shows');

        Livewire::test(CreateArticle::class)->fillForm(['title' => ['en' => 'Now one'], 'category_id' => $cat->id, 'status' => 'published'])->call('create');
        $now = Article::where('slug', 'now-one')->first();
        $this->assertNotNull($now->publish_date);
        $this->assertTrue(Article::published()->whereKey($now->id)->exists());

        $later = $this->article('Later one', $cat, ['status' => 'published', 'publish_date' => now()->addDay()]);
        $this->assertFalse(Article::published()->whereKey($later->id)->exists(), 'not yet visible');
        $draft = $this->article('Draft one', $cat);
        $this->assertFalse(Article::published()->whereKey($draft->id)->exists());
    }

    public function test_an_author_cannot_publish_but_a_moderator_can(): void
    {
        $cat = $this->cat('Shows');

        $this->as('author');
        Livewire::test(CreateArticle::class)->fillForm(['title' => ['en' => 'Author try'], 'category_id' => $cat->id, 'status' => 'draft'])->call('create');
        $a = Article::where('slug', 'author-try')->first();
        $this->assertSame('draft', $a->status);
        $this->assertSame(['draft', 'in_review'], array_keys(\App\Filament\Resources\Articles\ArticleResource::statusOptions()));

        $this->as('moderator');
        Livewire::test(EditArticle::class, ['record' => $a->getRouteKey()])->fillForm(['status' => 'published'])->call('save')->assertHasNoFormErrors();
        $this->assertSame('published', $a->fresh()->status);
        $this->assertArrayHasKey('published', \App\Filament\Resources\Articles\ArticleResource::statusOptions());
    }

    public function test_the_rules_stop_an_author_from_publishing_even_if_the_form_is_forged(): void
    {
        $this->as('author');
        $data = \App\Filament\Support\ArticleRules::apply(['title' => ['en' => 'Forged'], 'status' => 'published']);
        $this->assertSame('draft', $data['status']);
        $kept = \App\Filament\Support\ArticleRules::apply(['title' => ['en' => 'Forged'], 'status' => 'archived'], 'published');
        $this->assertSame('published', $kept['status'], 'a published article is not changed by an author');
    }

    public function test_clone_makes_a_draft_copy_with_the_same_other_categories(): void
    {
        $this->as('moderator');
        $shows = $this->cat('Shows');
        $blogs = $this->cat('Blogs');
        $a = $this->article('Original', $shows, ['status' => 'published']);
        $a->extraCategories()->attach($blogs->id);
        $a->videos()->create(['url' => 'https://youtu.be/dQw4w9WgXcQ']);

        Livewire::test(ListArticles::class)->callAction(TestAction::make('replicate')->table($a));

        $copy = Article::where('id', '!=', $a->id)->first();
        $this->assertSame('Original (copy)', $copy->mainTitle());
        $this->assertSame('draft', $copy->status);
        $this->assertSame([$blogs->id], $copy->extraCategories->pluck('id')->all());
        $this->assertSame(1, $copy->videos()->count());
        $this->assertNotSame($a->slug, $copy->slug);
    }

    public function test_assigning_articles_to_a_category_in_one_go(): void
    {
        $this->as('moderator');
        $shows = $this->cat('Shows');
        $target = $this->cat('Target');
        $one = $this->article('One', $shows);
        $two = $this->article('Two', $shows);

        Livewire::test(ListArticles::class)
            ->selectTableRecords([$one->id, $two->id])
            ->callAction(TestAction::make('assign')->table()->bulk(), data: ['category_id' => $target->id]);

        $this->assertSame(2, $target->articleCount());
    }

    public function test_a_category_with_articles_cannot_be_deleted(): void
    {
        $this->as('superuser');
        $cat = $this->cat('Full one');
        $this->article('Inside', $cat);

        Livewire::test(ListCategories::class)->callAction(TestAction::make('delete')->table($cat));

        $this->assertNotNull(Category::find($cat->id));
    }
}
