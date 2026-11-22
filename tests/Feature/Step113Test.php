<?php

namespace Tests\Feature;

use App\Mail\MemberDigest;
use App\Models\Article;
use App\Models\Category;
use App\Models\Comment;
use App\Models\Follow;
use App\Models\MemberNotification;
use App\Models\Person;
use App\Models\Setting;
use App\Models\Story;
use App\Models\User;
use App\Support\Notify;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/** Step 113: notices for members (the bell, the Notifications page) and the weekly news email. */
class Step113Test extends TestCase
{
    use RefreshDatabase;

    private Category $master;
    private Category $leaf;

    protected function setUp(): void
    {
        parent::setUp();
        \App\Models\Setting::forget();   // a switch saved by an earlier test must not leak into this one
        $this->master = Category::create(['name' => ['en' => 'History'], 'slug' => 'history']);
        $this->leaf = Category::create(['name' => ['en' => 'Conquests'], 'slug' => 'conquests', 'parent_id' => $this->master->id]);
    }

    protected function tearDown(): void
    {
        \App\Models\Setting::forget();
        parent::tearDown();
    }

    private function member(string $role = 'member'): User
    {
        return User::factory()->create(['role' => $role, 'status' => 'active']);
    }

    private function article(array $extra = []): Article
    {
        return Article::create(array_merge(['title' => ['en' => 'An article'], 'slug' => 'an-article-'.uniqid(), 'category_id' => $this->leaf->id, 'status' => 'draft',
            'short_detail' => ['en' => 'Short.'], 'long_detail' => ['en' => '<p>Text.</p>']], $extra));
    }

    private function types(User $user): array
    {
        return MemberNotification::where('user_id', $user->id)->orderBy('id')->pluck('type')->all();
    }

    // ---------------- comments ----------------

    public function test_a_comment_approved_or_hidden_tells_its_writer_and_a_reply_tells_the_first_writer(): void
    {
        $article = $this->article(['status' => 'published', 'publish_date' => now()->subDay()]);
        $ann = $this->member();
        $bob = $this->member();
        $first = Comment::create(['article_id' => $article->id, 'user_id' => $ann->id, 'body' => 'First!', 'status' => 'pending']);
        $second = Comment::create(['article_id' => $article->id, 'user_id' => $bob->id, 'body' => 'Not nice', 'status' => 'pending']);

        $first->update(['status' => 'approved']);
        $second->update(['status' => 'hidden']);
        $this->assertSame(['comment_approved'], $this->types($ann));
        $this->assertSame(['comment_hidden'], $this->types($bob));

        $reply = Comment::create(['article_id' => $article->id, 'user_id' => $bob->id, 'parent_id' => $first->id, 'body' => 'I agree', 'status' => 'pending']);
        $reply->update(['status' => 'approved']);
        $this->assertSame(['comment_approved', 'comment_reply'], $this->types($ann));
        $this->assertSame(['comment_hidden', 'comment_approved'], $this->types($bob));
        $this->assertStringContainsString('#comments', MemberNotification::where('type', 'comment_reply')->first()->url);
    }

    public function test_a_staff_reply_that_is_approved_at_once_tells_the_first_writer_and_a_reply_to_oneself_does_not(): void
    {
        $article = $this->article(['status' => 'published', 'publish_date' => now()->subDay()]);
        $ann = $this->member();
        $staff = $this->member('moderator');
        $first = Comment::create(['article_id' => $article->id, 'user_id' => $ann->id, 'body' => 'Question', 'status' => 'approved']);

        Comment::create(['article_id' => $article->id, 'user_id' => $staff->id, 'parent_id' => $first->id, 'body' => 'Answer', 'status' => 'approved']);
        Comment::create(['article_id' => $article->id, 'user_id' => $ann->id, 'parent_id' => $first->id, 'body' => 'Thanks', 'status' => 'approved']);

        $this->assertSame(['comment_reply'], $this->types($ann));
    }

    // ---------------- articles the member sent in ----------------

