<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Reaction;
use App\Models\SavedItem;
use App\Models\Story;
use Illuminate\Http\Request;

/** Like, dislike and save, for people who are logged in. Pressing the same button again takes it back. */
class EngageController extends Controller
{
    private const TYPES = ['article' => Article::class, 'story' => Story::class];

    public function react(Request $request, string $type, int $id)
    {
        abort_unless(\App\Support\SiteControls::on('reactions'), 404);
        abort_unless(auth()->user()->isActive(), 403);
        $value = $request->validate(['value' => ['required', 'in:like,dislike']])['value'] === 'like' ? Reaction::LIKE : Reaction::DISLIKE;
        $item = $this->find($type, $id);

        $mine = $item->reactions()->where('user_id', auth()->id())->first();
        if ($mine && $mine->value === $value) {
            $mine->delete();                       // the same button again: taken back
        } elseif ($mine) {
            $mine->update(['value' => $value]);    // the other button: changed
        } else {
            $item->reactions()->create(['user_id' => auth()->id(), 'value' => $value]);
        }

        return $this->back($request, $type, $item);
    }

    public function save(Request $request, string $type, int $id)
    {
        abort_unless(\App\Support\SiteControls::on('save'), 404);
        abort_unless(auth()->user()->isActive(), 403);
        $item = $this->find($type, $id);

        $saved = $item->saves()->where('user_id', auth()->id())->first();
        if ($saved) {
            $saved->delete();
        } else {
            $item->saves()->create(['user_id' => auth()->id()]);
        }

        return $this->back($request, $type, $item);
    }

    /** Only a published item can be reacted to or saved. */
    private function find(string $type, int $id): Article|Story
    {
        $class = self::TYPES[$type] ?? null;
        abort_if($class === null, 404);

        return $class::published()->findOrFail($id);
    }

    /** Back to the page the button was on (the account page, or the item itself at the buttons). */
    private function back(Request $request, string $type, Article|Story $item)
    {
        if ($request->input('from') === 'account') {
            return redirect()->route('account');
        }
        $url = $type === 'story' ? $item->url() : route('article.show', $item->slug);

        return redirect()->to($url.'#engage');
    }
}
