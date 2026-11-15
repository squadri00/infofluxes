<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\SocialAccount;
use App\Models\User;
use App\Support\SocialLogin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

/**
 * Sign in or sign up with Google or Facebook.
 * - A person who signed in this way before is signed in again.
 * - A new person becomes a member (only if sign-ups are open). Their email counts as confirmed, because Google or Facebook confirmed it.
 * - An existing member with the same confirmed email is joined to the account.
 * - A staff account (super user, moderator, author) is never joined or signed in this way: staff use their password.
 * Only the name, the email and the provider's id are kept.
 */
class SocialAuthController extends Controller
{
    public function redirect(string $provider)
    {
        abort_unless(SocialLogin::enabled($provider), 404);

        return Socialite::driver($provider)->redirect();
    }

    public function callback(Request $request, string $provider)
    {
        abort_unless(SocialLogin::enabled($provider), 404);
        if ($request->filled('error')) {
            return $this->fail('social_cancelled');   // the person pressed "cancel" at Google or Facebook
        }

        try {
            $social = Socialite::driver($provider)->user();
        } catch (\Throwable $e) {
            report($e);

            return $this->fail('social_failed');
        }

        $id = (string) $social->getId();
        $email = mb_strtolower(trim((string) $social->getEmail()));
        if ($id === '') {
            return $this->fail('social_failed');
        }

        // 1. The same Google or Facebook account again.
        $account = SocialAccount::query()->where('provider', $provider)->where('provider_id', $id)->first();
        if ($account) {
            return $this->signIn($request, $account->user);
        }

        // 2. A new Google or Facebook account: it needs an email that the provider has confirmed.
        if ($email === '') {
            return $this->fail('social_no_email');
        }
        if ($provider === 'google' && ($social->user['email_verified'] ?? $social->user['verified_email'] ?? false) !== true) {
            return $this->fail('social_not_verified');
        }

        $user = User::query()->where('email', $email)->first();
        if ($user) {
            if ($user->role !== 'member') {
                return $this->fail('social_staff');   // a staff account is not joined to a Google or Facebook account
            }
        } else {
            if (! (bool) Setting::get('signups_open', true)) {
                return $this->fail('social_closed');
            }
            $user = User::create([
                'name' => mb_substr(trim((string) ($social->getName() ?: Str::before($email, '@'))), 0, 120),
                'email' => $email,
                'password' => Str::random(48),   // nobody knows it; the person signs in with Google or Facebook
                'role' => 'member',
                'status' => 'active',
            ]);
        }
        if (! $user->email_verified_at) {
            $user->forceFill(['email_verified_at' => now()])->save();   // the provider confirmed this email
        }
        SocialAccount::create(['user_id' => $user->id, 'provider' => $provider, 'provider_id' => $id, 'email' => $email]);

        return $this->signIn($request, $user);
    }

    private function signIn(Request $request, User $user)
    {
        if ($user->role !== 'member') {
            return $this->fail('social_staff');
        }
        if (! $user->isActive()) {
            return $this->fail('login_inactive');
        }
        Auth::login($user, true);
        $request->session()->regenerate();

        return redirect()->intended(route('account'));
    }

    private function fail(string $key)
    {
        return redirect()->route('login')->withErrors(['email' => __('site.'.$key)]);
    }
}
