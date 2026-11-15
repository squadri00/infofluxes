<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\SocialAccount;
use App\Models\User;
use App\Support\SocialLogin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialUser;
use Mockery;
use Tests\TestCase;

/** Step 55: sign in and sign up with Google and Facebook. */
class Step55Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::forget();   // what one test saved in the settings must not be remembered by the next
    }

    private function keys(bool $google = true, bool $facebook = true): void
    {
        config([
            'services.google.client_id' => $google ? 'google-test-id' : null, 'services.google.client_secret' => $google ? 'google-test-secret' : null,
            'services.facebook.client_id' => $facebook ? 'facebook-test-id' : null, 'services.facebook.client_secret' => $facebook ? 'facebook-test-secret' : null,
        ]);
    }

    /** Pretends to be Google or Facebook answering with this person. */
    private function answer(string $provider, array $profile, array $raw = []): void
    {
        $user = (new SocialUser)->map($profile);
        $user->user = $raw;
        $driver = Mockery::mock(Provider::class);
        $driver->shouldReceive('user')->andReturn($user);
        Socialite::shouldReceive('driver')->with($provider)->andReturn($driver);
    }

    // ---------- the buttons ----------

    public function test_a_button_shows_only_when_both_keys_are_set(): void
    {
        $this->keys(false, false);
        $this->get('/login')->assertOk()->assertDontSee('Continue with');
        $this->assertSame([], SocialLogin::available());

        $this->keys(true, false);
        $this->get('/login')->assertSee('Continue with Google')->assertDontSee('Continue with Facebook');

        config(['services.google.client_secret' => null]);
        $this->get('/login')->assertDontSee('Continue with Google');

        $this->keys(true, true);
        $this->get('/register')->assertOk()->assertSee('Continue with Google')->assertSee('Continue with Facebook');
        $this->assertSame(['google' => 'Google', 'facebook' => 'Facebook'], SocialLogin::available());
    }

    public function test_the_button_leads_to_the_provider_with_our_return_address(): void
    {
        $this->keys();

        $r = $this->get('/login/google')->assertRedirect();

        $location = $r->headers->get('Location');
        $this->assertStringStartsWith('https://accounts.google.com/', $location);
        $this->assertStringContainsString('client_id=google-test-id', $location);
        $this->assertStringContainsString(rawurlencode(url('/login/google/callback')), $location);
        $this->assertStringNotContainsString('secret', $location);

        $this->assertStringStartsWith('https://www.facebook.com/', $this->get('/login/facebook')->headers->get('Location'));
    }

    public function test_a_provider_that_is_off_or_unknown_is_not_found(): void
    {
        $this->keys(false, true);

        $this->get('/login/google')->assertNotFound();
        $this->get('/login/google/callback')->assertNotFound();
        $this->get('/login/twitter')->assertNotFound();
    }

    // ---------- coming back ----------

    public function test_a_new_person_becomes_a_member_with_a_confirmed_email(): void
    {
        $this->keys();
        $this->answer('google', ['id' => 'g-1', 'name' => 'Sara Khan', 'email' => 'Sara@Example.org'], ['email_verified' => true]);

        $this->get('/login/google/callback')->assertRedirect(route('account'));

        $u = User::where('email', 'sara@example.org')->firstOrFail();
        $this->assertSame(['Sara Khan', 'member', 'active'], [$u->name, $u->role, $u->status]);
        $this->assertNotNull($u->email_verified_at);
        $this->assertAuthenticatedAs($u);
        $this->assertSame(['google', 'g-1', 'sara@example.org'], [SocialAccount::first()->provider, SocialAccount::first()->provider_id, SocialAccount::first()->email]);
    }

    public function test_nothing_but_the_name_the_email_and_the_id_is_kept(): void
    {
        $this->keys();
        $this->answer('facebook', ['id' => 'f-1', 'name' => 'Omar', 'email' => 'omar@example.org', 'avatar' => 'https://pic.example/x.jpg'], []);

        $this->get('/login/facebook/callback');

        $row = SocialAccount::first()->getAttributes();
        $this->assertStringNotContainsString('pic.example', json_encode($row));
        $this->assertStringNotContainsString('pic.example', json_encode(User::first()->getAttributes()));
    }

    public function test_the_same_person_signs_in_again_without_a_second_account(): void
    {
        $this->keys();
        $this->answer('google', ['id' => 'g-1', 'name' => 'Sara', 'email' => 'sara@example.org'], ['email_verified' => true]);
        $this->get('/login/google/callback');
        auth()->logout();

        $this->get('/login/google/callback')->assertRedirect(route('account'));

        $this->assertSame(1, User::count());
        $this->assertSame(1, SocialAccount::count());
        $this->assertAuthenticated();
    }

    public function test_it_signs_in_even_when_the_email_changed_at_the_provider(): void
    {
        $this->keys();
        $u = User::factory()->create(['email' => 'old@example.org', 'role' => 'member', 'status' => 'active']);
        SocialAccount::create(['user_id' => $u->id, 'provider' => 'google', 'provider_id' => 'g-9', 'email' => 'old@example.org']);
        $this->answer('google', ['id' => 'g-9', 'name' => 'Renamed', 'email' => 'new@example.org'], ['email_verified' => true]);

        $this->get('/login/google/callback')->assertRedirect(route('account'));

        $this->assertAuthenticatedAs($u);
        $this->assertSame(1, User::count());
    }

    public function test_a_member_with_the_same_confirmed_email_is_joined_to_the_account(): void
    {
        $this->keys();
        $u = User::factory()->create(['email' => 'sara@example.org', 'role' => 'member', 'status' => 'active', 'email_verified_at' => null]);
        $this->answer('google', ['id' => 'g-2', 'name' => 'Sara', 'email' => 'sara@example.org'], ['email_verified' => true]);

        $this->get('/login/google/callback')->assertRedirect(route('account'));

        $this->assertAuthenticatedAs($u);
        $this->assertSame(1, User::count());
        $this->assertSame($u->id, SocialAccount::first()->user_id);
        $this->assertNotNull($u->fresh()->email_verified_at);
    }

    public function test_a_staff_account_is_never_joined_or_signed_in_this_way(): void
    {
        $this->keys();
        foreach (['superuser', 'moderator', 'author'] as $i => $role) {
            $staff = User::factory()->create(['email' => $role.'@example.org', 'role' => $role, 'status' => 'active']);
            $this->answer('google', ['id' => 'g-s'.$i, 'name' => 'X', 'email' => $role.'@example.org'], ['email_verified' => true]);

            $this->get('/login/google/callback')->assertRedirect(route('login'))->assertSessionHasErrors('email');
            $this->assertGuest();
            $this->assertSame(0, SocialAccount::where('user_id', $staff->id)->count());
        }
    }

    public function test_even_an_account_joined_earlier_cannot_be_used_if_the_person_became_staff(): void
    {
        $this->keys();
        $u = User::factory()->create(['role' => 'moderator', 'status' => 'active']);
        SocialAccount::create(['user_id' => $u->id, 'provider' => 'google', 'provider_id' => 'g-m', 'email' => $u->email]);
        $this->answer('google', ['id' => 'g-m', 'name' => 'X', 'email' => $u->email], ['email_verified' => true]);

        $this->get('/login/google/callback')->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_an_email_that_google_has_not_confirmed_is_refused(): void
    {
        $this->keys();
        $this->answer('google', ['id' => 'g-3', 'name' => 'Sara', 'email' => 'sara@example.org'], ['email_verified' => false]);

        $this->get('/login/google/callback')->assertRedirect(route('login'))->assertSessionHasErrors('email');

        $this->assertSame(0, User::count());
        $this->assertGuest();
    }

    public function test_no_email_means_no_account(): void
    {
        $this->keys();
        $this->answer('facebook', ['id' => 'f-2', 'name' => 'No Email', 'email' => null]);

        $this->get('/login/facebook/callback')->assertRedirect(route('login'))->assertSessionHasErrors('email');

        $this->assertSame(0, User::count());
    }

    public function test_a_person_who_is_switched_off_cannot_sign_in(): void
    {
        $this->keys();
        $u = User::factory()->create(['role' => 'member', 'status' => 'switched_off']);
        SocialAccount::create(['user_id' => $u->id, 'provider' => 'facebook', 'provider_id' => 'f-3', 'email' => $u->email]);
        $this->answer('facebook', ['id' => 'f-3', 'name' => 'X', 'email' => $u->email]);

        $this->get('/login/facebook/callback')->assertRedirect(route('login'))->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_when_sign_ups_are_closed_a_new_person_cannot_join(): void
    {
        $this->keys();
        Setting::put('signups_open', false);
        $this->answer('google', ['id' => 'g-new', 'name' => 'New', 'email' => 'new@example.org'], ['email_verified' => true]);

        $this->get('/login/google/callback')->assertRedirect(route('login'))->assertSessionHasErrors('email');

        $this->assertSame(0, User::count());
    }

    public function test_when_sign_ups_are_closed_a_person_who_joined_before_can_still_sign_in(): void
    {
        $this->keys();
        Setting::put('signups_open', false);
        $u = User::factory()->create(['role' => 'member', 'status' => 'active']);
        SocialAccount::create(['user_id' => $u->id, 'provider' => 'google', 'provider_id' => 'g-old', 'email' => $u->email]);
        $this->answer('google', ['id' => 'g-old', 'name' => 'Old', 'email' => $u->email], ['email_verified' => true]);

        $this->get('/login/google/callback')->assertRedirect(route('account'));

        $this->assertAuthenticatedAs($u);
    }

    public function test_pressing_cancel_at_the_provider_or_an_error_gives_a_calm_message(): void
    {
        $this->keys();
        $this->get('/login/google/callback?error=access_denied')->assertRedirect(route('login'))->assertSessionHasErrors('email');

        $driver = Mockery::mock(Provider::class);
        $driver->shouldReceive('user')->andThrow(new \RuntimeException('state mismatch'));
        Socialite::shouldReceive('driver')->with('google')->andReturn($driver);
        $this->get('/login/google/callback?code=abc')->assertRedirect(route('login'))->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_the_page_after_sign_in_goes_where_the_person_was_going(): void
    {
        $this->keys();
        $this->get('/account')->assertRedirect(route('login'));
        $this->answer('google', ['id' => 'g-5', 'name' => 'Sara', 'email' => 'sara5@example.org'], ['email_verified' => true]);

        $this->get('/login/google/callback')->assertRedirect(url('/account'));
    }

    public function test_a_signed_in_person_does_not_see_the_social_routes(): void
    {
        $this->keys();
        $this->actingAs(User::factory()->create());

        $this->get('/login/google')->assertRedirect();   // the guest middleware sends them away
        $this->assertStringNotContainsString('accounts.google.com', (string) $this->get('/login/google')->headers->get('Location'));
    }

    public function test_the_keys_are_only_named_never_written_in_the_example_file(): void
    {
        $example = (string) file_get_contents(base_path('.env.example'));

        $this->assertMatchesRegularExpression('/^GOOGLE_CLIENT_ID=$/m', $example);
        $this->assertMatchesRegularExpression('/^FACEBOOK_CLIENT_SECRET=$/m', $example);
    }
}
