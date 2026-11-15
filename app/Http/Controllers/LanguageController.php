<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class LanguageController extends Controller
{
    /** Remember the language the visitor chose, then go back where they were. */
    public function __invoke(Request $request, string $code)
    {
        if (array_key_exists($code, \App\Support\Languages::active())) {
            $request->session()->put('locale', $code);
        }
        $back = url()->previous();
        $own = parse_url($back, PHP_URL_HOST) === $request->getHost();

        return redirect($own ? $back : route('home'));
    }
}
