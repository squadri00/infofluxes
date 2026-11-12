<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Category;

class HomeController extends Controller
{
    public function __invoke()
    {
        return view('home', [
            'categories' => Category::query()->whereNull('parent_id')->where('is_visible', true)->orderBy('sort_order')->orderBy('id')->get(),
            'articles' => Article::published()->with('category')->orderByDesc('publish_date')->orderByDesc('id')->limit(12)->get(),
        ]);
    }
}
