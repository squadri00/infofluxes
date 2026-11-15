<?php

namespace App\Http\Controllers;

use App\Support\Seo;

/** /robots.txt, made from the SEO settings (so the super user can change it without touching files). */
class RobotsController extends Controller
{
    public function __invoke()
    {
        return response(Seo::robotsTxt(), 200, ['Content-Type' => 'text/plain; charset=utf-8']);
    }
}
