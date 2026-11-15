<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** Shows the site in the language the visitor chose (kept in the session), or in the main language. */
class SetLocale
{
    public function handle(Request $request, Closure $next)
    {
        $locales = array_keys(\App\Support\Languages::active());
        $chosen = $request->session()->get('locale');
        $locale = in_array($chosen, $locales, true) ? $chosen : config('infofluxes.default_locale');
        app()->setLocale($locale);

        return $next($request);
    }
}
