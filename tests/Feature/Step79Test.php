<?php

namespace Tests\Feature;

use App\Filament\Resources\Comments\Pages\ListComments;
use App\Models\Article;
use App\Models\Category;
use App\Models\Comment;
use App\Models\Setting;
use App\Models\Story;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Step 79: the story page is one stage with nothing of the story outside it, and readers comment on a story ("Audience"). */
class Step79Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::forget();
    }

    private function story(bool $withSlides = true, string $slug = 'the-walk'): Story
    {
        $cat = Category::firstOrCreate(['slug' => 'stories'], ['name' => ['en' => 'Stories']]);
        $story = Story::create(['title' => ['en' => 'The Walk'], 'slug' => $slug, 'category_id' => $cat->id, 'status' => 'published', 'publish_date' => now()->subDay(),
            'short_detail' => ['en' => 'A man walks to a quiet hill.'], 'long_detail' => ['en' => '<p>The long text of the story.</p>']]);
        if ($withSlides) {
            $ch = $story->chapters()->create(['title' => ['en' => 'The opening'], 'sort_order' => 1]);
            $ch->slides()->create(['sort_order' => 1, 'heading' => ['en' => 'The hill'], 'body' => ['en' => 'A man walked to the hill.']]);
        }

        return $story->fresh();
    }

    private function member(): User
    {
        $u = User::factory()->create(['role' => 'member', 'status' => 'active', 'email_verified_at' => now()]);
        $this->actingAs($u);

        return $u;
    }

    // ---------- the page ----------

    public function test_a_story_with_slides_is_one_stage_with_nothing_of_the_story_outside_it(): void
    {
        $this->story();
        $html = $this->get('/stories/the-walk')->assertOk()->getContent();

        $this->assertStringContainsString('story-theatre', $html);
        $this->assertStringContainsString('story-theatre__frame', $html);
        $this->assertMatchesRegularExpression('/class="story story--(wide|boxed|embedded)[^"]*" style="--v-box/', $html, 'the stage keeps the full width chosen in the settings');
        $this->assertStringContainsString('story-meta', $html);
        $this->assertStringNotContainsString('topic__hook', $html, 'the short text is not repeated under the stage');
        $this->assertStringNotContainsString('article__body', $html, 'the long text is not written under the stage');
        $this->assertStringContainsString('The hill', $html, 'the words are in the slides');
    }

    public function test_a_story_without_slides_still_shows_its_short_and_long_text(): void
    {
        $this->story(false);
        $html = $this->get('/stories/the-walk')->assertOk()->getContent();

        $this->assertStringNotContainsString('story-theatre', $html);
        $this->assertStringContainsString('topic__hook', $html);
        $this->assertStringContainsString('The long text of the story.', $html);
    }

    public function test_the_page_has_the_audience_the_tools_and_only_three_related_stories(): void
    {
        $this->story();
        foreach (['b', 'c', 'd', 'e'] as $slug) {
            $this->story(false, 'other-'.$slug);
        }
        $html = $this->get('/stories/the-walk')->assertOk()->getContent();

        $this->assertStringContainsString('id="comments"', $html);
        $this->assertStringContainsString('Audience', $html);
        $this->assertSame(3, substr_count($html, 'class="scard"'), 'one slim row of three');
    }

    // ---------- the Audience ----------

    public function test_a_visitor_who_is_not_logged_in_is_asked_to_log_in_to_comment(): void
    {
        $this->story();
        $this->get('/stories/the-walk')->assertSee('Audience')->assertSee(route('login'), false);
    }

    public function test_a_member_comment_waits_and_only_the_author_sees_it_until_it_is_approved(): void
    {
        $story = $this->story();
        $me = $this->member();

        $this->post(route('comments.store'), ['story_id' => $story->id, 'body' => 'What a calm story.'])->assertRedirect($story->url().'#comments');

        $comment = Comment::first();
        $this->assertSame($story->id, $comment->story_id);
        $this->assertNull($comment->article_id);
        $this->assertSame('pending', $comment->status);

        $this->get('/stories/the-walk')->assertSee('What a calm story.');   // the author sees it, marked as waiting

        auth()->logout();
        $this->get('/stories/the-walk')->assertDontSee('What a calm story.');

        $comment->update(['status' => 'approved']);
        $this->get('/stories/the-walk')->assertSee('What a calm story.');
    }

    public function test_a_comment_from_staff_is_shown_at_once_and_a_reply_goes_under_an_approved_comment(): void
    {
        $story = $this->story();
        $first = Comment::create(['story_id' => $story->id, 'user_id' => User::factory()->create()->id, 'body' => 'First thoughts here.', 'status' => 'approved']);
        $this->actingAs(User::factory()->create(['role' => 'superuser', 'status' => 'active']));

        $this->post(route('comments.store'), ['story_id' => $story->id, 'parent_id' => $first->id, 'body' => 'Thank you for reading.'])->assertRedirect();

        $reply = Comment::where('parent_id', $first->id)->first();
        $this->assertNotNull($reply);
        $this->assertSame('approved', $reply->status);
        $this->assertSame($story->id, $reply->story_id);
        $this->get('/stories/the-walk')->assertSee('Thank you for reading.');
    }

    public function test_a_reply_cannot_go_under_a_comment_of_another_story_or_one_that_is_not_approved(): void
    {
        $one = $this->story(true, 'one');
        $two = $this->story(true, 'two');
        $this->member();
        $other = Comment::create(['story_id' => $two->id, 'user_id' => User::factory()->create()->id, 'body' => 'On the other story.', 'status' => 'approved']);
        $waiting = Comment::create(['story_id' => $one->id, 'user_id' => User::factory()->create()->id, 'body' => 'Still waiting.', 'status' => 'pending']);

        $this->post(route('comments.store'), ['story_id' => $one->id, 'parent_id' => $other->id, 'body' => 'A reply.'])->assertNotFound();
        $this->post(route('comments.store'), ['story_id' => $one->id, 'parent_id' => $waiting->id, 'body' => 'A reply.'])->assertNotFound();
    }

    public function test_a_comment_needs_an_article_or_a_story(): void
    {
        $this->member();
        $this->post(route('comments.store'), ['body' => 'Hello there.'])->assertSessionHasErrors();
        $this->assertSame(0, Comment::count());
    }

    public function test_comments_on_articles_still_work(): void
    {
        $article = Article::create(['title' => ['en' => 'Why we pray'], 'slug' => 'why-we-pray', 'status' => 'published', 'publish_date' => now()->subDay(), 'long_detail' => ['en' => '<p>x</p>']]);
        $this->member();

        $this->post(route('comments.store'), ['article_id' => $article->id, 'body' => 'A good article.'])->assertRedirect(route('article.show', $article->slug).'#comments');
        $this->assertSame($article->id, Comment::first()->article_id);
        $this->assertNull(Comment::first()->story_id);
    }

    public function test_the_moderation_screen_lists_story_comments_with_the_story_name(): void
    {
        $story = $this->story();
        $comment = Comment::create(['story_id' => $story->id, 'user_id' => User::factory()->create()->id, 'body' => 'Please check me.', 'status' => 'pending']);
        $this->actingAs(User::factory()->create(['role' => 'superuser', 'status' => 'active']));

        Livewire::test(ListComments::class)->assertCanSeeTableRecords([$comment])->assertSee('The Walk')->assertSee('Story');
    }

    public function test_the_audience_words_exist_in_every_language(): void
    {
        foreach (['en', 'ur', 'ar', 'fr', 'es', 'hi'] as $code) {
            $this->assertNotSame('', (string) (require base_path("lang/{$code}/site.php"))['story_audience'], $code);
        }
    }
}
