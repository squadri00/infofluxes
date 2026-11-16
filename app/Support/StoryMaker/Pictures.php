<?php

namespace App\Support\StoryMaker;

use App\Models\Story;
use App\Support\StoryImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Bulk pictures for a story: many files are dropped at once and each goes to its place, in order.
 * The first file may be the cover. The others go to the slides that still have no picture (or to all slides when asked), in the order of the files.
 * Every picture is made the same way as a picture added on the story screen (App\Support\StoryImage: a light WebP in the story shape).
 */
class Pictures
{
    public const MAX_FILES = 80;

    /**
     * The places a picture can go: the cover first, then every slide in reading order.
     *
     * @return list<array{type: string, label: string, heading: string, picture: string, has: bool, slide_id: int|null}>
     */
    public static function slots(Story $story): array
    {
        $story->loadMissing('chapters.slides');
        $slots = [['type' => 'cover', 'label' => 'Cover', 'heading' => $story->mainTitle(), 'picture' => '', 'has' => filled($story->main_image), 'slide_id' => null]];
        $n = 0;
        foreach ($story->chapters as $chapter) {
            foreach ($chapter->slides as $slide) {
                $n++;
                $slots[] = [
                    'type' => 'slide', 'label' => 'Slide '.$n, 'heading' => (string) $slide->getTranslation('heading', config('infofluxes.default_locale'), true),
                    'picture' => (string) ($slide->custom_data['picture'] ?? ''), 'has' => filled($slide->image), 'slide_id' => $slide->id,
                ];
            }
        }

        return $slots;
    }

    /** The files in the order they will be used: by file name (numbers read as numbers: 2 before 10), or as they were chosen. */
    public static function order(array $files, string $mode): array
    {
        $files = array_values($files);
        if ($mode === 'name') {
            usort($files, fn ($a, $b) => strnatcasecmp(static::nameOf($a), static::nameOf($b)));
        }

        return $files;
    }

    private static function nameOf(mixed $file): string
    {
        return $file instanceof UploadedFile ? $file->getClientOriginalName() : (string) $file;
    }

    /**
     * Which file goes where. $count is the number of files, already in their order.
     *
     * @return array{rows: list<array<string, mixed>>, extra: list<int>, empty: list<string>}
     */
    public static function plan(Story $story, int $count, bool $firstIsCover, bool $replace): array
    {
        $slots = static::slots($story);
        $rows = [];
        $empty = [];
        $next = 0;

        foreach ($slots as $i => $slot) {
            if ($slot['type'] === 'cover') {
                if ($firstIsCover && $count > 0) {
                    $rows[] = $slot + ['file' => $next++, 'state' => $slot['has'] ? 'replace' : 'fill'];
                } else {
                    $rows[] = $slot + ['file' => null, 'state' => 'skip'];
                }

                continue;
            }
            if ($slot['has'] && ! $replace) {
                $rows[] = $slot + ['file' => null, 'state' => 'kept'];

                continue;
            }
            if ($next < $count) {
                $rows[] = $slot + ['file' => $next++, 'state' => $slot['has'] ? 'replace' : 'fill'];
            } else {
                $rows[] = $slot + ['file' => null, 'state' => 'empty'];
                $empty[] = $slot['label'];
            }
        }

        return ['rows' => $rows, 'extra' => $next < $count ? range($next, $count - 1) : [], 'empty' => $empty];
    }

    /**
     * Puts the pictures on the story. A picture that cannot be read is skipped with a message; the others are still saved.
     *
     * @param  list<UploadedFile>  $files  in the order to use
     * @return array{cover: bool, slides: int, replaced: int, problems: list<string>, extra: list<string>, empty: list<string>}
     */
    public static function apply(Story $story, array $files, bool $firstIsCover, bool $replace): array
    {
        $plan = static::plan($story, count($files), $firstIsCover, $replace);
        $report = ['cover' => false, 'slides' => 0, 'replaced' => 0, 'problems' => [], 'extra' => [], 'empty' => $plan['empty']];
        $disk = Storage::disk(StoryImage::DISK);
        $locale = config('infofluxes.default_locale');
        $slideModels = $story->slides()->get()->keyBy('id');

        foreach ($plan['rows'] as $row) {
            if ($row['file'] === null) {
                continue;
            }
            $file = $files[$row['file']];
            try {
                $path = StoryImage::store($file);
            } catch (ValidationException $e) {
                $report['problems'][] = $file->getClientOriginalName().': '.collect($e->errors())->flatten()->first();

                continue;
            }
            if ($row['type'] === 'cover') {
                $old = $story->main_image;
                $story->main_image = $path;
                if (blank($story->getTranslation('main_image_alt', $locale, false))) {
                    $story->setTranslation('main_image_alt', $locale, $story->mainTitle());
                }
                $story->save();
                $report['cover'] = true;
            } else {
                $slide = $slideModels[$row['slide_id']];
                $old = $slide->image;
                $slide->image = $path;
                $slide->save();
                $report['slides']++;
            }
            if (filled($old)) {
                $disk->delete($old);
                $report['replaced']++;
            }
        }
        foreach ($plan['extra'] as $i) {
            $report['extra'][] = $files[$i]->getClientOriginalName();
        }

        return $report;
    }

    /** The picture lines of all the slides, with the look line first: one text to give to an image maker. */
    public static function lines(Story $story, string $style): string
    {
        $out = $style !== '' ? "Look for all the pictures: {$style}\n\n" : '';
        foreach (static::slots($story) as $slot) {
            if ($slot['type'] === 'slide') {
                $out .= str_replace('Slide ', '', $slot['label']).'. '.($slot['picture'] !== '' ? $slot['picture'] : '(no picture line)')."\n";
            }
        }

        return trim($out);
    }
}
