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
        // Names kept in the database for likes and saves (so a class can be renamed without breaking them).
        \Illuminate\Database\Eloquent\Relations\Relation::morphMap(['article' => \App\Models\Article::class, 'story' => \App\Models\Story::class]);

        // The tests of "Challenge yourself": a generous limit for a person playing, a stop for a script.
        RateLimiter::for('quiz', fn (Request $request) => Limit::perMinute(120)->by($request->user()?->id ?: $request->ip()));
    }
}
