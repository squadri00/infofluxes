<?php

namespace App\Http\Controllers;

use App\Models\Clip;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * The still picture of a clip (shown in the menus and on the Clips page, so no video has to load before a visitor wants it).
 * The server has no video tool, so the Clips screen of the admin makes the picture from the video in the browser and sends it here.
 */
class ClipPosterController extends Controller
{
    /** The clips that have no picture yet. */
    public function index(Request $request)
    {
        abort_unless($request->user()?->canPublish(), 403);

        $todo = Clip::query()->orderBy('id')->get()
            ->filter(fn (Clip $c) => $c->exists() && $c->posterUrl() === null)
            ->map(fn (Clip $c) => ['id' => $c->id, 'src' => $c->src(), 'store' => route('clip-posters.store', $c)])
            ->values();

        return response()->json($todo);
    }

    public function store(Request $request, Clip $clip)
    {
        abort_unless($request->user()?->canPublish(), 403);
        $request->validate(['picture' => ['required', 'file', 'max:600']]);

        $bytes = (string) file_get_contents($request->file('picture')->getRealPath());
        $info = @getimagesizefromstring($bytes);
        abort_unless($info && $info[2] === IMAGETYPE_JPEG && $info[0] >= 100 && $info[0] <= 2000, 422, 'That is not a picture of the right kind.');

        Storage::disk('public')->put($clip->posterPath(), $bytes, 'public');

        return response()->json(['ok' => true]);
    }
}
