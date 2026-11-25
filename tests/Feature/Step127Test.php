<?php

namespace Tests\Feature;

use App\Filament\Pages\StoryMaker;
use App\Filament\Resources\Articles\Pages\ListArticles;
use App\Models\Article;
use App\Models\ArticleImage;
use App\Models\Category;
use App\Models\Setting;
use App\Models\Story;
use App\Models\StoryRun;
use App\Models\User;
use App\Support\Gallery;
use App\Support\StoryMaker\FromArticle;
use App\Support\StoryMaker\Prompt;
use App\Support\StoryMaker\Reader;
use App\Support\StoryMaker\Saver;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/** Step 127: "Make a story from this article": the button, the Story maker with the article in it, the link both ways, the pictures taken from the article, the gallery shows each once. */
class Step127Test extends TestCase
{
    use RefreshDatabase;

    private Category $cat;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::forget();
        Gallery::forget();
        Storage::fake('public');
        Filament::setCurrentPanel('admin');
        $master = Category::create(['name' => ['en' => 'Master'], 'slug' => 'master']);
        $this->cat = Category::create(['name' => ['en' => 'History place'], 'slug' => 'history-place', 'parent_id' => $master->id]);
    }

    protected function tearDown(): void
    {
        Setting::forget();
        Gallery::forget();
        parent::tearDown();
    }

    private function superuser(): User
    {
        $u = User::factory()->create(['role' => 'superuser', 'status' => 'active']);
        $this->actingAs($u);

        return $u;
    }

    private function png(int $shade = 120): string
    {
        $im = imagecreatetruecolor(160, 120);
        imagefill($im, 0, 0, imagecolorallocate($im, $shade, 90, 160));
        ob_start();
        imagepng($im);

        return (string) ob_get_clean();
    }

    /** An article with a main picture and $extra other pictures (files on the fake disk). */
    private function article(int $extra = 2, array $over = []): Article
    {
        Storage::disk('public')->put('articles/main.png', $this->png(10));
        $article = Article::create($over + [
            'title' => ['en' => 'The Hill Walk'], 'slug' => 'the-hill-walk', 'category_id' => $this->cat->id, 'status' => 'published', 'publish_date' => now()->subDay(),
            'short_detail' => ['en' => 'A walk to a hill.'], 'long_detail' => ['en' => '<h2>The start</h2><p>A man walked to the hill at dawn.</p><h2>The top</h2><p>He sat and looked at the town.</p>'],
            'main_image' => 'articles/main.png', 'main_image_alt' => ['en' => 'The hill'], 'source_line' => 'A real book, page 4',
        ]);
        for ($i = 1; $i <= $extra; $i++) {
            Storage::disk('public')->put("articles/extra-$i.png", $this->png(10 + $i * 40));
            ArticleImage::create(['article_id' => $article->id, 'image' => "articles/extra-$i.png", 'alt' => ['en' => "Extra $i"], 'sort_order' => $i]);
        }

        return $article;
    }

    private function answer(int $slides, string $title = 'The Hill Walk Story'): string
    {
        $out = "TYPE: story\nCATEGORY: History place\nTITLE: ".$title."\nSHORT: A short story about a walk.\nSTYLE: Soft drawing\nCHAPTER: The walk\n";
        for ($n = 1; $n < $slides; $n++) {
            $out .= "SLIDE\nHEADING: Part $n\nBODY: A man walked to the hill. He was tired but glad. The sun was low. He sat down and looked at the town below.\nPICTURE: A quiet hill at sunset\nALT: A hill at sunset\n";
        }
        $out .= "CHAPTER: Closing and final remarks\nSLIDE\nHEADING: What to remember\nBODY: A walk can calm the heart. Rest when you are tired. Look at what is around you.\nPICTURE: A calm town below a hill\nALT: A town below a hill\n";

        return $out."KEYWORDS: hill, walk\nMETA TITLE: The hill walk\nMETA DESCRIPTION: A short and simple story about a quiet walk to a hill at sunset, told in plain words for everyone.\nPLEASE CHECK:\n- nothing\nEND\n";
    }

    private function saveStory(Article $article, int $slides = 6, bool $autoPictures = true, string $title = 'The Hill Walk Story'): Story
    {
        $user = $this->superuser();
        $run = StoryRun::create(['kind' => 'story', 'user_id' => $user->id, 'topic' => 'x', 'category_id' => $this->cat->id, 'status' => 'checked',
            'options' => ['article_id' => $article->id, 'auto_pictures' => $autoPictures]]);
        $read = Reader::read($this->answer($slides, $title));
        $this->assertSame([], $read['errors'], json_encode($read['errors']));

        return Saver::save($read['story'], $this->cat->id, $run, $user);
    }

    // ---------- who, and the prompt ----------

    public function test_only_a_moderator_or_the_super_user_can_make_a_story_and_only_from_an_article_with_text(): void
    {
        $article = $this->article();
        $author = User::factory()->create(['role' => 'author', 'status' => 'active']);
        $this->assertFalse(FromArticle::canMake($article, $author));
        $this->assertFalse(FromArticle::canMake($article, null));
        $this->assertTrue(FromArticle::canMake($article, $this->superuser()));

        $empty = Article::create(['title' => ['en' => 'Nothing yet'], 'slug' => 'nothing-yet', 'category_id' => $this->cat->id, 'status' => 'draft']);
        $this->assertFalse(FromArticle::canMake($empty, $this->superuser()));
    }

    public function test_the_prompt_asks_for_a_short_story_made_only_from_the_article(): void
    {
        $article = $this->article();
        $text = Prompt::convert(['template' => 'from-article', 'own_text' => FromArticle::text($article), 'topic' => 'The Hill Walk', 'category_id' => $this->cat->id, 'open' => true, 'length' => 'short']);

        foreach (['SHORT story', 'about 5 to 8 slides', 'ADD NOTHING NEW', 'exactly as it is in the article', 'What to remember', 'A man walked to the hill at dawn.', '## The start', 'Closing and final remarks'] as $needle) {
            $this->assertStringContainsString($needle, $text, $needle);
        }
        $this->assertStringNotContainsString('{{', $text);
        $this->assertStringContainsString('TRANSLATION INTO A SHAPE', Prompt::convert(['own_text' => 'x']), 'the ordinary prompt is unchanged');
    }

    public function test_the_story_maker_opens_with_the_article_in_it(): void
    {
        $article = $this->article();
        $this->superuser();

        $c = Livewire::withQueryParams(['article' => $article->id])->test(StoryMaker::class);
        $c->assertSet('articleId', $article->id)->assertSet('topic', 'The Hill Walk')->assertSet('categoryId', $this->cat->id)->assertSet('openFlow', true)->assertSet('autoPictures', true);
        $this->assertStringContainsString('He sat and looked at the town.', $c->get('ownText'));
        $this->assertStringContainsString('SHORT story', $c->instance()->prompt());

        // The ordinary Story maker is not changed.
        Livewire::withQueryParams([])->test(StoryMaker::class)->assertSet('articleId', null)->assertSet('ownText', '');
    }

    // ---------- the saved story ----------

    public function test_the_saved_story_is_a_draft_linked_to_the_article_with_its_source_line(): void
    {
        $article = $this->article();
        $story = $this->saveStory($article);

        $this->assertSame('draft', $story->status);
        $this->assertSame($article->id, $story->article_id);
        $this->assertSame('A real book, page 4', $story->source_line);
        $this->assertSame($story->id, FromArticle::storyOf($article)->id);
        $this->assertTrue($story->ai_assisted);
    }

    public function test_the_pictures_come_from_the_article_in_order_spread_over_the_slides(): void
    {
        $article = $this->article(2);                // main + 2 others = 3 pictures
        $story = $this->saveStory($article, 6);      // 6 slides: pictures 0,0,1,1,2,2

        $this->assertSame('articles/main.png', ((array) $story->custom_data)['source_main_image']);
        $this->assertNotEmpty($story->main_image);
        $this->assertNotSame('articles/main.png', $story->main_image, 'the story has its own copy');
        Storage::disk('public')->assertExists($story->main_image);

        $slides = $story->chapters()->with('slides')->get()->flatMap(fn ($c) => $c->slides)->values();
        $this->assertCount(6, $slides);
        $this->assertSame(['articles/main.png', 'articles/main.png', 'articles/extra-1.png', 'articles/extra-1.png', 'articles/extra-2.png', 'articles/extra-2.png'],
            $slides->map(fn ($s) => ((array) $s->custom_data)['source_image'])->all());
        foreach ($slides as $s) {
            $this->assertNotEmpty($s->image);
            Storage::disk('public')->assertExists($s->image);
        }
        $this->assertSame('Extra 1', $slides[2]->getTranslation('image_alt', 'en'), 'the alt text of the article picture');
        $this->assertSame($slides[0]->image, $slides[1]->image, 'a picture shared by two slides is stored once');
    }

    public function test_a_picture_added_by_hand_is_never_replaced(): void
    {
        $article = $this->article(2);
        $story = $this->saveStory($article, 4);
        $slides = $story->chapters()->with('slides')->get()->flatMap(fn ($c) => $c->slides)->values();
        $slides[1]->update(['image' => 'stories/by-hand.webp', 'custom_data' => []]);
        $slides[2]->update(['image' => null, 'custom_data' => []]);

        FromArticle::applyPictures($story->fresh(), $article);

        $this->assertSame('stories/by-hand.webp', $slides[1]->fresh()->image);
        $this->assertNotEmpty($slides[2]->fresh()->image, 'an empty place is filled again');
    }

    public function test_the_pictures_can_be_switched_off_and_an_article_without_pictures_is_fine(): void
    {
        $story = $this->saveStory($this->article(2), 4, false);
        $this->assertEmpty($story->main_image);
        $this->assertSame(0, $story->slides()->whereNotNull('image')->count());

        Storage::fake('public');
        $bare = Article::create(['title' => ['en' => 'No pictures'], 'slug' => 'no-pictures', 'category_id' => $this->cat->id, 'status' => 'published', 'publish_date' => now()->subDay(),
            'long_detail' => ['en' => '<p>Some text.</p>']]);
        $this->assertSame([], FromArticle::pictures($bare));
        $this->assertSame(0, FromArticle::applyPictures($this->saveStory($bare, 3, true, 'A Story Without Pictures'), $bare));
    }

    // ---------- the gallery shows a picture once ----------

    public function test_the_gallery_shows_a_shared_picture_once_and_the_copy_when_the_article_is_not_shown(): void
    {
        $article = $this->article(2);
        $story = $this->saveStory($article, 6);
        $story->update(['status' => 'published', 'publish_date' => now()->subHour()]);
        Gallery::forget();

        $list = Gallery::pictures();
        $kinds = array_count_values(array_column($list, 'kind'));
        $this->assertSame(3, $kinds['article'] ?? 0, 'the three pictures of the article');
        $this->assertSame(0, $kinds['story'] ?? 0, 'the story copies are not listed again');

        // The article is a draft again: its pictures are not on the site, so the story's own pictures are listed.
        $article->update(['status' => 'draft']);
        Gallery::forget();
        $kinds = array_count_values(array_column(Gallery::pictures(), 'kind'));
        $this->assertSame(0, $kinds['article'] ?? 0);
        $this->assertGreaterThanOrEqual(3, $kinds['story'] ?? 0);
    }

    // ---------- the two pages point to each other ----------

    public function test_the_story_links_to_the_full_article_and_the_article_to_its_story(): void
    {
        $article = $this->article(1);
        $story = $this->saveStory($article, 4);

        // A draft story is not on the article page; the article shows nothing yet.
        $this->get(route('article.show', $article->slug))->assertOk()->assertDontSee('See this as a story');

        $story->update(['status' => 'published', 'publish_date' => now()->subHour()]);
        $this->get(route('article.show', $article->slug))->assertOk()->assertSee('See this as a story')->assertSee($story->url(), false);
        $this->get($story->url())->assertOk()->assertSee('Read the full article')->assertSee(route('article.show', $article->slug), false);

        // The article is not public: the story does not send people to it.
        $article->update(['status' => 'draft']);
        $this->get($story->url())->assertOk()->assertDontSee('Read the full article');
    }

    // ---------- the buttons ----------

    public function test_the_list_of_articles_has_the_button_and_then_a_link_to_the_story(): void
    {
        $article = $this->article(1);
        $this->superuser();

        Livewire::test(ListArticles::class)->assertTableActionVisible('make_story', $article)->assertTableActionHidden('open_story', $article);

        $story = $this->saveStory($article, 3);
        $this->superuser();
        Livewire::test(ListArticles::class)->assertTableActionHidden('make_story', $article)->assertTableActionVisible('open_story', $article);
        $this->assertNotNull($story->id);

        // An author does not see the button.
        $this->actingAs(User::factory()->create(['role' => 'author', 'status' => 'active']));
        $other = Article::create(['title' => ['en' => 'Another'], 'slug' => 'another', 'category_id' => $this->cat->id, 'status' => 'published', 'publish_date' => now()->subDay(), 'long_detail' => ['en' => '<p>Text.</p>']]);
        $this->assertFalse(FromArticle::canMake($other, auth()->user()));
    }
}
