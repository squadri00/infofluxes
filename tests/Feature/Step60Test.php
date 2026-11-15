<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Category;
use App\Models\Comment;
use App\Models\NewsletterSubscriber;
use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Step 60: profile management: profile, password, email, linked sign-ins, my data, delete my account, forgotten password. */
class Step60Test extends TestCase
{
    use RefreshDatabase;

    private function member(array $extra = []): User
    {
        $u = User::factory()->create(array_merge(['role' => 'member', 'status' => 'active', 'password' => 'old-password-123'], $extra));
        $this->actingAs($u);

        return $u;
    }

    public function test_every_account_page_needs_a_login_and_shows_the_menu(): void
    {
        foreach (['/account', '/account/profile', '/account/security', '/account/privacy'] as $url) {
            $this->get($url)->assertRedirect('/login');
        }
        $this->member();
        foreach (['/account', '/account/profile', '/account/security', '/account/privacy'] as $url) {
            $this->get($url)->assertOk()->assertSee('Sign-in and security')->assertSee('Privacy and my data');
        }
    }

    public function test_the_profile_is_saved_without_html_and_the_picture_can_be_added_and_removed(): void
    {
        Storage::fake('public');
        $u = $this->member();

        $this->post('/account/profile', ['name' => '<b>New Name</b>', 'bio' => 'Hello <script>x</script> there', 'photo' => UploadedFile::fake()->image('me.jpg', 300, 300)])->assertRedirect('/account/profile');
        $u->refresh();
        $this->assertSame('New Name', $u->name);
        $this->assertSame('Hello x there', $u->bio);
        $this->assertNotNull($u->photo);
        Storage::disk('public')->assertExists($u->photo);
        $old = $u->photo;

        $this->post('/account/profile', ['name' => 'New Name', 'remove_photo' => 1])->assertRedirect();
        $this->assertNull($u->fresh()->photo);
        Storage::disk('public')->assertMissing($old);
    }

