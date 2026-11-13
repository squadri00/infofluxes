<?php

namespace App\Http\Controllers;

use App\Support\Newsletter;
use Illuminate\Http\Request;

/** The newsletter sign-up: type an address, get a code by email, type the code. Works with and without JavaScript. */
class NewsletterController extends Controller
{
    private function guard(): void
    {
        abort_unless(Newsletter::enabled(), 404);
    }

    public function show(Request $request)
    {
        $this->guard();

        return view('newsletter.show', [
            'email' => $request->session()->get('newsletter_email'),
            'step' => $request->session()->get('newsletter_step', 'form'),
        ]);
    }

    public function subscribe(Request $request)
    {
        $this->guard();

        // A hidden box that people never fill in: robots do.
        if (filled($request->input('website_url'))) {
            return $this->answer($request, ['status' => 'sent'], null, 'form');
        }

        $result = Newsletter::request($request->input('email'));
        $email = Newsletter::clean($request->input('email'));

        return $this->answer($request, $result, $email, in_array($result['status'], ['sent', 'wait'], true) ? 'code' : 'form');
    }

    public function confirm(Request $request)
    {
        $this->guard();

        $result = Newsletter::confirm($request->input('email'), $request->input('code'));
        $email = Newsletter::clean($request->input('email'));

        return $this->answer($request, $result, $email, match ($result['status']) {
            'ok' => 'done',
            'wrong' => 'code',
            default => 'form',
        });
    }

    public function unsubscribeShow(string $token)
    {
        $this->guard();

        return view('newsletter.unsubscribe', ['token' => $token, 'done' => false]);
    }

    public function unsubscribe(string $token)
    {
        $this->guard();
        Newsletter::unsubscribe($token);

        return view('newsletter.unsubscribe', ['token' => $token, 'done' => true]);
    }

    private function answer(Request $request, array $result, ?string $email, string $step)
    {
        $message = Newsletter::message($result, $email);
        $good = in_array($result['status'], ['sent', 'ok'], true);

        if ($request->expectsJson()) {
            return response()->json(['ok' => $good, 'status' => $result['status'], 'step' => $step, 'message' => $message, 'email' => $email]);
        }

        return redirect()->route('newsletter.show')
            ->with('newsletter_email', $email)
            ->with('newsletter_step', $step)
            ->with('newsletter_message', $message)
            ->with('newsletter_good', $good);
    }
}
