<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;

/** Forgotten password: ask for a link by email, then choose a new password. The answer never tells whether an email has an account. */
class PasswordResetController extends Controller
{
    public function showForgot()
    {
        return view('auth.forgot-password');
    }

    public function sendLink(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:255']]);
        try {
            Password::sendResetLink(['email' => $data['email']]);
        } catch (\Throwable $e) {
            report($e);   // a mail problem is for the owner to see in the log, not for the visitor
        }

        return back()->with('status', str_replace(':minutes', (string) (int) config('auth.passwords.users.expire', 60), __('account.forgot_sent')));
    }

    public function showReset(Request $request, string $token)
    {
        return view('auth.reset-password', ['token' => $token, 'email' => (string) $request->query('email')]);
    }

    public function reset(Request $request)
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(10)],
        ]);

        $status = Password::reset($data, function (User $user, string $password) {
            $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)]);
            $user->custom_data = collect($user->custom_data ?? [])->except('no_password')->all() ?: null;
            $user->save();
            event(new PasswordReset($user));
        });

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('login')->with('status', __('account.reset_done'))
            : back()->withInput($request->only('email'))->withErrors(['email' => __('account.reset_bad')]);
    }
}
