<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;

/**
 * Confirming the email address of a member. A member who has not confirmed can read and sign in,
 * but cannot comment, like, save or send in an article until the email is confirmed.
 */
class VerifyEmailController extends Controller
{
    /** The page that says "we sent you an email". */
    public function notice(Request $request)
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route('account');
        }

        return view('auth.verify-email', ['email' => $request->user()->email]);
    }

    /** The link in the email. The address is signed, so it cannot be changed, and it belongs to the person who is signed in. */
    public function verify(EmailVerificationRequest $request)
    {
        $request->fulfill();

        return redirect()->route('account')->with('status', __('site.verify_done'));
    }

    /** "Send the email again". */
    public function send(Request $request)
    {
        $user = $request->user();
        if ($user->hasVerifiedEmail()) {
            return redirect()->route('account');
        }

        return static::sendTo($user)
            ? back()->with('status', __('site.verify_sent'))
            : back()->withErrors(['email' => __('site.verify_send_failed')]);
    }

    /** Sends the email. A problem with the mail server never breaks the page; it is reported and the person can press the button again. */
    public static function sendTo(\App\Models\User $user): bool
    {
        try {
            $user->sendEmailVerificationNotification();

            return true;
        } catch (\Throwable $e) {
            report($e);

            return false;
        }
    }
}
