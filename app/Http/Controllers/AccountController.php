<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Category;
use App\Support\Html;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** A logged-in person: their account page and the articles they send in. A new article waits for a moderator. */
class AccountController extends Controller
{
    public function index()
    {
        return view('account.index', [
            'articles' => Article::where('author_id', auth()->id())->orderByDesc('id')->get(),
            'staff' => auth()->user()->isStaff(),
        ]);
    }

    public function create()
    {
        abort_unless(auth()->user()->isActive(), 403);

        return view('account.submit', ['article' => null, 'categories' => Category::publicChoices()]);
    }

    public function store(Request $request)
    {
        abort_unless(auth()->user()->isActive(), 403);
        $data = $this->validated($request);

        $article = Article::create([
            'title' => [config('infofluxes.default_locale') => trim($data['title'])],
            'slug' => Article::uniqueSlug($data['title']),
            'category_id' => $data['category_id'],
            'short_detail' => [config('infofluxes.default_locale') => trim((string) ($data['short_detail'] ?? ''))],
            'long_detail' => [config('infofluxes.default_locale') => Html::fromPlain($data['long_detail'] ?? '')],
            'source_line' => $data['source_line'] ?? null,
            'search_words' => $this->words($data['search_words'] ?? ''),
            'author_id' => auth()->id(),
            'status' => 'in_review',     // a person's article always waits for a moderator
        ]);

        return redirect()->route('account')->with('status', __('site.submitted_notice'));
    }

    public function edit(Article $article)
    {
        $this->authorizeOwn($article);

        return view('account.submit', ['article' => $article, 'categories' => Category::publicChoices()]);
    }

    public function update(Request $request, Article $article)
    {
        $this->authorizeOwn($article);
        $data = $this->validated($request);
        $locale = config('infofluxes.default_locale');

        $article->setTranslation('title', $locale, trim($data['title']));
        $article->setTranslation('short_detail', $locale, trim((string) ($data['short_detail'] ?? '')));
        $article->setTranslation('long_detail', $locale, Html::fromPlain($data['long_detail'] ?? ''));
        $article->fill([
            'category_id' => $data['category_id'],
            'source_line' => $data['source_line'] ?? null,
            'search_words' => $this->words($data['search_words'] ?? ''),
            'status' => 'in_review',     // an edited article goes back to the moderator
        ])->save();

        return redirect()->route('account')->with('status', __('site.saved_notice'));
    }

    /** A person edits only their own article, and only while it is not published. */
    private function authorizeOwn(Article $article): void
    {
        abort_unless($article->author_id === auth()->id() && in_array($article->status, ['draft', 'in_review'], true) && auth()->user()->isActive(), 403);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'category_id' => ['required', 'integer', Rule::in(array_keys(Category::publicChoices()))],
            'short_detail' => ['nullable', 'string', 'max:600'],
            'long_detail' => ['nullable', 'string', 'max:30000'],
            'source_line' => ['nullable', 'string', 'max:500'],
            'search_words' => ['nullable', 'string', 'max:600'],
        ]);
    }

    /** "one, two words, three" becomes a clean list (at most 20 words, each at most 60 letters). */
    private function words(string $text): ?array
    {
        $words = collect(explode(',', $text))->map(fn ($w) => trim(mb_substr(trim($w), 0, 60)))->filter()->unique()->take(20)->values()->all();

        return $words === [] ? null : $words;
    }
}
