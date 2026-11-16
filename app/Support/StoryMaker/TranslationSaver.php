<?php

namespace App\Support\StoryMaker;

use App\Models\Story;
use Illuminate\Support\Facades\DB;

/**
 * Saves a checked translation into the boxes of one language of the story: the title, the short text, the Google lines,
 * the chapter titles and the heading, text and picture description of every slide. English is never touched.
 * A saved language is hidden until it is switched on (see Translations).
 */
class TranslationSaver
{
    /**
     * @param  array<string, mixed>  $data  the "data" part of one language of TranslationReader::read()
     */
    public static function save(Story $story, string $code, array $data, ?int $runId = null): void
    {
        $en = config('infofluxes.default_locale');
        if ($code === $en || ! isset(Rules::languages()[$code])) {
            throw new \InvalidArgumentException('This language cannot be saved here.');
        }

        DB::transaction(function () use ($story, $code, $data, $runId) {
            $story->setTranslation('title', $code, $data['title']);
            filled($data['short']) ? $story->setTranslation('short_detail', $code, $data['short']) : null;
            filled($data['meta_title'] ?? null) ? $story->setTranslation('meta_title', $code, $data['meta_title']) : null;
            filled($data['meta_description'] ?? null) ? $story->setTranslation('meta_description', $code, $data['meta_description']) : null;
            $story->save();

            $chapters = $story->chapters()->with('slides')->get()->values();
            foreach (array_values($data['chapters']) as $ci => $c) {
                $chapter = $chapters[$ci] ?? null;
                if (! $chapter) {
                    continue;
                }
                filled($c['title']) ? $chapter->setTranslation('title', $code, $c['title']) : null;
                $chapter->save();
                $slides = $chapter->slides->values();
                foreach (array_values($c['slides']) as $si => $s) {
                    $slide = $slides[$si] ?? null;
                    if (! $slide) {
                        continue;
                    }
                    $slide->setTranslation('heading', $code, $s['heading']);
                    $slide->setTranslation('body', $code, $s['body']);
                    filled($s['alt']) ? $slide->setTranslation('image_alt', $code, $s['alt']) : null;
                    $slide->save();
                }
            }

            Translations::saved($story->fresh(), $code, $runId, $data['keywords'] ?? []);
        });
    }
}
