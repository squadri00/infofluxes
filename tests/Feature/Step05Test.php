<?php

namespace Tests\Feature;

use App\Filament\Resources\Articles\ArticleResource;
use App\Filament\Resources\Articles\Pages\EditArticle;
use App\Filament\Resources\Articles\Pages\ListArticles;
use App\Filament\Resources\Comments\Pages\ListComments;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\Article;
use App\Models\Category;
use App\Models\Comment;
use App\Models\Setting;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** Step 5: people and roles, the admin screens each may open, members, submitted articles and the discussion. */
class Step05Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::forget();
        Filament::setCurrentPanel('admin');
    }

    private function person(string $role, array $extra = []): User
    {
        return User::factory()->create(array_merge(['role' => $role, 'status' => 'active'], $extra));
    }

    private function cat(string $name = 'Shows'): Category
    {
        return Category::create(['name' => ['en' => $name], 'slug' => str($name)->slug()->toString()]);
    }

    private function article(string $title, Category $cat, ?User $author = null, array $extra = []): Article
    {
        return Article::create(array_merge([
            'title' => ['en' => $title], 'slug' => str($title)->slug()->toString(), 'category_id' => $cat->id,
            'author_id' => $author?->id, 'status' => 'published', 'publish_date' => now()->subDay(),
        ], $extra));
    }

    // ---------- which admin screens a person may open ----------

    public function test_the_usual_screens_of_each_role(): void
    {
        $super = $this->person('superuser');
        $mod = $this->person('moderator');
        $author = $this->person('author');
        $member = $this->person('member');

        foreach (['articles', 'categories', 'comments', 'users', 'settings'] as $screen) {
            $this->assertTrue($super->canUseScreen($screen), "super user: $screen");
        }
        $this->assertSame([true, true, true, false, false], array_map(fn ($s) => $mod->canUseScreen($s), ['articles', 'categories', 'comments', 'users', 'settings']));
        $this->assertSame([true, false, false, false, false], array_map(fn ($s) => $author->canUseScreen($s), ['articles', 'categories', 'comments', 'users', 'settings']));
        $this->assertFalse($member->canUseScreen('articles'));
    }

    public function test_ticks_replace_the_usual_screens_but_never_give_users_or_settings(): void
    {
        $author = $this->person('author', ['admin_screens' => ['categories', 'users', 'settings']]);

        $this->assertTrue($author->canUseScreen('categories'));
        $this->assertFalse($author->canUseScreen('articles'), 'ticks replace the usual screens');
        $this->assertFalse($author->canUseScreen('users'));
        $this->assertFalse($author->canUseScreen('settings'));
    }

    public function test_a_switched_off_person_can_use_no_screen(): void
    {
        $this->assertFalse($this->person('moderator', ['status' => 'switched_off'])->canUseScreen('articles'));
    }

    public function test_screens_are_enforced_on_the_admin_pages_themselves(): void
    {
        $this->actingAs($this->person('author'));
        $this->get('/admin/articles')->assertOk();
        $this->get('/admin/categories')->assertForbidden();
        $this->get('/admin/comments')->assertForbidden();
        $this->get('/admin/users')->assertForbidden();
        $this->get('/admin/site-settings')->assertForbidden();

        $this->actingAs($this->person('moderator'));
        $this->get('/admin/categories')->assertOk();
        $this->get('/admin/comments')->assertOk();
        $this->get('/admin/users')->assertForbidden();
        $this->get('/admin/categories/create')->assertOk();

        $this->actingAs($this->person('author', ['admin_screens' => ['comments']]));
        $this->get('/admin/articles/create')->assertForbidden();
        $this->get('/admin/comments')->assertOk();
    }

    // ---------- an author's own articles ----------

    public function test_an_author_sees_and_edits_only_their_own_articles(): void
    {
        $me = $this->person('author');
        $other = $this->person('author');
        $cat = $this->cat();
        $mine = $this->article('My piece', $cat, $me, ['status' => 'draft']);
        $theirs = $this->article('Their piece', $cat, $other, ['status' => 'draft']);

        $this->actingAs($me);
        Livewire::test(ListArticles::class)->assertSee('My piece')->assertDontSee('Their piece');
        $this->get('/admin/articles/'.$mine->id.'/edit')->assertOk();
        $this->get('/admin/articles/'.$theirs->id.'/edit')->assertNotFound();
    }

    public function test_a_moderator_sees_and_edits_every_article_and_can_publish(): void
    {
        $author = $this->person('author');
        $cat = $this->cat();
        $a = $this->article('Waiting piece', $cat, $author, ['status' => 'in_review', 'publish_date' => null]);

        $this->actingAs($this->person('moderator'));
        Livewire::test(ListArticles::class)->assertSee('Waiting piece');
        Livewire::test(EditArticle::class, ['record' => $a->getRouteKey()])->fillForm(['status' => 'published'])->call('save')->assertHasNoFormErrors();

        $this->assertSame('published', $a->fresh()->status);
        $this->assertSame($author->id, $a->fresh()->author_id, 'the author stays the author');
    }

    public function test_an_author_cannot_change_the_author_or_delete_a_published_article(): void
    {
        $me = $this->person('author');
        $other = $this->person('author');
        $cat = $this->cat();
        $draft = $this->article('Draft piece', $cat, $me, ['status' => 'draft']);
        $live = $this->article('Live piece', $cat, $me, ['status' => 'published']);

        $this->actingAs($me);
        Livewire::test(EditArticle::class, ['record' => $draft->getRouteKey()])
            ->fillForm(['author_id' => $other->id])->call('save');
        $this->assertSame($me->id, $draft->fresh()->author_id);

        $this->assertTrue(ArticleResource::canDelete($draft));
        $this->assertFalse(ArticleResource::canDelete($live));
    }

    // ---------- the People screen ----------

    public function test_only_the_super_user_manages_people_and_creates_staff_with_ticks(): void
    {
        $this->actingAs($this->person('superuser'));

        Livewire::test(CreateUser::class)
            ->fillForm(['name' => 'New Mod', 'email' => 'mod@example.org', 'password' => 'a-long-password-1', 'role' => 'moderator', 'status' => 'active', 'admin_screens' => ['comments']])
            ->call('create')
            ->assertHasNoFormErrors();

        $mod = User::where('email', 'mod@example.org')->first();
        $this->assertSame('moderator', $mod->role);
        $this->assertSame(['comments'], $mod->admin_screens);
        $this->assertNotSame('a-long-password-1', $mod->password, 'the password is kept scrambled');
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('a-long-password-1', $mod->password));
        $this->assertTrue($mod->canUseScreen('comments'));
        $this->assertFalse($mod->canUseScreen('articles'));

        $this->actingAs($mod);
        $this->get('/admin/users')->assertForbidden();
    }

    public function test_ticks_are_forgotten_for_a_member_and_a_short_password_is_refused(): void
    {
        $this->actingAs($this->person('superuser'));

        Livewire::test(CreateUser::class)
            ->fillForm(['name' => 'Short', 'email' => 's@example.org', 'password' => 'short', 'role' => 'member', 'status' => 'active'])
            ->call('create')->assertHasFormErrors(['password']);

        Livewire::test(CreateUser::class)
            ->fillForm(['name' => 'Member One', 'email' => 'm@example.org', 'password' => 'a-long-password-1', 'role' => 'member', 'status' => 'active', 'admin_screens' => ['articles']])
            ->call('create')->assertHasNoFormErrors();
        $this->assertNull(User::where('email', 'm@example.org')->first()->admin_screens);
    }

    public function test_the_super_user_cannot_lock_themselves_out_and_there_is_no_delete(): void
    {
        $super = $this->person('superuser');
        $this->actingAs($super);

        Livewire::test(EditUser::class, ['record' => $super->getRouteKey()])
            ->fillForm(['role' => 'member', 'status' => 'switched_off'])->call('save');

        $this->assertSame('superuser', $super->fresh()->role);
        $this->assertSame('active', $super->fresh()->status);
        $this->assertFalse(\App\Filament\Resources\Users\UserResource::canDelete($super));
    }

    public function test_a_person_can_be_switched_off_and_on(): void
    {
        $this->actingAs($this->person('superuser'));
        $member = $this->person('member');

        Livewire::test(ListUsers::class)->callAction(TestAction::make('toggle')->table($member));
        $this->assertSame('switched_off', $member->fresh()->status);

        Livewire::test(ListUsers::class)->callAction(TestAction::make('toggle')->table($member));
        $this->assertSame('active', $member->fresh()->status);
    }

    // ---------- log in, sign up, log out ----------

    public function test_a_visitor_can_sign_up_and_becomes_an_active_member(): void
    {
        $this->post('/register', ['name' => 'Sam Reader', 'email' => 'sam@example.org', 'password' => 'a-long-password-1', 'password_confirmation' => 'a-long-password-1'])
            ->assertRedirect(route('account'));

        $sam = User::where('email', 'sam@example.org')->first();
        $this->assertSame('member', $sam->role);
        $this->assertSame('active', $sam->status);
        $this->assertAuthenticatedAs($sam);
    }

    public function test_sign_up_checks_the_fields_and_stops_robots(): void
    {
        $this->post('/register', ['name' => 'X', 'email' => 'not-an-email', 'password' => 'short', 'password_confirmation' => 'other'])
            ->assertSessionHasErrors(['email', 'password']);

        $this->post('/register', ['name' => 'Robot', 'email' => 'robot@example.org', 'password' => 'a-long-password-1', 'password_confirmation' => 'a-long-password-1', 'website' => 'http://spam'])
            ->assertRedirect(route('home'));
        $this->assertNull(User::where('email', 'robot@example.org')->first(), 'the hidden box was filled: no account');

        $this->person('member', ['email' => 'taken@example.org']);
        $this->post('/register', ['name' => 'Twin', 'email' => 'taken@example.org', 'password' => 'a-long-password-1', 'password_confirmation' => 'a-long-password-1'])->assertSessionHasErrors('email');
    }

    public function test_sign_up_can_be_closed_in_the_settings(): void
    {
        Setting::put('signups_open', false);

        $this->get('/register')->assertNotFound();
        $this->post('/register', ['name' => 'Late', 'email' => 'late@example.org', 'password' => 'a-long-password-1', 'password_confirmation' => 'a-long-password-1'])->assertNotFound();
        $this->get('/')->assertDontSee('Sign up');
    }

    public function test_log_in_works_with_a_right_password_only_and_for_active_people_only(): void
    {
        $user = $this->person('member', ['email' => 'in@example.org', 'password' => 'a-long-password-1']);

        $this->post('/login', ['email' => 'in@example.org', 'password' => 'wrong-password-1'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->post('/login', ['email' => 'nobody@example.org', 'password' => 'a-long-password-1'])->assertSessionHasErrors('email');

        $user->update(['status' => 'switched_off']);
        $this->post('/login', ['email' => 'in@example.org', 'password' => 'a-long-password-1'])->assertSessionHasErrors('email');
        $this->assertGuest();

        $user->update(['status' => 'active']);
        $this->post('/login', ['email' => 'in@example.org', 'password' => 'a-long-password-1'])->assertRedirect(route('account'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_the_same_words_are_shown_for_a_wrong_email_and_a_wrong_password(): void
    {
        $this->person('member', ['email' => 'known@example.org', 'password' => 'a-long-password-1']);

        $a = $this->post('/login', ['email' => 'known@example.org', 'password' => 'nope-nope-nope'])->assertSessionHasErrors('email');
        $b = $this->post('/login', ['email' => 'unknown@example.org', 'password' => 'nope-nope-nope'])->assertSessionHasErrors('email');
        $this->assertSame(session('errors')->first('email'), __('site.login_failed'));
    }

    public function test_log_out_and_the_account_page_needs_a_login(): void
    {
        $this->get('/account')->assertRedirect(route('login'));

        $this->actingAs($this->person('member'));
        $this->get('/account')->assertOk()->assertSee('My account');
        $this->post('/logout')->assertRedirect(route('home'));
        $this->assertGuest();
    }

    public function test_the_header_shows_log_in_or_the_name_and_the_admin_link_for_staff(): void
    {
        $this->get('/')->assertSee('Log in')->assertSee('Sign up');

        $member = $this->person('member', ['name' => 'Mary Member']);
        $this->actingAs($member)->get('/')->assertSee('Mary Member')->assertSee('Log out')->assertDontSee('>Admin<', false);

        $this->actingAs($this->person('author', ['name' => 'Ann Author']))->get('/')->assertSee('Ann Author')->assertSee('>Admin<', false);
    }

    // ---------- sending in an article ----------

    public function test_a_member_sends_in_an_article_that_waits_for_a_moderator(): void
    {
        $cat = $this->cat();
        $member = $this->person('member');
        $this->actingAs($member);

        $this->post('/submit', [
            'title' => 'My Own Article', 'category_id' => $cat->id, 'short_detail' => 'A short line.',
            'long_detail' => "First paragraph.\n\nSecond <b>paragraph</b> <script>x</script>", 'source_line' => 'A book', 'search_words' => 'one, two words, one',
        ])->assertRedirect(route('account'));

        $a = Article::where('slug', 'my-own-article')->first();
        $this->assertSame('in_review', $a->status);
        $this->assertSame($member->id, $a->author_id);
        $this->assertSame(['one', 'two words'], $a->search_words);
        $this->assertStringContainsString('<p>First paragraph.</p><p>Second &lt;b&gt;paragraph&lt;/b&gt;', $a->long_detail);
        $this->assertStringNotContainsString('<script>', $a->long_detail);

        $this->actingAs($member)->get('/my-own-article')->assertNotFound();
        auth()->logout();
        $this->get('/my-own-article')->assertNotFound();
        $this->get('/')->assertDontSee('My Own Article');
    }

    public function test_a_member_cannot_choose_a_hidden_category_or_leave_out_the_title(): void
    {
        $hidden = Category::create(['name' => ['en' => 'Hidden'], 'slug' => 'hidden', 'is_visible' => false]);
        $this->actingAs($this->person('member'));

        $this->post('/submit', ['title' => 'T', 'category_id' => $hidden->id])->assertSessionHasErrors('category_id');
        $this->post('/submit', ['title' => '', 'category_id' => $this->cat()->id])->assertSessionHasErrors('title');
        $this->assertSame(0, Article::count());
    }

    public function test_a_member_edits_their_own_waiting_article_but_not_a_published_one_or_someone_elses(): void
    {
        $cat = $this->cat();
        $me = $this->person('member');
        $other = $this->person('member');
        $mine = $this->article('Mine', $cat, $me, ['status' => 'in_review']);
        $live = $this->article('Live', $cat, $me, ['status' => 'published']);
        $theirs = $this->article('Theirs', $cat, $other, ['status' => 'in_review']);

        $this->actingAs($me);
        $this->get('/submit/'.$mine->id)->assertOk()->assertSee('Mine');
        $this->get('/submit/'.$live->id)->assertForbidden();
        $this->get('/submit/'.$theirs->id)->assertForbidden();
        $this->post('/submit/'.$theirs->id, ['title' => 'Hacked', 'category_id' => $cat->id])->assertForbidden();

        $this->post('/submit/'.$mine->id, ['title' => 'Mine edited', 'category_id' => $cat->id])->assertRedirect(route('account'));
        $this->assertSame('Mine edited', $mine->fresh()->mainTitle());
        $this->assertSame('in_review', $mine->fresh()->status);
    }

    public function test_the_submit_pages_need_a_login(): void
    {
        $this->get('/submit')->assertRedirect(route('login'));
        $this->post('/submit', ['title' => 'x'])->assertRedirect(route('login'));
    }

    // ---------- the discussion ----------

    public function test_a_visitor_sees_a_login_link_and_cannot_post(): void
    {
        $a = $this->article('Talk here', $this->cat());

        $this->get('/talk-here')->assertSee('Log in to join the discussion');
        $this->post('/comments', ['article_id' => $a->id, 'body' => 'Hello there'])->assertRedirect(route('login'));
        $this->assertSame(0, Comment::count());
    }

    public function test_a_members_comment_waits_and_only_they_see_it_until_approved(): void
    {
        $a = $this->article('Talk here', $this->cat());
        $member = $this->person('member');

        $this->actingAs($member)->post('/comments', ['article_id' => $a->id, 'body' => 'My first thought'])->assertRedirect();
        $c = Comment::first();
        $this->assertSame('pending', $c->status);

        $this->actingAs($member)->get('/talk-here')->assertSee('My first thought')->assertSee('Waiting for approval');
        auth()->logout();
        $this->get('/talk-here')->assertDontSee('My first thought');
        $this->actingAs($this->person('member'))->get('/talk-here')->assertDontSee('My first thought');

        $c->update(['status' => 'approved']);
        auth()->logout();
        $this->get('/talk-here')->assertSee('My first thought')->assertDontSee('Waiting for approval');
    }

    public function test_staff_comments_and_replies_are_shown_at_once_with_a_staff_badge(): void
    {
        $a = $this->article('Talk here', $this->cat());
        $this->actingAs($this->person('moderator', ['name' => 'Mod Mia']))->post('/comments', ['article_id' => $a->id, 'body' => 'From the team']);

        $this->assertSame('approved', Comment::first()->status);
        auth()->logout();
        $this->get('/talk-here')->assertSee('From the team')->assertSee('Mod Mia')->assertSee('Staff');
    }

    public function test_a_reply_goes_under_a_visible_comment_of_the_same_article_only(): void
    {
        $cat = $this->cat();
        $a = $this->article('Talk here', $cat);
        $b = $this->article('Other talk', $cat);
        $member = $this->person('member');
        $root = Comment::create(['article_id' => $a->id, 'user_id' => $member->id, 'body' => 'Root comment', 'status' => 'approved']);
        $waiting = Comment::create(['article_id' => $a->id, 'user_id' => $member->id, 'body' => 'Waiting root', 'status' => 'pending']);

        $this->actingAs($member);
        $this->post('/comments', ['article_id' => $a->id, 'parent_id' => $root->id, 'body' => 'A reply'])->assertRedirect();
        $this->assertSame($root->id, Comment::where('body', 'A reply')->first()->parent_id);

        $this->post('/comments', ['article_id' => $b->id, 'parent_id' => $root->id, 'body' => 'Wrong article'])->assertNotFound();
        $this->post('/comments', ['article_id' => $a->id, 'parent_id' => $waiting->id, 'body' => 'Under a waiting one'])->assertNotFound();
    }

    public function test_a_comment_must_have_words_and_not_be_too_long_and_only_on_published_articles(): void
    {
        $cat = $this->cat();
        $a = $this->article('Talk here', $cat);
        $draft = $this->article('Draft talk', $cat, null, ['status' => 'draft']);
        $this->actingAs($this->person('member'));

        $this->post('/comments', ['article_id' => $a->id, 'body' => ''])->assertSessionHasErrors('body');
        $this->post('/comments', ['article_id' => $a->id, 'body' => str_repeat('x', 2001)])->assertSessionHasErrors('body');
        $this->post('/comments', ['article_id' => $draft->id, 'body' => 'Hello there'])->assertNotFound();
        $this->assertSame(0, Comment::count());
    }

    public function test_comment_text_is_shown_safely(): void
    {
        $a = $this->article('Talk here', $this->cat());
        $member = $this->person('member');
        Comment::create(['article_id' => $a->id, 'user_id' => $member->id, 'body' => "<script>alert(1)</script>\nSecond line", 'status' => 'approved']);

        $html = $this->get('/talk-here')->getContent();
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        $this->assertStringContainsString('<br />', $html);
    }

    public function test_comments_can_be_closed_in_the_settings(): void
    {
        $a = $this->article('Talk here', $this->cat());
        Setting::put('comments_open', false);

        $this->actingAs($this->person('member'))->post('/comments', ['article_id' => $a->id, 'body' => 'Too late'])->assertForbidden();
        $this->get('/talk-here')->assertSee('Comments are closed');
    }

    public function test_a_switched_off_person_cannot_comment(): void
    {
        $a = $this->article('Talk here', $this->cat());
        $user = $this->person('member');
        $this->actingAs($user);
        $user->update(['status' => 'switched_off']);

        $this->post('/comments', ['article_id' => $a->id, 'body' => 'Still here'])->assertForbidden();
    }

    // ---------- the Comments screen ----------

    public function test_a_moderator_approves_hides_and_replies_to_comments(): void
    {
        $a = $this->article('Talk here', $this->cat());
        $member = $this->person('member');
        $c = Comment::create(['article_id' => $a->id, 'user_id' => $member->id, 'body' => 'Please check', 'status' => 'pending']);
        $mod = $this->person('moderator');
        $this->actingAs($mod);

        Livewire::test(ListComments::class)->assertSee('Please check');

        Livewire::test(ListComments::class)->callAction(TestAction::make('approve')->table($c));
        $this->assertSame('approved', $c->fresh()->status);

        Livewire::test(ListComments::class)->filterTable('status', 'approved')->callAction(TestAction::make('hide')->table($c));
        $this->assertSame('hidden', $c->fresh()->status);

        Livewire::test(ListComments::class)->filterTable('status', 'hidden')->callAction(TestAction::make('reply')->table($c), data: ['body' => 'Thanks for writing']);
        $reply = Comment::where('body', 'Thanks for writing')->first();
        $this->assertSame($c->id, $reply->parent_id);
        $this->assertSame($mod->id, $reply->user_id);
        $this->assertSame('approved', $reply->status);
        $this->assertSame('hidden', $c->fresh()->status, 'a reply does not un-hide a hidden comment');

        $waiting = Comment::create(['article_id' => $a->id, 'user_id' => $member->id, 'body' => 'Waiting one', 'status' => 'pending']);
        Livewire::test(ListComments::class)->callAction(TestAction::make('reply')->table($waiting), data: ['body' => 'Answer to waiting']);
        $this->assertSame('approved', $waiting->fresh()->status, 'replying to a waiting comment shows it');
    }

    public function test_a_reply_to_a_reply_stays_under_the_first_comment(): void
    {
        $a = $this->article('Talk here', $this->cat());
        $root = Comment::create(['article_id' => $a->id, 'user_id' => $this->person('member')->id, 'body' => 'Root', 'status' => 'approved']);
        $first = Comment::create(['article_id' => $a->id, 'user_id' => $this->person('member')->id, 'parent_id' => $root->id, 'body' => 'First reply', 'status' => 'approved']);
        $this->actingAs($this->person('moderator'));

        Livewire::test(ListComments::class)->filterTable('status', 'approved')->callAction(TestAction::make('reply')->table($first), data: ['body' => 'Deep reply']);

        $this->assertSame($root->id, Comment::where('body', 'Deep reply')->first()->parent_id);
    }

    public function test_the_comments_screen_is_closed_to_people_without_it(): void
    {
        $this->actingAs($this->person('author'));
        $this->get('/admin/comments')->assertForbidden();
    }
}
