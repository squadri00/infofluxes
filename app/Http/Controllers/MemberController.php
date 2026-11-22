<?php

namespace App\Http\Controllers;

use App\Models\Follow;
use App\Models\MemberNotification;
use App\Models\User;
use App\Support\ChallengeScores;
use App\Support\Following;
use App\Support\Notify;
use Illuminate\Http\Request;

/** The member's own pages: My scores, What I follow, Notifications, and the Follow buttons. */
class MemberController extends Controller
{
    public function scores()
    {
        return view('account.scores', ['best' => ChallengeScores::best(auth()->user()), 'history' => ChallengeScores::history(auth()->user())]);
    }

    public function following()
    {
        abort_unless(Following::on(), 404);
        $user = auth()->user();

        return view('account.following', [
            'follows' => Follow::query()->where('user_id', $user->id)->orderBy('kind')->orderByDesc('id')->get()->groupBy('kind'),
            'digest' => static::digestOn($user),
        ]);
    }

    /** Follow or unfollow a category or a contributor, then back to the page it came from. */
    public function follow(Request $request, string $kind, int $id)
    {
        abort_unless(Following::on() && auth()->user()->isActive(), 403);
        $result = Following::toggle($request->user(), $kind, $id);

        return $this->back($request, $result, $kind);
    }

    public function followKeyword(Request $request)
    {
        abort_unless(Following::on() && auth()->user()->isActive(), 403);
        $word = (string) $request->validate(['keyword' => ['required', 'string', 'max:60']])['keyword'];
        $word = Following::clean($word);
        // Adding a word that is already followed does not take it away: only the "Stop following" button does.
        $result = Following::is($request->user(), 'keyword', $word) && ! $request->boolean('toggle') ? true : Following::toggle($request->user(), 'keyword', $word);

        return $this->back($request, $result, 'keyword');
    }

    public function digest(Request $request)
    {
        $user = $request->user();
        $custom = (array) $user->custom_data;
        $custom['digest'] = $request->boolean('digest');
        $user->custom_data = $custom;
        $user->save();

        return redirect()->route('account.following')->with('status', __('account.digest_saved'));
    }

    /** The link in the email: the weekly email is switched off. */
    public function digestOff(Request $request, User $user)
    {
        $custom = (array) $user->custom_data;
        $custom['digest'] = false;
        $user->custom_data = $custom;
        $user->save();

        return redirect()->route('home')->with('status', __('account.digest_off_done'));
    }

    public function notifications()
    {
        abort_unless(Notify::on(), 404);
        $user = auth()->user();
        $items = MemberNotification::query()->where('user_id', $user->id)->orderByDesc('id')->paginate(30);

        return view('account.notifications', ['items' => $items, 'unread' => Notify::unread($user)]);
    }

    /** A notice is opened: it is marked as read and the person goes to the page it is about. */
    public function open(int $id)
    {
        $note = MemberNotification::query()->where('user_id', auth()->id())->findOrFail($id);
        if ($note->isNew()) {
            $note->update(['read_at' => now()]);
        }

        return redirect($note->url ?: route('account.notifications'));
    }

    public function readAll()
    {
        MemberNotification::query()->where('user_id', auth()->id())->unread()->update(['read_at' => now()]);

        return redirect()->route('account.notifications');
    }

    /** The weekly email is on unless the member switched it off. */
    public static function digestOn(User $user): bool
    {
        return ($user->custom_data['digest'] ?? true) !== false;
    }

    private function back(Request $request, ?bool $result, string $kind)
    {
        $to = $request->input('back');
        // Only a page of this site is allowed as the way back.
        $to = is_string($to) && str_starts_with($to, '/') && ! str_starts_with($to, '//') ? $to : url()->previous();
        $key = match (true) {
            $result === null => 'account.follow_refused',
            $result === true => 'account.follow_on',
            default => 'account.follow_off',
        };

        return redirect($to)->with('status', __($key, ['max' => Follow::MAX]));
    }
}
