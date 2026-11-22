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
        // When the English text of a story changes, the languages made from the old text are hidden and marked "out of date".
        \App\Support\StoryMaker\OutdatedTranslations::register();

        // The notices of the members: a comment approved, a reply, an article they sent in, and news from what they follow.
        \App\Support\MemberEvents::register();

        // A picture saved with an empty ALT text gets one made from its page (the title, the tab, the slide heading, the name).
        \App\Support\PictureAlt::register();

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
        // The email with the link to choose a new password.
        \Illuminate\Auth\Notifications\ResetPassword::toMailUsing(function ($notifiable, string $token) {
            $site = (string) \App\Models\Setting::get('site_name', config('app.name'));
            $minutes = (int) config('auth.passwords.users.expire', 60);
            $url = route('password.reset', ['token' => $token, 'email' => $notifiable->getEmailForPasswordReset()]);

            return (new \Illuminate\Notifications\Messages\MailMessage)
                ->subject(str_replace(':site', $site, __('account.reset_mail_subject')))
                ->greeting('Hello '.$notifiable->name.',')
                ->line(__('account.reset_mail_line1'))
                ->action(__('account.reset_mail_action'), $url)
                ->line(str_replace(':minutes', (string) $minutes, __('account.reset_mail_line2')))
                ->salutation('The '.$site.' team');
        });
        // Names kept in the database for likes and saves (so a class can be renamed without breaking them).
        \Illuminate\Database\Eloquent\Relations\Relation::morphMap(['article' => \App\Models\Article::class, 'story' => \App\Models\Story::class]);

        // The tests of "Challenge yourself": a generous limit for a person playing, a stop for a script.
        RateLimiter::for('quiz', fn (Request $request) => Limit::perMinute(120)->by($request->user()?->id ?: $request->ip()));
    }
}
