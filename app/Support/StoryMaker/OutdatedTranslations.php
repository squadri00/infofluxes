<?php

namespace App\Support\StoryMaker;

use App\Models\Story;
use App\Models\StoryChapter;
use App\Models\StorySlide;

/**
 * Watches the ENGLISH text of a story. When it changes (in the Story maker, or when somebody edits the story in Visual Stories), the languages that were
 * made from the old text no longer match: they are hidden and marked "out of date" (see Translations::markOutdated). A change in another language
 * (a translation being saved or edited) is not a change of the English text and does nothing here.
 * A new chapter or slide, or a removed one, also changes the English text.
 */
class OutdatedTranslations
{
    public static function register(): void
    {
        Story::updating(function (Story $story) {
            if (static::changed($story, ['title', 'short_detail'])) {
                Translations::markOutdated($story, true);
            }
        });

        StoryChapter::updating(function (StoryChapter $chapter) {
            if (static::changed($chapter, ['title'])) {
                static::mark($chapter->story);
            }
        });
        StoryChapter::created(fn (StoryChapter $chapter) => static::mark($chapter->story));
        StoryChapter::deleted(fn (StoryChapter $chapter) => static::mark($chapter->story));

        StorySlide::updating(function (StorySlide $slide) {
            if (static::changed($slide, ['heading', 'body'])) {
                static::mark($slide->chapter?->story);
            }
        });
        StorySlide::created(fn (StorySlide $slide) => static::mark($slide->chapter?->story));
        StorySlide::deleted(fn (StorySlide $slide) => static::mark($slide->chapter?->story));
    }

    private static function mark(?Story $story): void
    {
        if ($story && ($story->custom_data['translations'] ?? []) !== []) {
            Translations::markOutdated($story);
        }
    }

    /** Did the text of one of these fields change in the default language (English)? @param  list<string>  $fields */
    private static function changed($model, array $fields): bool
    {
        $locale = config('infofluxes.default_locale');
        foreach ($fields as $field) {
            if (! $model->isDirty($field)) {
                continue;
            }
            $original = $model->getOriginal($field);
            $old = is_array($original) ? $original : json_decode((string) $original, true);
            $old = is_array($old) ? ($old[$locale] ?? null) : null;
            $new = $model->getTranslation($field, $locale, false);
            if ((string) $old !== (string) $new) {
                return true;
            }
        }

        return false;
    }
}
