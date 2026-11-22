<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * Create: one door for making new content. A person chooses an article or a story in pictures, made with the AI,
 * and the right tool opens. The same choices are in the "+ Create" button at the top of every screen.
 */
class CreateContent extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPlusCircle;

    protected static string|UnitEnum|null $navigationGroup = null;

    protected static ?int $navigationSort = -1;

    protected static ?string $navigationLabel = 'Create';

    protected static ?string $title = 'Create';

    protected static ?string $slug = 'create';

    protected string $view = 'filament.pages.create-content';

    /**
     * The choices this person may use: [key, title, help, url, group] where group is "article" or "story".
     *
     * @return list<array{key: string, title: string, help: string, url: string, group: string}>
     */
    public static function options(): array
    {
        $out = [];
        $add = function (string $key, string $group, bool $allowed, string $url) use (&$out) {
            if ($allowed) {
                $out[] = ['key' => $key, 'group' => $group, 'title' => __('admin.create_'.$key), 'help' => __('admin.create_'.$key.'_help'), 'url' => $url];
            }
        };

        // Only the AI ways: they keep every article and story in the same shape. Writing a free form by hand is not offered here.
        $add('article_ai', 'article', AiImport::canAccess(), AiImport::getUrl());
        $add('story_ai', 'story', StoryMaker::canAccess(), StoryMaker::getUrl());

        return $out;
    }

    public static function canAccess(): bool
    {
        return static::options() !== [];
    }
}
