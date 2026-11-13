<?php

namespace App\Http\Controllers;

use App\Models\DidYouKnow;

/** The "Did You Know?" page: every published fact that has a source, listed like a FAQ that opens and closes. */
class DidYouKnowController extends Controller
{
    public function __invoke()
    {
        return view('did-you-know', ['entries' => DidYouKnow::query()->published()->whereNotNull('source')->ordered()->with('category')->get()]);
    }
}
