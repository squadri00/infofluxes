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

    // ---------- step 2: translating a story ----------

    public const TRANSLATION_KEY = 'storymaker_translation_rules';

    public const LANGUAGES_KEY = 'storymaker_languages';

    public static function translationOriginal(): string
    {
        return trim((string) file_get_contents(resource_path('prompts/story/translation.txt')));
    }

    public static function translation(): string
    {
        $saved = trim((string) Setting::get(self::TRANSLATION_KEY));

        return $saved !== '' ? $saved : static::translationOriginal();
    }

    public static function translationChanged(): bool
    {
        return trim((string) Setting::get(self::TRANSLATION_KEY)) !== '';
    }

    public static function saveTranslation(string $text): void
    {
        $text = trim(str_replace("\r\n", "\n", $text));
        $text === '' || $text === static::translationOriginal() ? Setting::query()->where('key', self::TRANSLATION_KEY)->delete() : Setting::put(self::TRANSLATION_KEY, $text);
        Setting::forget();
    }

    public static function resetTranslation(): void
    {
        Setting::query()->where('key', self::TRANSLATION_KEY)->delete();
        Setting::forget();
    }

    /** The first drafts, from resources/prompts/story/languages.php. @return array<string, array{name: string, native: string, rtl: bool, quran: string, words: string}> */
    public static function languageDefaults(): array
    {
        $out = [];
        foreach ((array) require resource_path('prompts/story/languages.php') as $code => [$name, $native, $rtl, $quran, $words]) {
            $out[$code] = ['name' => $name, 'native' => $native, 'rtl' => (bool) $rtl, 'quran' => $quran, 'words' => $words];
        }

        return $out;
    }

    /** The languages with the changes of the super user on top of the drafts. @return array<string, array{name: string, native: string, rtl: bool, quran: string, words: string}> */
    public static function languages(): array
    {
        $saved = (array) Setting::get(self::LANGUAGES_KEY, []);
        $out = static::languageDefaults();
        foreach ($out as $code => $row) {
            if (isset($saved[$code]) && is_array($saved[$code])) {
                $out[$code]['quran'] = trim((string) ($saved[$code]['quran'] ?? $row['quran']));
                $out[$code]['words'] = trim((string) ($saved[$code]['words'] ?? $row['words']));
            }
        }

        return $out;
    }

    public static function saveLanguage(string $code, string $quran, string $words): void
    {
        if (! isset(static::languageDefaults()[$code])) {
            return;
        }
        $saved = (array) Setting::get(self::LANGUAGES_KEY, []);
        $saved[$code] = ['quran' => trim($quran), 'words' => trim(str_replace("\r\n", "\n", $words))];
        $default = static::languageDefaults()[$code];
        if ($saved[$code] === ['quran' => $default['quran'], 'words' => $default['words']]) {
            unset($saved[$code]);
        }
        $saved === [] ? Setting::query()->where('key', self::LANGUAGES_KEY)->delete() : Setting::put(self::LANGUAGES_KEY, $saved);
        Setting::forget();
    }

    public static function resetLanguage(string $code): void
    {
        $saved = (array) Setting::get(self::LANGUAGES_KEY, []);
        unset($saved[$code]);
        $saved === [] ? Setting::query()->where('key', self::LANGUAGES_KEY)->delete() : Setting::put(self::LANGUAGES_KEY, $saved);
        Setting::forget();
    }
    // ---------- read the whole story before the draft (optional) ----------

    public const REQUIRE_READ_KEY = 'storymaker_require_read';

    /** When on, "Save as a draft" waits until the whole story was opened in the reading panel. Off unless the super user switches it on. */
    public static function requireRead(): bool
    {
        return (string) Setting::get(self::REQUIRE_READ_KEY, '0') === '1';
    }

    public static function saveRequireRead(bool $on): void
    {
        $on ? Setting::put(self::REQUIRE_READ_KEY, '1') : Setting::query()->where('key', self::REQUIRE_READ_KEY)->delete();
        Setting::forget();
    }

    // ---------- how long each slide is ----------

    public const LENGTHS_KEY = 'storymaker_lengths';

    /** The three levels and their usual number of words in the BODY of a slide. The super user can change the numbers. */
    public const LENGTH_DEFAULTS = [
        'short' => ['label' => 'Short', 'min' => 25, 'max' => 45, 'extra' => false, 'about' => 'quick captions'],
        'story' => ['label' => 'Story', 'min' => 60, 'max' => 110, 'extra' => false, 'about' => 'a told story, a scene in each slide'],
        'extra' => ['label' => 'Story + extra', 'min' => 90, 'max' => 150, 'extra' => true, 'about' => 'a told story, plus one short "good to know" line when it helps'],
    ];

    /** @return array<string, array{label: string, min: int, max: int, extra: bool, about: string}> */
    public static function lengths(): array
    {
        $saved = (array) Setting::get(self::LENGTHS_KEY, []);
        $out = self::LENGTH_DEFAULTS;
        foreach ($out as $code => $row) {
            $min = (int) ($saved[$code]['min'] ?? $row['min']);
            $max = (int) ($saved[$code]['max'] ?? $row['max']);
            if ($min >= 10 && $max <= 400 && $max > $min) {
                $out[$code]['min'] = $min;
                $out[$code]['max'] = $max;
            }
        }

        return $out;
    }

    /** One level; anything unknown is the short one (the way stories were always made). */
    public static function length(?string $code): array
    {
        $all = static::lengths();

        return ['code' => isset($all[(string) $code]) ? (string) $code : 'short'] + $all[isset($all[(string) $code]) ? (string) $code : 'short'];
    }

    /** "2 to 4" for 25 to 45 words: about 10 words in a sentence, never fewer than 1 sentence. */
    public static function sentenceRange(int $min, int $max): string
    {
        $low = max(1, (int) round($min / 11));
        $high = max($low + 1, (int) round($max / 9));

        return $low.' to '.$high;
    }

    /** @param  array<string, array{min?: mixed, max?: mixed}>  $rows */
    public static function saveLengths(array $rows): void
    {
        $saved = [];
        foreach (self::LENGTH_DEFAULTS as $code => $row) {
            $min = (int) ($rows[$code]['min'] ?? $row['min']);
            $max = (int) ($rows[$code]['max'] ?? $row['max']);
            if ($min >= 10 && $max <= 400 && $max > $min && ($min !== $row['min'] || $max !== $row['max'])) {
                $saved[$code] = ['min' => $min, 'max' => $max];
            }
        }
        $saved === [] ? Setting::query()->where('key', self::LENGTHS_KEY)->delete() : Setting::put(self::LENGTHS_KEY, $saved);
        Setting::forget();
    }

    public static function resetLengths(): void
    {
        Setting::query()->where('key', self::LENGTHS_KEY)->delete();
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
