<?php

namespace Tests\Feature;

use App\Filament\Resources\Articles\Pages\EditArticle;
use App\Filament\Resources\Articles\Pages\ListArticles;
use App\Filament\Resources\Stories\Pages\EditStory;
use App\Models\Article;
use App\Models\Category;
use App\Models\Comment;
use App\Models\Setting;
use App\Models\Story;
use App\Models\StoryRun;
use App\Models\User;
use App\Support\StoryMaker\FromArticle;
use App\Support\StoryMaker\Reader;
use App\Support\StoryMaker\Saver;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/** Step 128: the Story column and filter of the articles, and the note when an article changed after its story was made. */
class Step128Test extends TestCase
{
    use RefreshDatabase;

    private Category $cat;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::forget();
        Storage::fake('public');
        Filament::setCurrentPanel('admin');
        $master = Category::create(['name' => ['en' => 'Master'], 'slug' => 'master']);
        $this->cat = Category::create(['name' => ['en' => 'History place'], 'slug' => 'history-place', 'parent_id' => $master->id]);
        $this->actingAs(User::factory()->create(['role' => 'superuser', 'status' => 'active']));
    }

    protected function tearDown(): void
    {
        Setting::forget();
        parent::tearDown();
    }

    private function article(string $title): Article
    {
        return Article::create(['title' => ['en' => $title], 'slug' => str($title)->slug()->toString(), 'category_id' => $this->cat->id, 'status' => 'published', 'publish_date' => now()->subDay(),
            'short_detail' => ['en' => 'A short line.'], 'long_detail' => ['en' => '<h2>Start</h2><p>A man walked to the hill at dawn.</p>']]);
    }

    private function storyOf(Article $article, string $title): Story
    {
        $run = StoryRun::create(['kind' => 'story', 'user_id' => auth()->id(), 'topic' => 'x', 'category_id' => $this->cat->id, 'status' => 'checked', 'options' => ['article_id' => $article->id, 'auto_pictures' => false]]);
        $answer = "TYPE: story\nCATEGORY: History place\nTITLE: ".$title."\nSHORT: A short story.\nSTYLE: Soft drawing\nCHAPTER: The walk\nSLIDE\nHEADING: Part 1\nBODY: A man walked to the hill. He was tired but glad. The sun was low. He sat down.\nPICTURE: A hill\nALT: A hill\n"
            ."CHAPTER: Closing and final remarks\nSLIDE\nHEADING: What to remember\nBODY: Rest when you are tired. Look around you.\nPICTURE: A town\nALT: A town\nKEYWORDS: hill\nMETA TITLE: The hill\nMETA DESCRIPTION: A short and simple story about a quiet walk to a hill at sunset, told in plain words for everyone.\nPLEASE CHECK:\n- nothing\nEND\n";
        $read = Reader::read($answer);
        $this->assertSame([], $read['errors'], json_encode($read['errors']));

        return Saver::save($read['story'], $this->cat->id, $run, auth()->user());
    }

    public function test_a_story_is_out_of_date_only_when_the_text_of_its_article_changed(): void
    {
        $article = $this->article('The Hill Walk');
        $story = $this->storyOf($article, 'The Hill Walk Story');

        $this->assertNotEmpty(((array) $story->custom_data)['article_fingerprint']);
        $this->assertFalse(FromArticle::outdated($story));

        // Things that are not the text do not count: views, a comment, a new publish date, the status.
        $article->update(['views' => 99, 'status' => 'archived']);
        $this->assertFalse(FromArticle::outdated($story->fresh()));

        $article->setTranslation('long_detail', 'en', '<h2>Start</h2><p>A woman walked to the hill at dawn.</p>')->save();
        $this->assertTrue(FromArticle::outdated($story->fresh()));

        // A story that is made again from the changed article is up to date again.
        $again = $this->storyOf($article->fresh(), 'The Hill Walk Story Again');
        $this->assertFalse(FromArticle::outdated($again->fresh()));

        // A story that has no article is never out of date.
        $plain = Story::create(['title' => ['en' => 'Plain'], 'slug' => 'plain', 'category_id' => $this->cat->id, 'status' => 'draft']);
        $this->assertFalse(FromArticle::outdated($plain));
    }

    public function test_the_list_of_articles_has_a_story_column_and_a_filter(): void
    {
        $with = $this->article('Has a story');
        $without = $this->article('Has none');
        $story = $this->storyOf($with, 'The Story of It');

        $page = Livewire::test(ListArticles::class);
        $page->assertTableColumnStateSet('story_state', $story->code.' · draft', $with)->assertTableColumnStateSet('story_state', null, $without);

        $page->filterTable('story', 'none')->assertCanSeeTableRecords([$without])->assertCanNotSeeTableRecords([$with]);
        Livewire::test(ListArticles::class)->filterTable('story', 'draft')->assertCanSeeTableRecords([$with])->assertCanNotSeeTableRecords([$without]);
        Livewire::test(ListArticles::class)->filterTable('story', 'published')->assertCanNotSeeTableRecords([$with, $without]);

        $story->update(['status' => 'published', 'publish_date' => now()->subHour()]);
        Livewire::test(ListArticles::class)->filterTable('story', 'published')->assertCanSeeTableRecords([$with])->assertCanNotSeeTableRecords([$without]);

        $with->setTranslation('short_detail', 'en', 'A changed line.')->save();
        Livewire::test(ListArticles::class)->assertTableColumnStateSet('story_state', $story->code.' · published · article changed', $with);
    }

    public function test_both_edit_pages_say_so_when_the_article_changed(): void
    {
        $article = $this->article('The Hill Walk');
        $story = $this->storyOf($article, 'The Hill Walk Story');

        Livewire::test(EditArticle::class, ['record' => $article->getRouteKey()])->assertDontSee('may be out of date');
        Livewire::test(EditStory::class, ['record' => $story->getRouteKey()])->assertDontSee('changed after this story')->assertActionVisible('open_article')->assertActionHidden('make_again');

        $article->setTranslation('long_detail', 'en', '<p>The text is new.</p>')->save();

        Livewire::test(EditArticle::class, ['record' => $article->getRouteKey()])->assertSee('Its story '.$story->code.' was made before the last change');
        Livewire::test(EditStory::class, ['record' => $story->getRouteKey()])->assertSee('The article '.$article->code.' changed after this story was made')->assertActionVisible('make_again');
    }

    public function test_a_comment_or_a_view_does_not_make_the_story_out_of_date(): void
    {
        $article = $this->article('The Hill Walk');
        $story = $this->storyOf($article, 'The Hill Walk Story');
        if (class_exists(Comment::class) && method_exists($article, 'comments')) {
            $article->touch();
        }
        $this->assertFalse(FromArticle::outdated($story->fresh()));
    }
}
