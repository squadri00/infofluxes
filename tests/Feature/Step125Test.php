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

/** Step 125: "Continue with Microsoft" (personal Hotmail, Outlook and Live accounts). */
class Step125Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::forget();
        config(['services.google.client_id' => null, 'services.facebook.client_id' => null, 'services.microsoft.client_id' => 'ms-test-id', 'services.microsoft.client_secret' => 'ms-test-secret', 'services.microsoft.tenant' => 'consumers']);
    }

    private function answer(array $profile): void
    {
        $user = (new SocialUser)->map($profile);
        $user->user = [];
        $driver = Mockery::mock(Provider::class);
        $driver->shouldReceive('user')->andReturn($user);
        Socialite::shouldReceive('driver')->with('microsoft')->andReturn($driver);
    }

    public function test_the_button_shows_only_with_both_keys_and_only_for_personal_accounts(): void
    {
        $this->get('/login')->assertOk()->assertSee('Continue with Microsoft');
        $this->assertArrayHasKey('microsoft', SocialLogin::available());

        config(['services.microsoft.client_secret' => null]);
        $this->get('/login')->assertDontSee('Continue with Microsoft');

        config(['services.microsoft.client_secret' => 'ms-test-secret', 'services.microsoft.tenant' => 'common']);
        $this->get('/login')->assertDontSee('Continue with Microsoft');
        $this->get('/login/microsoft')->assertNotFound();
    }

    public function test_the_driver_is_known_to_socialite(): void
    {
        $this->assertInstanceOf(\SocialiteProviders\Microsoft\Provider::class, Socialite::driver('microsoft'));
    }

    public function test_a_new_person_becomes_a_member_with_a_confirmed_email(): void
    {
        $this->answer(['id' => 'ms-1', 'name' => 'Sam Hotmail', 'email' => 'Sam@Hotmail.com']);

        $this->get('/login/microsoft/callback')->assertRedirect(route('account'));

        $user = User::where('email', 'sam@hotmail.com')->first();
        $this->assertNotNull($user);
        $this->assertSame('member', $user->role);
        $this->assertNotNull($user->email_verified_at);
        $this->assertSame(1, SocialAccount::where('provider', 'microsoft')->where('provider_id', 'ms-1')->count());
        $this->assertAuthenticatedAs($user);
    }

    public function test_the_same_person_signs_in_again(): void
    {
        $this->answer(['id' => 'ms-2', 'name' => 'Sam', 'email' => 'sam@outlook.com']);
        $this->get('/login/microsoft/callback')->assertRedirect(route('account'));
        $this->post('/logout');
        $this->get('/login/microsoft/callback')->assertRedirect(route('account'));
        $this->assertSame(1, User::where('email', 'sam@outlook.com')->count());
    }

    public function test_a_staff_email_is_never_joined(): void
    {
        User::factory()->create(['email' => 'boss@outlook.com', 'role' => 'super_user']);
        $this->answer(['id' => 'ms-3', 'name' => 'Boss', 'email' => 'boss@outlook.com']);
        $this->get('/login/microsoft/callback')->assertRedirect(route('login'));
        $this->assertSame(0, SocialAccount::where('provider_id', 'ms-3')->count());
    }
}
