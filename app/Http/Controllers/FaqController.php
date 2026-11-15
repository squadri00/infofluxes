<?php

namespace App\Http\Controllers;

use App\Models\Faq;
use App\Support\Html;

/** The FAQ page: every shown question as an accordion, listed under its heading. */
class FaqController extends Controller
{
    public function __invoke()
    {
        $locale = config('infofluxes.default_locale');
        $items = Faq::active()->orderBy('sort_order')->orderBy('id')->get()->map(fn (Faq $f) => [
            'id' => $f->id,
            'question' => $f->question,
            'answer' => Html::clean((string) $f->answer),
            'group' => trim((string) $f->getTranslation('group', app()->getLocale(), true)),
        ]);

        // Headings in the order of their first question; questions without a heading come first, with no title.
        $groups = $items->groupBy('group')->sortBy(fn ($rows, $name) => $name === '' ? -1 : $rows->keys()->first());

        return view('faq', ['groups' => $groups, 'count' => $items->count(), 'items' => $items]);
    }
}
