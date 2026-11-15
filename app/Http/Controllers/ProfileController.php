<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Comment;
use App\Models\NewsletterSubscriber;
use App\Models\Reaction;
use App\Models\SavedItem;
use App\Models\Setting;
use App\Models\SocialAccount;
use App\Models\User;
use App\Support\ImageStore;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * A person's own account: profile (name, about me, picture), sign-in and security (password, email, linked Google/Facebook),
 * and privacy (download my data, delete my account). Everything here works on the person who is signed in, never on someone else.
 */
class ProfileController extends Controller
{
    public function profile()
    {
        return view('account.profile', ['user' => auth()->user()]);
    }

    public function saveProfile(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'bio' => ['nullable', 'string', 'max:500'],
            'photo' => ['nullable', 'file', 'mimetypes:'.implode(',', ImageStore::TYPES), 'max:'.ImageStore::maxKb()],
        ]);

        $user->name = trim(strip_tags($data['name']));
        $user->bio = filled($data['bio'] ?? null) ? trim(strip_tags($data['bio'])) : null;

        if ($request->boolean('remove_photo') && $user->photo) {
            Storage::disk(ImageStore::DISK)->delete($user->photo);
            $user->photo = null;
        }
        if ($request->hasFile('photo')) {
            $new = ImageStore::store($request->file('photo'), 'profiles');
            if ($user->photo) {
                Storage::disk(ImageStore::DISK)->delete($user->photo);
            }
            $user->photo = $new;
        }
        $user->save();

        return redirect()->route('account.profile')->with('status', __('account.profile_saved'));
    }

    public function security()
    {
        $user = auth()->user();

        return view('account.security', ['user' => $user, 'socials' => SocialAccount::where('user_id', $user->id)->orderBy('provider')->get()]);
    }

    public function changePassword(Request $request)
    {
        $user = $request->user();
        $rules = ['password' => ['required', 'confirmed', Password::min(10)]];
        if ($user->hasOwnPassword()) {
            $rules['current_password'] = ['required', 'current_password'];
            $rules['password'][] = 'different:current_password';
        }
        $request->validate($rules, ['password.different' => __('account.password_same')]);

        $first = ! $user->hasOwnPassword();
        $user->password = $request->input('password');
        $user->setRememberToken(Str::random(60));
        $user->custom_data = collect($user->custom_data ?? [])->except('no_password')->all() ?: null;
        $user->save();

        return back()->with('status', $first ? __('account.password_set_saved') : __('account.password_saved'));
    }

    public function changeEmail(Request $request)
    {
        $user = $request->user();
        $rules = ['new_email' => ['required', 'email', 'max:255']];
        if ($user->hasOwnPassword()) {
            $rules['email_current_password'] = ['required', 'current_password'];
        }
        $data = $request->validate($rules, [], ['new_email' => 'new email', 'email_current_password' => 'password']);
        $new = mb_strtolower(trim($data['new_email']));

        if ($new === mb_strtolower($user->email)) {
            return back()->withErrors(['new_email' => __('account.email_same')])->withInput();
        }
        if (User::where('email', $new)->where('id', '!=', $user->id)->exists()) {
            return back()->withErrors(['new_email' => __('account.email_taken')])->withInput();
        }

        $old = $user->email;
        $user->email = $new;
        $user->email_verified_at = null;
        $user->save();

        // The old address is told, so a change nobody asked for is noticed.
        try {
            $site = (string) Setting::get('site_name', config('app.name'));
            Mail::raw(str_replace([':site', ':new'], [$site, $new], __('account.email_changed_notice')), fn ($m) => $m->to($old)->subject(__('account.email_changed_notice_subject')));
        } catch (\Throwable $e) {
            report($e);
        }
        VerifyEmailController::sendTo($user);

        return back()->with('status', str_replace(':email', $new, __('account.email_changed')));
    }

    public function unlink(Request $request, string $provider)
    {
        $user = $request->user();
        $link = SocialAccount::where('user_id', $user->id)->where('provider', $provider)->firstOrFail();
        $others = SocialAccount::where('user_id', $user->id)->where('id', '!=', $link->id)->count();
        if (! $user->hasOwnPassword() && $others === 0) {
            return back()->withErrors(['unlink' => __('account.signins_last')]);
        }
        $link->delete();

        return back()->with('status', str_replace(':provider', ucfirst($provider), __('account.signins_unlinked')));
    }

    public function privacy()
    {
        return view('account.privacy', ['user' => auth()->user()]);
    }

    /** Everything the site keeps about the person, as one file. */
    public function download(Request $request)
    {
        $user = $request->user();
        $data = [
            'made_on' => now()->toIso8601String(),
            'profile' => ['name' => $user->name, 'email' => $user->email, 'about_me' => $user->bio, 'has_picture' => (bool) $user->photo, 'role' => $user->role, 'email_confirmed_at' => $user->email_verified_at?->toIso8601String(), 'joined' => $user->created_at?->toIso8601String()],
            'linked_sign_ins' => SocialAccount::where('user_id', $user->id)->get(['provider', 'email', 'created_at'])->toArray(),
            'comments' => Comment::where('user_id', $user->id)->with('article')->orderBy('id')->get()->map(fn ($c) => ['on' => $c->article?->mainTitle(), 'written' => $c->created_at?->toIso8601String(), 'status' => $c->status, 'text' => $c->body])->all(),
            'likes_and_dislikes' => Reaction::where('user_id', $user->id)->with('reactable')->get()->map(fn ($r) => ['on' => $r->reactable?->mainTitle(), 'value' => $r->value === Reaction::LIKE ? 'like' : 'dislike'])->all(),
            'saved' => SavedItem::where('user_id', $user->id)->with('saveable')->get()->map(fn ($s) => ['item' => $s->saveable?->mainTitle()])->all(),
            'articles_sent_in' => Article::where('author_id', $user->id)->get()->map(fn ($a) => ['title' => $a->mainTitle(), 'status' => $a->status, 'code' => $a->code])->all(),
            'newsletter' => NewsletterSubscriber::where('email', $user->email)->exists() ? 'subscribed' : 'not subscribed',
        ];

        return response(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 200, [
            'Content-Type' => 'application/json; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="my-data.json"',
        ]);
    }

    public function destroy(Request $request)
    {
        $user = $request->user();
        abort_if($user->isStaff(), 403, __('account.delete_staff'));

        if ($user->hasOwnPassword()) {
            $request->validate(['delete_password' => ['required', 'current_password']], [], ['delete_password' => 'password']);
        } else {
            $request->validate(['delete_email' => ['required', Rule::in([$user->email])]], ['delete_email.in' => 'This is not your email address.'], ['delete_email' => 'email address']);
        }

        $id = $user->id;
        $email = $user->email;
        DB::transaction(function () use ($user, $id, $email) {
            // Drafts and articles waiting for review go; published ones stay, without a name.
            foreach (Article::where('author_id', $id)->whereIn('status', ['draft', 'written', 'in_review'])->get() as $a) {
                method_exists($a, 'forceDelete') ? $a->forceDelete() : $a->delete();
            }
            Article::where('author_id', $id)->update(['author_id' => null]);
            Comment::where('user_id', $id)->update(['user_id' => null]);   // kept, without a name, so the replies still make sense
            Reaction::where('user_id', $id)->delete();
            SavedItem::where('user_id', $id)->delete();
            SocialAccount::where('user_id', $id)->delete();
            NewsletterSubscriber::where('email', $email)->delete();
            if (Schema::hasTable('sessions')) {
                DB::table('sessions')->where('user_id', $id)->delete();
            }
            if ($user->photo) {
                Storage::disk(ImageStore::DISK)->delete($user->photo);
            }
        });

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        // Not written to the History: the history would keep the name and the email.
        User::withoutEvents(fn () => $user->delete());

        return redirect()->route('home')->with('status', __('account.delete_done'));
    }
}
