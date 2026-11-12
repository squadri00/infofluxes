<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:255'], 'password' => ['required', 'string', 'max:255']]);

        $user = User::where('email', $data['email'])->first();
        // The same words for a wrong email and a wrong password, so nobody can find out who has an account.
        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return back()->withInput($request->only('email'))->withErrors(['email' => __('site.login_failed')]);
        }
        if (! $user->isActive()) {
            return back()->withInput($request->only('email'))->withErrors(['email' => __('site.login_inactive')]);
        }

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        return redirect()->intended(route('account'));
    }

    public function showRegister()
    {
        abort_unless($this->open(), 404);

        return view('auth.register');
    }

    public function register(Request $request)
    {
        abort_unless($this->open(), 404);

        // The hidden "website" box is for robots: a person never fills it.
        if (filled($request->input('website'))) {
            return redirect()->route('home');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(10)],
        ]);

        $user = User::create(['name' => trim($data['name']), 'email' => $data['email'], 'password' => $data['password'], 'role' => 'member', 'status' => 'active']);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('account')->with('status', __('site.saved_notice'));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }

    private function open(): bool
    {
        return (bool) Setting::get('signups_open', true);
    }
}
