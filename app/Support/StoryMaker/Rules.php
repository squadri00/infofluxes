<?php

namespace App\Support\StoryMaker;

use App\Models\Setting;

/**
 * The rules of the Story maker. They start as the file resources/prompts/story/master.txt and the super user can change them
 * on the Story maker screen ("Reset to the original" puts the file's words back). They have nothing to do with the AI prompts of the articles.
 */
class Rules
{
    public const KEY = 'storymaker_rules';

    public const STYLE_KEY = 'storymaker_style';

    public const DEFAULT_STYLE = 'Warm, soft illustration with gentle light and calm colours. Wide 16:9 picture. No words or letters in the picture. No faces of prophets, companions or angels.';

    public static function original(): string
    {
        return trim((string) file_get_contents(resource_path('prompts/story/master.txt')));
    }

    public static function master(): string
    {
        $saved = trim((string) Setting::get(self::KEY));

        return $saved !== '' ? $saved : static::original();
    }

    public static function isChanged(): bool
    {
        return trim((string) Setting::get(self::KEY)) !== '';
    }

    public static function save(string $text): void
    {
        $text = trim(str_replace("\r\n", "\n", $text));
        // The original is not stored again: the file stays the one source until the super user really changes it.
        $text === '' || $text === static::original() ? Setting::query()->where('key', self::KEY)->delete() : Setting::put(self::KEY, $text);
        Setting::forget();
    }

    public static function reset(): void
    {
        Setting::query()->where('key', self::KEY)->delete();
        Setting::forget();
    }

    public static function style(): string
    {
        $saved = trim((string) Setting::get(self::STYLE_KEY));

        return $saved !== '' ? $saved : self::DEFAULT_STYLE;
    }

    public static function saveStyle(string $text): void
    {
        $text = trim(preg_replace('/\s+/', ' ', $text));
        $text === '' || $text === self::DEFAULT_STYLE ? Setting::query()->where('key', self::STYLE_KEY)->delete() : Setting::put(self::STYLE_KEY, $text);
        Setting::forget();
    }
}
