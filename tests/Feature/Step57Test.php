<?php

namespace Tests\Feature;

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Models\Article;
use App\Models\Category;
use App\Models\Setting;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Tests\TestCase;

/** Step 57: a new member confirms their email address before they can comment, like, save or send in an article. */
class Step57Test extends TestCase
{
    use RefreshDatabase;

    private function signUp(array $over = []): void
    {
        $this->post('/register', array_merge(['name' => 'Sam Reader', 'email' => 'sam@example.org', 'password' => 'a-long-password-1', 'password_confirmation' => 'a-long-password-1'], $over));
    }

    private function unconfirmed(): User
    {
        $u = User::factory()->unverified()->create(['role' => 'member', 'status' => 'active']);
        $this->actingAs($u);

        return $u;
    }

    private function article(): Article
    {
        $cat = Category::create(['name' => ['en' => 'Shows'], 'slug' => 'shows']);

        return Article::create(['title' => ['en' => 'An article'], 'slug' => 'an-article', 'category_id' => $cat->id, 'status' => 'published', 'publish_date' => now()->subDay()]);
    }

    // ---------- signing up ----------

    public function test_signing_up_sends_the_email_and_leaves_the_address_unconfirmed(): void
    {
        Notification::fake();

        $this->signUp();

        $sam = User::where('email', 'sam@example.org')->firstOrFail();
        $this->assertNull($sam->email_verified_at);
        $this->assertFalse($sam->hasVerifiedEmail());
        Notification::assertSentTo($sam, VerifyEmail::class);
        $this->assertAuthenticatedAs($sam);
    }

    public function test_the_email_is_in_simple_words_in_the_name_of_the_site(): void
    {
        Setting::forget();
        Setting::put('site_name', 'InfoFluxes');
        $sam = User::factory()->unverified()->create(['name' => 'Sam Reader']);

        $mail = (new VerifyEmail)->toMail($sam);

        $this->assertSame('Please confirm your email for InfoFluxes', $mail->subject);
        $this->assertSame('Confirm my email', $mail->actionText);
        $this->assertStringContainsString('/email/verify/'.$sam->id.'/', $mail->actionUrl);
        $this->assertStringContainsString('signature=', $mail->actionUrl);
        $this->assertStringContainsString('Send the email again', implode(' ', $mail->outroLines));
        $this->assertSame('Hello Sam Reader,', $mail->greeting);
    }

    public function test_a_problem_with_the_mail_server_never_breaks_signing_up(): void
    {
        Notification::shouldReceive('send')->andThrow(new \RuntimeException('mail server is down'));
        config(['mail.default' => 'smtp']);

        $this->signUp();

        $this->assertNotNull(User::where('email', 'sam@example.org')->first());
        $this->assertAuthenticated();
    }

    public function test_the_page_after_sign_up_says_where_the_email_went_and_lets_them_send_it_again(): void
    {
        Notification::fake();
        $this->signUp();

        $this->get('/email/verify')->assertOk()->assertSee('sam@example.org')->assertSee('Send the email again');
        $this->post('/email/verification-notification')->assertRedirect();

        Notification::assertSentToTimes(User::where('email', 'sam@example.org')->first(), VerifyEmail::class, 2);
    }

    public function test_resending_is_limited(): void
    {
        Notification::fake();
        $this->unconfirmed();

        foreach (range(1, 6) as $i) {
            $this->post('/email/verification-notification')->assertRedirect();
        }

        $this->post('/email/verification-notification')->assertStatus(429);
    }

    // ---------- the link in the email ----------

    private function link(User $u, ?\DateTimeInterface $expires = null): string
    {
        return URL::temporarySignedRoute('verification.verify', $expires ?? now()->addMinutes(60), ['id' => $u->id, 'hash' => sha1($u->getEmailForVerification())]);
    }

    public function test_the_link_confirms_the_address(): void
    {
        $u = $this->unconfirmed();

        $this->get($this->link($u))->assertRedirect(route('account'));

        $this->assertTrue($u->fresh()->hasVerifiedEmail());
    }