    public function test_a_sent_in_article_that_is_published_or_sent_back_tells_its_author(): void
    {
        $author = $this->member();
        $published = $this->article(['author_id' => $author->id, 'status' => 'in_review']);
        $back = $this->article(['author_id' => $author->id, 'status' => 'in_review']);

        $published->update(['status' => 'published', 'publish_date' => now()->subMinute()]);
        $back->update(['status' => 'draft']);

        $this->assertSame(['article_published', 'article_sent_back'], $this->types($author));
        $this->assertStringContainsString('/submit/'.$back->id, MemberNotification::where('type', 'article_sent_back')->first()->url);
    }

    public function test_staff_authors_are_not_told_about_their_own_articles(): void
    {
        $staff = $this->member('moderator');
        $this->article(['author_id' => $staff->id, 'status' => 'in_review'])->update(['status' => 'published', 'publish_date' => now()->subMinute()]);

        $this->assertSame([], $this->types($staff));
    }

    // ---------------- news from what a member follows ----------------

    public function test_news_goes_to_the_followers_of_the_category_above_the_person_and_the_word(): void
    {
        $byCategory = $this->member();
        $byPerson = $this->member();
        $byWord = $this->member();
        $nobody = $this->member();
        $person = Person::create(['name' => 'Writer One']);
        Follow::create(['user_id' => $byCategory->id, 'kind' => 'category', 'target_id' => $this->master->id]);   // a category ABOVE the article's own
        Follow::create(['user_id' => $byPerson->id, 'kind' => 'person', 'target_id' => $person->id]);
        Follow::create(['user_id' => $byWord->id, 'kind' => 'keyword', 'keyword' => 'ibn battuta']);

        $other = Category::create(['name' => ['en' => 'Science'], 'slug' => 'science']);
        $this->article(['category_id' => $other->id, 'contributor_id' => $person->id, 'search_words' => ['Ibn Battuta'], 'status' => 'published', 'publish_date' => now()->subMinute()]);

        $this->assertSame([], $this->types($byCategory), 'the category above is not the category of this article');
        $this->assertSame(['new_work'], $this->types($byPerson));
        $this->assertSame(['new_work'], $this->types($byWord));
        $this->assertSame([], $this->types($nobody));

        $this->article(['title' => ['en' => 'Second'], 'status' => 'published', 'publish_date' => now()->subMinute()]);
        $this->assertSame(['new_work'], $this->types($byCategory), 'a new article below the followed category');
    }

    public function test_drafts_future_dates_and_the_author_make_no_news_and_news_is_told_once(): void
    {
        $follower = $this->member();
        $author = $this->member();
        Follow::create(['user_id' => $follower->id, 'kind' => 'category', 'target_id' => $this->master->id]);
        Follow::create(['user_id' => $author->id, 'kind' => 'category', 'target_id' => $this->master->id]);

        $this->article(['status' => 'draft']);
        $this->article(['status' => 'published', 'publish_date' => now()->addDay()]);
        $this->assertSame([], $this->types($follower));

        $article = $this->article(['status' => 'in_review', 'author_id' => $author->id]);
        $article->update(['status' => 'published', 'publish_date' => now()->subMinute()]);
        $this->assertSame(['new_work'], $this->types($follower));
        $this->assertNotContains('new_work', $this->types($author), 'the author is not told about their own work');

        $article->update(['status' => 'archived']);
        $article->update(['status' => 'published']);
        $this->assertCount(1, MemberNotification::where('user_id', $follower->id)->get(), 'told once');
    }

    public function test_a_new_story_is_news_too(): void
    {
        $follower = $this->member();
        Follow::create(['user_id' => $follower->id, 'kind' => 'category', 'target_id' => $this->master->id]);

        Story::create(['title' => ['en' => 'A story'], 'slug' => 'a-story', 'category_id' => $this->leaf->id, 'status' => 'published', 'publish_date' => now()->subMinute()]);

        $this->assertSame(['new_work'], $this->types($follower));
        $this->assertStringContainsString('/stories/a-story', MemberNotification::first()->url);
    }

    public function test_the_notifications_switch_stops_new_notices(): void
    {
        $follower = $this->member();
        Follow::create(['user_id' => $follower->id, 'kind' => 'category', 'target_id' => $this->master->id]);
        Setting::put('ctl_notifications', '0');

        $this->article(['status' => 'published', 'publish_date' => now()->subMinute()]);

        $this->assertSame([], $this->types($follower));
        $this->actingAs($follower);
        $this->get(route('account.notifications'))->assertNotFound();
    }

