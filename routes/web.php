<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\ArticleController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChallengeController;
use App\Http\Controllers\FactQuizController;
use App\Http\Controllers\OrderQuizController;
use App\Http\Controllers\QuizController;
use App\Http\Controllers\ScriptureQuizController;
use App\Http\Controllers\WhoQuizController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LanguageController;
use App\Http\Controllers\ReferenceController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\StoryBookController;
use App\Http\Controllers\SitemapController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::get('/search', SearchController::class)->name('search');
Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');
Route::get('/language/{code}', LanguageController::class)->name('language.switch');
Route::get('/category/{slug}', CategoryController::class)->name('category.show');
// People: log in, sign up, log out, my account, submit an article, post a comment.
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
});
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
Route::middleware('auth')->group(function () {
    Route::get('/account', [AccountController::class, 'index'])->name('account');
    Route::get('/submit', [AccountController::class, 'create'])->name('submit');
    Route::post('/submit', [AccountController::class, 'store'])->middleware('throttle:10,1');
    Route::get('/submit/{article}', [AccountController::class, 'edit'])->whereNumber('article')->name('submit.edit');
    Route::post('/submit/{article}', [AccountController::class, 'update'])->whereNumber('article')->middleware('throttle:20,1');
    Route::post('/comments', [CommentController::class, 'store'])->middleware('throttle:10,1')->name('comments.store');
});

// Challenge yourself: the tests, and the answers they ask for (JSON, for the page script).
Route::get('/challenge-yourself', [ChallengeController::class, 'index'])->name('challenge');
Route::get('/challenge-yourself/{test}', [ChallengeController::class, 'show'])->where('test', 'quran|scripture|facts|who|order')->name('challenge.show');
Route::prefix('quiz')->middleware('throttle:quiz')->group(function () {
    Route::get('start', [QuizController::class, 'start'])->name('quiz.start');
    Route::post('answer', [QuizController::class, 'answer'])->name('quiz.answer');
    Route::post('finish', [QuizController::class, 'finish'])->name('quiz.finish');
    Route::get('scripture/start', [ScriptureQuizController::class, 'start'])->name('scripture.start');
    Route::post('scripture/answer', [ScriptureQuizController::class, 'answer'])->name('scripture.answer');
    Route::post('scripture/finish', [ScriptureQuizController::class, 'finish'])->name('scripture.finish');
    Route::get('facts/start', [FactQuizController::class, 'start'])->name('facts.start');
    Route::post('facts/answer', [FactQuizController::class, 'answer'])->name('facts.answer');
    Route::post('facts/finish', [FactQuizController::class, 'finish'])->name('facts.finish');
    Route::get('who/start', [WhoQuizController::class, 'start'])->name('who.start');
    Route::post('who/answer', [WhoQuizController::class, 'answer'])->name('who.answer');
    Route::post('who/finish', [WhoQuizController::class, 'finish'])->name('who.finish');
    Route::get('order/start', [OrderQuizController::class, 'start'])->name('order.start');
    Route::post('order/check', [OrderQuizController::class, 'check'])->name('order.check');
    Route::post('order/try', [OrderQuizController::class, 'tryTile'])->name('order.try');
});

// A reference code (A-42 or C-12) opens the article or category it belongs to.
Route::get('/id/{code}', ReferenceController::class)->where('code', '[AaCc]-[0-9]+')->name('reference');

// The PDF book of an article with story slides.
Route::get('/{slug}/book', StoryBookController::class)->where('slug', '[A-Za-z0-9][A-Za-z0-9_-]*')->middleware('throttle:6,1')->name('article.book');

// An article lives at /its-address. This must stay the last route.
Route::get('/{slug}', ArticleController::class)->where('slug', '[A-Za-z0-9][A-Za-z0-9_-]*')->name('article.show');
