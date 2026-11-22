<?php

namespace App\Http\Controllers;

use App\Support\Gallery;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

/**
 * The picture gallery: pictures from the whole site in a random order that stays the same while a visitor goes through the pages.
 * A picture leads to the page it belongs to. Filters: the kind of page and the section (top-level category). 25, 50, 100 or 200 pictures a page.
 */
class GalleryController extends Controller
{
    public function __invoke(Request $request)
    {
        abort_unless(Gallery::on(), 404);

        // "Shuffle": a new random order, from the first page.
        if ($request->boolean('shuffle')) {
            session()->forget('gallery_seed');

            return redirect()->route('gallery', $request->only(['kind', 'section', 'per']));
        }
        $seed = (string) session('gallery_seed');
        if ($seed === '') {
            $seed = Str::random(8);
            session(['gallery_seed' => $seed]);
        }

        $all = Gallery::pictures();
        $counts = Gallery::counts($all);
        $kind = in_array($request->query('kind'), Gallery::KINDS, true) ? (string) $request->query('kind') : null;
        $sectionSlug = (string) $request->query('section', '');
        $section = collect($counts['sections'])->firstWhere('slug', $sectionSlug)['id'] ?? null;
        $per = in_array((int) $request->query('per'), Gallery::PER_PAGE, true) ? (int) $request->query('per') : Gallery::DEFAULT_PER;

        $list = Gallery::shuffled(Gallery::filtered($all, $kind, $section), $seed);
        $total = count($list);
        $last = max(1, (int) ceil($total / $per));
        $page = min(max(1, (int) $request->query('page', 1)), $last);
        $paginator = new LengthAwarePaginator(array_slice($list, ($page - 1) * $per, $per), $total, $per, $page, [
            'path' => route('gallery'),
            'query' => array_filter(['kind' => $kind, 'section' => $section ? $sectionSlug : null, 'per' => $per !== Gallery::DEFAULT_PER ? $per : null]),
        ]);

        return view('gallery', [
            'pictures' => $paginator->items(),
            'paginator' => $paginator,
            'counts' => $counts,
            'kind' => $kind,
            'sectionSlug' => $section ? $sectionSlug : null,
            'per' => $per,
            'total' => $total,
            // The one picture behind the banner (set it with the setting gallery_hero_image; the usual one is in public/images).
            'heroImage' => asset(ltrim((string) (\App\Models\Setting::get('gallery_hero_image') ?: 'images/gallery-hero.webp'), '/')),
            'all' => count($all),
        ]);
    }
}