    public function test_a_wrong_expired_or_changed_link_does_not_work(): void
    {
        $u = $this->unconfirmed();

        $this->get(route('verification.verify', ['id' => $u->id, 'hash' => sha1($u->email)]))->assertForbidden();               // not signed
        $this->get($this->link($u, now()->subMinute()))->assertForbidden();                                                      // too old
        $this->get(URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $u->id, 'hash' => 'wrong']))->assertForbidden();
        $other = User::factory()->unverified()->create();
        $this->get($this->link($other))->assertForbidden();                                                                      // someone else's link

        $this->assertFalse($u->fresh()->hasVerifiedEmail());
        $this->assertFalse($other->fresh()->hasVerifiedEmail());
    }

    public function test_someone_who_is_not_signed_in_is_sent_to_sign_in_first(): void
    {
        $u = User::factory()->unverified()->create(['role' => 'member', 'status' => 'active']);

        $this->get($this->link($u))->assertRedirect(route('login'));
    }

    public function test_a_confirmed_member_has_no_use_for_the_page(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'member', 'status' => 'active']));

        $this->get('/email/verify')->assertRedirect(route('account'));
        $this->post('/email/verification-notification')->assertRedirect(route('account'));
    }

    // ---------- what needs a confirmed address ----------

    public function test_a_member_who_has_not_confirmed_can_read_and_sign_in_but_not_comment_like_save_or_send_in(): void
    {
        $a = $this->article();
        $this->unconfirmed();

        $this->get('/an-article')->assertOk()->assertSee('Please confirm your email to do this.');
        $this->get('/account')->assertOk()->assertSee('Please confirm your email address.');

        $this->post('/comments', ['article_id' => $a->id, 'body' => 'Hello there'])->assertRedirect(route('verification.notice'));
        $this->post(route('react', ['article', $a->id]), ['value' => 'like'])->assertRedirect(route('verification.notice'));
        $this->post(route('save', ['article', $a->id]))->assertRedirect(route('verification.notice'));
        $this->get('/submit')->assertRedirect(route('verification.notice'));
        $this->post('/submit', ['title' => 'x'])->assertRedirect(route('verification.notice'));
        $this->assertSame(0, \App\Models\Comment::count());
        $this->assertSame(0, \App\Models\Reaction::count());
    }

    public function test_after_confirming_they_can_do_all_of_it(): void
    {
        $a = $this->article();
        $u = $this->unconfirmed();
        $this->get($this->link($u));

        $this->post('/comments', ['article_id' => $a->id, 'body' => 'Hello there'])->assertRedirect();
        $this->post(route('react', ['article', $a->id]), ['value' => 'like'])->assertRedirect();
        $this->post(route('save', ['article', $a->id]))->assertRedirect();

        $this->assertSame(1, \App\Models\Comment::count());
        $this->assertSame(1, $a->likesCount());
        $this->get('/submit')->assertOk();
        $this->get('/account')->assertOk()->assertDontSee('Please confirm your email address.');
    }

    public function test_the_comment_form_is_replaced_by_a_note_until_the_address_is_confirmed(): void
    {
        $this->article();
        $this->unconfirmed();

        $html = $this->get('/an-article')->getContent();

        $this->assertStringNotContainsString('name="body"', $html);
    }

    // ---------- people who are not asked ----------

    public function test_a_person_added_by_the_super_user_is_confirmed(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->create(['role' => 'superuser', 'status' => 'active']));

        Livewire::test(CreateUser::class)->fillForm(['name' => 'New Author', 'email' => 'author@example.org', 'password' => 'a-long-password-1', 'role' => 'author', 'status' => 'active'])->call('create')->assertHasNoFormErrors();

        $this->assertTrue(User::where('email', 'author@example.org')->first()->hasVerifiedEmail());
    }

    public function test_staff_enter_the_admin_whatever_their_confirmation(): void
    {
        Filament::setCurrentPanel('admin');
        $this->actingAs(User::factory()->unverified()->create(['role' => 'moderator', 'status' => 'active']));

        $this->get('/admin')->assertOk();
    }

    public function test_the_migration_confirms_the_people_who_were_there_before(): void
    {
        $old = User::factory()->unverified()->create();
        $migration = require database_path('migrations/2026_12_24_100000_mark_existing_people_as_confirmed.php');

        $migration->up();

        $this->assertTrue($old->fresh()->hasVerifiedEmail());
    }

    public function test_a_login_works_before_the_address_is_confirmed(): void
    {
        User::factory()->unverified()->create(['email' => 'new@example.org', 'password' => 'a-long-password-1', 'role' => 'member', 'status' => 'active']);

        $this->post('/login', ['email' => 'new@example.org', 'password' => 'a-long-password-1'])->assertRedirect(route('account'));

        $this->assertAuthenticated();
    }
}
