<?php

use App\Http\Controllers\ArticleController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LanguageController;
use App\Http\Controllers\ReferenceController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SitemapController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::get('/search', SearchController::class)->name('search');
Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');
Route::get('/language/{code}', LanguageController::class)->name('language.switch');
Route::get('/category/{slug}', CategoryController::class)->name('category.show');
// A reference code (A-42 or C-12) opens the article or category it belongs to.
Route::get('/id/{code}', ReferenceController::class)->where('code', '[AaCc]-[0-9]+')->name('reference');

// An article lives at /its-address. This must stay the last route.
Route::get('/{slug}', ArticleController::class)->where('slug', '[A-Za-z0-9][A-Za-z0-9_-]*')->name('article.show');
