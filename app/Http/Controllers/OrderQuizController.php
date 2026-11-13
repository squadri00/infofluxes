<?php

namespace App\Http\Controllers;

use App\Support\OrderQuiz;
use Illuminate\Http\Request;

/** The test "Put the chapters in order": start a round, check an order. Everything answers in JSON for the page's script. */
class OrderQuizController extends Controller
{
    private function guard(): void
    {
        abort_unless(OrderQuiz::enabled(), 404);
    }

    public function start(Request $request)
    {
        $this->guard();
        abort_unless(OrderQuiz::ready(), 503);

        $data = $request->validate([
            'level' => ['required', 'in:'.implode(',', OrderQuiz::LEVELS)],
            'practice' => ['nullable', 'in:0,1'],
        ]);

        return response()->json(OrderQuiz::start($data['level'], ((string) ($data['practice'] ?? '0')) === '1'))->header('Cache-Control', 'no-store');
    }

    /** Practice only: is this title right for this place? */
    public function tryTile(Request $request)
    {
        $this->guard();

        $data = $request->validate([
            'token' => ['required', 'string', 'max:20000'],
            'slot' => ['required', 'integer', 'min:0', 'max:29'],
            'tile' => ['required', 'integer', 'min:0', 'max:29'],
        ]);

        $result = OrderQuiz::tryTile($data['token'], (int) $data['slot'], (int) $data['tile']);
        abort_unless($result, 422);

        return response()->json($result)->header('Cache-Control', 'no-store');
    }

    public function check(Request $request)
    {
        $this->guard();

        $data = $request->validate([
            'token' => ['required', 'string', 'max:20000'],
            'placement' => ['required', 'array', 'min:1', 'max:30'],
            'placement.*' => ['nullable', 'integer', 'min:0', 'max:29'],
        ]);

        $result = OrderQuiz::check($data['token'], array_map(fn ($p) => $p === null ? null : (int) $p, $data['placement']));
        abort_unless($result, 422);

        return response()->json($result)->header('Cache-Control', 'no-store');
    }
}
