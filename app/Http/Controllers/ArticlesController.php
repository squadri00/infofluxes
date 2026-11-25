<?php

namespace App\Http\Controllers;

use App\Support\SiteArticles;
use App\Support\SiteControls;
use Illuminate\Http\Request;

/** "Read all articles": every published article with a search box and filters (category, contributor, sort order). */
class ArticlesController extends Controller
{
    public function __invoke(Request $request)
    {
        abort_unless(SiteControls::on('articles_page'), 404);

        return view('articles', ['list' => SiteArticles::build($request)]);
    }
}
