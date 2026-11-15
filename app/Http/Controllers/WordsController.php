<?php

namespace App\Http\Controllers;

use App\Models\Explainer;

/** "Words explained": every published word note, A to Z, on one page. */
class WordsController extends Controller
{
    public function __invoke()
    {
        $notes = Explainer::published()->get()->sortBy(fn ($e) => mb_strtolower($e->mainTerm()))->values();
        $groups = $notes->groupBy(fn ($e) => mb_strtoupper(mb_substr(preg_replace('/[^\p{L}\p{N}]/u', '', $e->mainTerm()), 0, 1)) ?: '#');

        return view('words', ['groups' => $groups, 'count' => $notes->count()]);
    }
}
