<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Person;

/** The contributors: the list, and a page for each one whose "public page" switch is on (staff may preview the others). */
class PersonController extends Controller
{
    private function isStaff(): bool
    {
        return auth()->check() && auth()->user()->canAccessPanel(\Filament\Facades\Filament::getPanel('admin'));
    }

    public function index()
    {
        $people = Person::query()->where('show_page', true)->orderBy('name')->get();

        return view('person.index', ['people' => $people]);
    }

    public function show(string $slug)
    {
        $preview = $this->isStaff();

        $person = Person::query()->where('slug', $slug)->when(! $preview, fn ($q) => $q->where('show_page', true))->firstOrFail();

        $articles = Article::published()->where('contributor_id', $person->id)->with('category')->withCount('slides')
            ->orderByDesc('publish_date')->orderByDesc('id')->get();

        return view('person.show', ['person' => $person, 'articles' => $articles, 'preview' => $preview && ! $person->show_page]);
    }
}
