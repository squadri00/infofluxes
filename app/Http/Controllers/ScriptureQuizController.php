<?php

namespace App\Http\Controllers;

use App\Support\ScriptureQuiz;
use Illuminate\Http\Request;

/** The test "Which scripture?": start a round, check an answer, finish. Everything answers in JSON for the page's script. */
class ScriptureQuizController extends Controller
{
    private function guard(): void
    {
        abort_unless(ScriptureQuiz::enabled(), 404);
    }

    public function start()
    {
        $this->guard();
        abort_unless(ScriptureQuiz::ready(), 503);

        return response()->json(ScriptureQuiz::start())->header('Cache-Control', 'no-store');
    }

    public function answer(Request $request)
    {
        $this->guard();

        $data = $request->validate([
            'token' => ['required', 'string', 'max:20000'],
            'question' => ['required', 'integer', 'min:0', 'max:60'],
            'choice' => ['required', 'integer', 'min:0', 'max:9'],
        ]);

        $result = ScriptureQuiz::answer($data['token'], (int) $data['question'], (int) $data['choice']);
        abort_unless($result, 422);

        return response()->json($result)->header('Cache-Control', 'no-store');
    }

    public function finish(Request $request)
    {
        $this->guard();

        $data = $request->validate([
            'token' => ['required', 'string', 'max:20000'],
            'choices' => ['required', 'array', 'max:60'],
            'choices.*' => ['nullable', 'integer', 'min:0', 'max:9'],
        ]);

        $result = ScriptureQuiz::finish($data['token'], array_map(fn ($c) => $c === null ? null : (int) $c, $data['choices']));
        abort_unless($result, 422);

        return response()->json($result)->header('Cache-Control', 'no-store');
    }
}
