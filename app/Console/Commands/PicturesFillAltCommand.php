<?php

namespace App\Console\Commands;

use App\Models\Article;
use App\Models\ArticleImage;
use App\Models\ArticleSlide;
use App\Models\Category;
use App\Models\DidYouKnow;
use App\Models\HeroSlide;
use App\Models\Person;
use App\Models\Story;
use App\Models\StorySlide;
use App\Support\PictureAlt;
use Illuminate\Console\Command;

/** Writes an ALT text for every picture on the site that has none (made from its page). A text somebody wrote is never changed. */
class PicturesFillAltCommand extends Command
{
    protected $signature = 'pictures:fill-alt {--apply : really save the texts (without it nothing changes)}';

    protected $description = 'Fill the empty ALT texts of the pictures with a text made from their page';

    public function handle(): int
    {
        $total = 0;
        $samples = [];
        foreach ([Article::class, Story::class, ArticleImage::class, ArticleSlide::class, StorySlide::class, Category::class, DidYouKnow::class, HeroSlide::class, Person::class] as $class) {
            $query = method_exists($class, 'withTrashed') ? $class::withTrashed() : $class::query();
            $count = 0;
            foreach ($query->get() as $model) {
                if (PictureAlt::fill($model)) {
                    $count++;
                    if (count($samples) < 12) {
                        $samples[] = [class_basename($class).' #'.$model->getKey(), mb_substr((string) $this->altOf($model), 0, 70)];
                    }
                    if ($this->option('apply')) {
                        $model->saveQuietly();
                    }
                }
            }
            $this->line(str_pad(class_basename($class), 14).$count.' picture(s) without an ALT text');
            $total += $count;
        }
        if ($samples !== []) {
            $this->table(['Picture of', 'ALT text'], $samples);
        }
        $this->info($this->option('apply') ? "$total ALT text(s) written." : "Dry run: $total ALT text(s) would be written. Run again with --apply.");

        return self::SUCCESS;
    }

    private function altOf($model): ?string
    {
        foreach (['main_image_alt', 'alt', 'image_alt', 'photo_alt'] as $field) {
            if (array_key_exists($field, $model->getAttributes())) {
                return method_exists($model, 'getTranslation') && in_array($field, $model->getTranslatableAttributes(), true)
                    ? $model->getTranslation($field, config('infofluxes.default_locale'), false)
                    : $model->getAttribute($field);
            }
        }

        return null;
    }
}