    public function test_a_file_that_is_not_a_picture_is_refused(): void
    {
        Storage::fake('public');
        $u = $this->member();
        $this->post('/account/profile', ['name' => 'X', 'photo' => UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')])->assertSessionHasErrors('photo');
        $this->assertNull($u->fresh()->photo);
    }

    public function test_the_password_needs_the_old_one_and_must_be_new_and_long(): void
    {
        $u = $this->member();

        $this->post('/account/password', ['current_password' => 'wrong', 'password' => 'brand-new-pass-1', 'password_confirmation' => 'brand-new-pass-1'])->assertSessionHasErrors('current_password');
        $this->post('/account/password', ['current_password' => 'old-password-123', 'password' => 'short', 'password_confirmation' => 'short'])->assertSessionHasErrors('password');
        $this->post('/account/password', ['current_password' => 'old-password-123', 'password' => 'old-password-123', 'password_confirmation' => 'old-password-123'])->assertSessionHasErrors('password');

        $this->post('/account/password', ['current_password' => 'old-password-123', 'password' => 'brand-new-pass-1', 'password_confirmation' => 'brand-new-pass-1'])->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('brand-new-pass-1', $u->fresh()->password));
    }

    public function test_a_person_who_joined_with_google_can_set_a_password_without_an_old_one(): void
    {
        $u = $this->member(['custom_data' => ['no_password' => true]]);
        $this->get('/account/security')->assertSee('Set a password');

        $this->post('/account/password', ['password' => 'brand-new-pass-1', 'password_confirmation' => 'brand-new-pass-1'])->assertSessionHasNoErrors();
        $u->refresh();
        $this->assertTrue(Hash::check('brand-new-pass-1', $u->password));
        $this->assertTrue($u->hasOwnPassword());
    }

    public function test_changing_the_email_needs_the_password_and_has_to_be_confirmed_again(): void
    {
        Notification::fake();
        \Illuminate\Support\Facades\Mail::fake();
        $u = $this->member(['email' => 'old@example.com', 'email_verified_at' => now()]);
        User::factory()->create(['email' => 'taken@example.com']);

        $this->post('/account/email', ['new_email' => 'new@example.com', 'email_current_password' => 'wrong'])->assertSessionHasErrors('email_current_password');
        $this->post('/account/email', ['new_email' => 'taken@example.com', 'email_current_password' => 'old-password-123'])->assertSessionHasErrors('new_email');
        $this->post('/account/email', ['new_email' => 'old@example.com', 'email_current_password' => 'old-password-123'])->assertSessionHasErrors('new_email');
        $this->assertSame('old@example.com', $u->fresh()->email);

        $this->post('/account/email', ['new_email' => 'New@Example.com', 'email_current_password' => 'old-password-123'])->assertSessionHasNoErrors();
        $u->refresh();
        $this->assertSame('new@example.com', $u->email);
        $this->assertNull($u->email_verified_at);
        Notification::assertSentTo($u, \Illuminate\Auth\Notifications\VerifyEmail::class);
    }

    public function test_a_linked_sign_in_cannot_be_removed_when_it_is_the_only_way_in(): void
    {
        $u = $this->member(['custom_data' => ['no_password' => true]]);
        SocialAccount::create(['user_id' => $u->id, 'provider' => 'google', 'provider_id' => 'g1', 'email' => $u->email]);

        $this->post('/account/sign-ins/google/unlink')->assertSessionHasErrors('unlink');
        $this->assertSame(1, SocialAccount::count());

        $this->post('/account/password', ['password' => 'brand-new-pass-1', 'password_confirmation' => 'brand-new-pass-1']);
        $this->post('/account/sign-ins/google/unlink')->assertSessionHasNoErrors();
        $this->assertSame(0, SocialAccount::count());
    }

    public function test_nobody_can_unlink_someone_elses_sign_in(): void
    {
        $other = User::factory()->create();
        SocialAccount::create(['user_id' => $other->id, 'provider' => 'google', 'provider_id' => 'g2', 'email' => $other->email]);
        $this->member();

        $this->post('/account/sign-ins/google/unlink')->assertNotFound();
        $this->assertSame(1, SocialAccount::count());
    }

    public function test_my_data_is_a_json_file_with_my_things_only(): void
    {
        $cat = Category::create(['name' => ['en' => 'Shows'], 'slug' => 'shows']);
        $article = Article::create(['title' => ['en' => 'Moon article'], 'slug' => 'moon-article', 'category_id' => $cat->id, 'status' => 'published', 'publish_date' => now()->subDay()]);
        $other = User::factory()->create();
        Comment::create(['article_id' => $article->id, 'user_id' => $other->id, 'body' => 'Someone else', 'status' => 'approved']);
        $u = $this->member();
        Comment::create(['article_id' => $article->id, 'user_id' => $u->id, 'body' => 'My own words', 'status' => 'approved']);

        $response = $this->get('/account/my-data')->assertOk()->assertHeader('Content-Disposition', 'attachment; filename="my-data.json"');
        $json = json_decode($response->getContent(), true);
        $this->assertSame($u->email, $json['profile']['email']);
        $this->assertSame('My own words', $json['comments'][0]['text']);
        $this->assertCount(1, $json['comments']);
        $this->assertStringNotContainsString('Someone else', $response->getContent());
        $this->assertArrayNotHasKey('password', $json['profile']);
    }

    public function test_deleting_the_account_removes_the_person_and_keeps_comments_without_a_name(): void
    {
        $cat = Category::create(['name' => ['en' => 'Shows'], 'slug' => 'shows']);
        $article = Article::create(['title' => ['en' => 'Moon article'], 'slug' => 'moon-article', 'category_id' => $cat->id, 'status' => 'published', 'publish_date' => now()->subDay()]);
        $u = $this->member(['email' => 'gone@example.com']);
        Comment::create(['article_id' => $article->id, 'user_id' => $u->id, 'body' => 'Still here', 'status' => 'approved']);
        NewsletterSubscriber::create(['email' => 'gone@example.com', 'status' => 'active', 'unsubscribe_token' => 'tok123']);
        $draft = Article::create(['title' => ['en' => 'My draft'], 'slug' => 'my-draft', 'category_id' => $cat->id, 'status' => 'draft', 'author_id' => $u->id]);
        $published = Article::create(['title' => ['en' => 'My published'], 'slug' => 'my-published', 'category_id' => $cat->id, 'status' => 'published', 'publish_date' => now()->subDay(), 'author_id' => $u->id]);

        $this->post('/account/delete', ['delete_password' => 'wrong'])->assertSessionHasErrors('delete_password');
        $this->assertNotNull(User::find($u->id));

        $this->post('/account/delete', ['delete_password' => 'old-password-123'])->assertRedirect('/');
        $this->assertNull(User::find($u->id));
        $this->assertSame(0, NewsletterSubscriber::where('email', 'gone@example.com')->count());
        $this->assertSame('Still here', Comment::first()->body);
        $this->assertNull(Comment::first()->user_id);
        $this->assertNull(Article::withTrashed()->find($draft->id));
        $this->assertNull($published->fresh()->author_id);
        $this->assertGuest();
    }

    public function test_staff_cannot_delete_their_own_account_here(): void
    {
        $u = $this->member(['role' => 'moderator']);
        $this->get('/account/privacy')->assertSee('Please ask the super user');
        $this->post('/account/delete', ['delete_password' => 'old-password-123'])->assertForbidden();
        $this->assertNotNull(User::find($u->id));
    }

    public function test_a_person_without_a_password_confirms_the_delete_with_the_email(): void
    {
        $u = $this->member(['email' => 'g@example.com', 'custom_data' => ['no_password' => true]]);
        $this->post('/account/delete', ['delete_email' => 'other@example.com'])->assertSessionHasErrors('delete_email');
        $this->post('/account/delete', ['delete_email' => 'g@example.com'])->assertRedirect('/');
        $this->assertNull(User::find($u->id));
    }

    public function test_forgotten_password_does_not_tell_whether_the_email_exists_and_the_link_works_once(): void
    {
        Notification::fake();
        $u = User::factory()->create(['email' => 'me@example.com', 'password' => 'old-password-123', 'status' => 'active']);

        $this->get('/login')->assertSee('Forgot your password?');
        $a = $this->post('/forgot-password', ['email' => 'me@example.com']);
        $b = $this->post('/forgot-password', ['email' => 'nobody@example.com']);
        $this->assertSame(session('status'), $a->getSession()->get('status'));
        $this->assertSame($a->getSession()->get('status'), $b->getSession()->get('status'));
        Notification::assertSentToTimes($u, ResetPassword::class, 1);

        $token = Password::createToken($u);
        $this->get('/reset-password/'.$token.'?email=me@example.com')->assertOk()->assertSee('Choose a new password');

        $this->post('/reset-password', ['token' => 'wrong', 'email' => 'me@example.com', 'password' => 'brand-new-pass-1', 'password_confirmation' => 'brand-new-pass-1'])->assertSessionHasErrors('email');
        $this->post('/reset-password', ['token' => $token, 'email' => 'me@example.com', 'password' => 'brand-new-pass-1', 'password_confirmation' => 'brand-new-pass-1'])->assertRedirect('/login');
        $this->assertTrue(Hash::check('brand-new-pass-1', $u->fresh()->password));

        $this->post('/reset-password', ['token' => $token, 'email' => 'me@example.com', 'password' => 'another-pass-123', 'password_confirmation' => 'another-pass-123'])->assertSessionHasErrors('email');
    }

    public function test_a_comment_shows_the_picture_of_its_writer(): void
    {
        $cat = Category::create(['name' => ['en' => 'Shows'], 'slug' => 'shows']);
        $article = Article::create(['title' => ['en' => 'Moon article'], 'slug' => 'moon-article', 'category_id' => $cat->id, 'status' => 'published', 'publish_date' => now()->subDay()]);
        $u = User::factory()->create(['photo' => 'profiles/x.webp', 'role' => 'member', 'status' => 'active']);
        Comment::create(['article_id' => $article->id, 'user_id' => $u->id, 'body' => 'Hi', 'status' => 'approved']);

        $this->get('/moon-article')->assertSee('comment__avatar', false)->assertSee('storage/profiles/x.webp', false);
    }
}
