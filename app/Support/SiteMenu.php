<?php

namespace App\Support;

use App\Models\Category;

/** The main menu is the category tree: every visible top category, with its visible sub-categories in the drop-down. */
class SiteMenu
{
    /** @return array<int, array{label: string, url: string, children: array<int, array{label: string, url: string}>}> */
    public static function header(): array
    {
        $max = 12;
        $roots = Category::query()->whereNull('parent_id')->where('is_visible', true)->orderBy('sort_order')->orderBy('id')->get();
        $out = [];
        foreach ($roots as $root) {
            $children = [];
            foreach ($root->children()->where('is_visible', true)->limit($max)->get() as $child) {
                $children[] = ['label' => $child->name, 'url' => route('category.show', $child->slug)];
            }
            $out[] = ['label' => $root->name, 'url' => route('category.show', $root->slug), 'children' => $children];
        }

        return $out;
    }
}
