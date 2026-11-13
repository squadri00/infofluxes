<?php

namespace App\Http\Controllers;

use App\Models\Page;

/** A website page (About, Privacy...). Reached from the catch-all route when no article has that address. */
class PageController extends Controller
{
    public function show(string $slug)
    {
        $preview = auth()->check() && auth()->user()->canAccessPanel(\Filament\Facades\Filament::getPanel('admin'));

        $page = Page::query()->where('slug', $slug)->when(! $preview, fn ($q) => $q->published())->firstOrFail();

        return view('page', ['page' => $page, 'preview' => $preview && $page->status !== 'published']);
    }
}
