<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // The email that asks a new member to confirm their address: simple words, in the name of the site.
        \Illuminate\Auth\Notifications\VerifyEmail::toMailUsing(function ($notifiable, string $url) {
            $site = (string) \App\Models\Setting::get('site_name', config('app.name'));

            return (new \Illuminate\Notifications\Messages\MailMessage)
                ->subject('Please confirm your email for '.$site)
                ->greeting('Hello '.$notifiable->name.',')
                ->line('Thank you for signing up. Please press the button to confirm your email address.')
                ->action('Confirm my email', $url)
                ->line('The link works for '.(int) config('auth.verification.expire', 60).' minutes. If it stops working, sign in and press "Send the email again".')
                ->line('If you did not sign up, you can ignore this email.')
                ->salutation('The '.$site.' team');
        });
        // Names kept in the database for likes and saves (so a class can be renamed without breaking them).
        \Illuminate\Database\Eloquent\Relations\Relation::morphMap(['article' => \App\Models\Article::class, 'story' => \App\Models\Story::class]);

        // The tests of "Challenge yourself": a generous limit for a person playing, a stop for a script.
        RateLimiter::for('quiz', fn (Request $request) => Limit::perMinute(120)->by($request->user()?->id ?: $request->ip()));
    }
}
