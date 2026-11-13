<?php

namespace App\Http\Controllers;

use App\Support\Challenge;

/** "Challenge yourself": the list of tests, and one page for each test. */
class ChallengeController extends Controller
{
    public function index()
    {
        return view('challenge.index', ['tests' => Challenge::tests()]);
    }

    public function show(string $test)
    {
        $found = Challenge::tests()->firstWhere('key', $test);
        abort_unless($found && $found['on'], 404);

        return view('challenge.show', ['test' => $found]);
    }
}
