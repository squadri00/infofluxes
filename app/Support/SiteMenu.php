<?php

namespace App\Support;

use App\Models\Category;
use Illuminate\Support\Str;

/**
 * The main menu is the category tree: every visible top category, with its visible sub-categories.
 * On a big screen a top category with sub-categories opens a wide panel: the sub-categories (with icon and short description)
 * on the left, and a feature card on the right. The card is the one chosen on the category; without one it is made from the category itself.
 */
class SiteMenu
{
    /**
     * @return array<int, array{label: string, url: string, children: array<int, array{label: string, url: string, description: string, icon: string}>, feature: array|null}>
     */
    public static function header(): array
    {
        $max = 12;
        $roots = Category::query()->whereNull('parent_id')->where('is_visible', true)->orderBy('sort_order')->orderBy('id')->get();
        $out = [];
        foreach ($roots as $root) {
            $children = [];
            foreach ($root->children()->where('is_visible', true)->limit($max)->get() as $child) {
                $children[] = [
                    'label' => $child->name,
                    'url' => route('category.show', $child->slug),
                    'description' => static::plain($child->description, 90),
                    'icon' => (string) $child->menu_icon,
                ];
            }
            $out[] = [
                'label' => $root->name,
                'url' => route('category.show', $root->slug),
                'children' => $children,
                'feature' => $children ? static::feature($root) : null,
            ];
        }

        return $out;
    }

    /** The card on the right of the panel. Every part that is left empty falls back to the category itself. */
    public static function feature(Category $root): array
    {
        $own = static::safeUrl((string) $root->feature_url);
        $image = $root->feature_image ?: $root->image;

        return [
            'title' => trim((string) $root->feature_title) ?: (string) $root->name,
            'text' => trim((string) $root->feature_text) ?: static::plain($root->description, 120),
            'button' => trim((string) $root->feature_button) ?: __('site.mega_explore'),
            'url' => $own ?: route('category.show', $root->slug),
            'image' => $image ? asset('storage/'.$image) : null,
            'alt' => $root->feature_image ? '' : (string) $root->image_alt,
        ];
    }

    private static function plain(?string $html, int $limit): string
    {
        return Str::limit(trim(preg_replace('/\s+/u', ' ', strip_tags((string) $html))), $limit);
    }

    /** An address of this site (starts with one slash) or a web address (https). Anything else is not used. */
    public static function safeUrl(string $url): ?string
    {
        $url = trim($url);

        return preg_match('~^(/(?!/)|https?://)~i', $url) ? $url : null;
    }
}