    // ---------------- the bell and the page ----------------

    public function test_the_bell_counts_unread_the_list_opens_a_notice_and_marks_it_read(): void
    {
        $me = $this->member();
        $a = Notify::to($me, 'new_work', 'New: First', '/first');
        Notify::to($me, 'new_work', 'New: Second', '/second');
        $this->actingAs($me);

        $this->get(route('home'))->assertOk()->assertSee('data-bell', false)->assertSee('bell__count', false);
        $this->get(route('account.notifications'))->assertOk()->assertSee('New: First')->assertSee('Mark all as read');

        $this->get(route('account.notification', $a->id))->assertRedirect('/first');
        $this->assertNotNull($a->fresh()->read_at);
        $this->assertSame(1, Notify::unread($me));

        $this->post(route('account.notifications.read'))->assertRedirect();
        $this->assertSame(0, Notify::unread($me));
    }

    public function test_nobody_can_open_the_notice_of_somebody_else(): void
    {
        $ann = $this->member();
        $note = Notify::to($ann, 'new_work', 'Private', '/x');
        $this->actingAs($this->member());

        $this->get(route('account.notification', $note->id))->assertNotFound();
    }

    // ---------------- the weekly email ----------------

    public function test_the_weekly_email_goes_to_followers_with_news_once_and_marks_it_sent(): void
    {
        Mail::fake();
        $on = $this->member();
        $off = $this->member();
        $off->update(['custom_data' => ['digest' => false]]);
        $unverified = User::factory()->unverified()->create(['role' => 'member', 'status' => 'active']);
        foreach ([$on, $off, $unverified] as $u) {
            Notify::to($u, 'new_work', 'New: A thing', '/a-thing');
        }
        Notify::to($on, 'comment_approved', 'Your comment was approved', '/c');   // only news is emailed

        $this->artisan('members:digest')->assertSuccessful();

        Mail::assertSent(MemberDigest::class, 1);
        Mail::assertSent(MemberDigest::class, fn (MemberDigest $m) => $m->hasTo($on->email) && $m->items->count() === 1);
        $this->assertNotNull(MemberNotification::where('user_id', $on->id)->where('type', 'new_work')->first()->emailed_at);

        $this->artisan('members:digest')->assertSuccessful();
        Mail::assertSent(MemberDigest::class, 1);
    }

    public function test_a_dry_run_sends_nothing_and_the_email_has_the_links(): void
    {
        Mail::fake();
        $me = $this->member();
        Notify::to($me, 'new_work', 'New: A thing', '/a-thing');

        $this->artisan('members:digest', ['--dry' => true])->assertSuccessful();
        Mail::assertNothingSent();

        $html = (new MemberDigest($me, MemberNotification::all()))->render();
        $this->assertStringContainsString('New: A thing', $html);
        $this->assertStringContainsString('digest/off/'.$me->id, $html);
        $this->assertStringContainsString(route('account.following'), $html);
    }

    public function test_the_signed_link_in_the_email_switches_the_email_off(): void
    {
        $me = $this->member();

        $this->get(route('account.digest.off', $me->id))->assertForbidden();
        $this->get(URL::signedRoute('account.digest.off', ['user' => $me->id]))->assertRedirect(route('home'));

        $this->assertFalse($me->fresh()->custom_data['digest']);
    }

    public function test_the_notifications_and_follows_are_in_the_data_download_and_go_with_the_account(): void
    {
        $me = $this->member();
        Notify::to($me, 'new_work', 'New: Mine', '/m');
        Follow::create(['user_id' => $me->id, 'kind' => 'keyword', 'keyword' => 'hijra']);
        $this->actingAs($me);

        $json = json_decode($this->get(route('account.data'))->getContent(), true);
        $this->assertSame('New: Mine', $json['notifications'][0]['text']);
        $this->assertSame('hijra', $json['following'][0]['what']);

        $this->post(route('account.delete'), ['delete_password' => 'password']);
        $this->assertSame(0, MemberNotification::where('user_id', $me->id)->count());
        $this->assertSame(0, Follow::where('user_id', $me->id)->count());
    }
}
