<?php

namespace App\Support\StoryMaker;

use App\Models\Story;
use App\Models\StoryRun;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Saves a checked story as a DRAFT: the story with its chapters and slides. Nothing is published from here.
 * The picture line of each slide is kept with the slide (for the picture step). Saving the same run again updates the same draft
 * (while it is still a draft): the chapters and slides are matched by their place, so pictures that were added are kept.
 */
class Saver
{
    /**
     * @param  array<string, mixed>  $data  the "story" part of the result of Reader::read()
     */
    public static function save(array $data, int $categoryId, StoryRun $run, ?User $user): Story
    {
        $locale = config('infofluxes.default_locale');

        return DB::transaction(function () use ($data, $categoryId, $run, $user, $locale) {
            $story = $run->story_id ? Story::find($run->story_id) : null;
            if ($story && $story->status !== 'draft') {
                throw new \RuntimeException('The story '.$story->code.' is not a draft any more, so it is not changed here. Change it in Visual Stories.');
            }

            $fields = [
                'category_id' => $categoryId,
                'short_detail' => filled($data['short']) ? [$locale => $data['short']] : null,
                'search_words' => $data['keywords'] ?: null,
                'meta_title' => filled($data['meta_title'] ?? null) ? [$locale => $data['meta_title']] : null,
                'meta_description' => filled($data['meta_description'] ?? null) ? [$locale => $data['meta_description']] : null,
            ];
            $custom = ['story_run' => $run->id, 'picture_style' => $data['style'] ?? ''];

            if ($story) {
                $story->setTranslation('title', $locale, $data['title']);
                $story->fill($fields);
                $story->custom_data = array_merge((array) $story->custom_data, $custom);
                $story->save();
            } else {
                $story = Story::create($fields + [
                    'title' => [$locale => $data['title']],
                    'slug' => Story::uniqueSlug($data['title']),
                    'status' => 'draft',
                    'ai_assisted' => true,
                    'author_id' => $user?->id,
                    'custom_data' => $custom,
                ]);
            }

            static::chapters($story, $data['chapters'], $locale);
            static::linkArticle($story, $run);

            return $story->fresh();
        });
    }

    /**
     * A story made from an article keeps the id of the article, its source line and its contributor, and takes the article's pictures
     * (only into places that have no picture yet), see FromArticle.
     */
    private static function linkArticle(Story $story, StoryRun $run): void
    {
        $articleId = (int) (((array) $run->options)['article_id'] ?? 0);
        $article = $articleId > 0 ? \App\Models\Article::find($articleId) : null;
        if (! $article) {
            return;
        }
        $story->article_id = $article->id;
        // What the story was made from: if the article text changes later, the admin shows a note (FromArticle::outdated).
        $story->custom_data = array_merge((array) $story->custom_data, ['article_fingerprint' => FromArticle::fingerprint($article)]);
        if (blank($story->source_line) && filled($article->source_line)) {
            $story->source_line = $article->source_line;
        }
        if (blank($story->contributor_id) && filled($article->contributor_id)) {
            $story->contributor_id = $article->contributor_id;
        }
        $story->save();
        if (((array) $run->options)['auto_pictures'] ?? true) {
            FromArticle::applyPictures($story->fresh(), $article);
        }
    }

    /** The chapters and slides, matched by their place. */
    private static function chapters(Story $story, array $chapters, string $locale): void
    {
        $existing = $story->chapters()->with('slides')->get()->values();

        foreach (array_values($chapters) as $ci => $c) {
            $chapter = $existing[$ci] ?? null;
            if ($chapter) {
                $chapter->setTranslation('title', $locale, $c['title']);
                $chapter->sort_order = $ci + 1;
                $chapter->save();
            } else {
                $chapter = $story->chapters()->create(['title' => [$locale => $c['title']], 'sort_order' => $ci + 1]);
                $chapter->setRelation('slides', collect());
            }

            $slides = $chapter->slides->values();
            foreach (array_values($c['slides']) as $si => $s) {
                $slide = $slides[$si] ?? null;
                $values = [
                    'sort_order' => $si + 1,
                    'custom_data' => array_merge((array) ($slide?->custom_data), ['picture' => $s['picture'] ?: null]),
                ];
                if ($slide) {
                    $slide->setTranslation('heading', $locale, $s['heading']);
                    $slide->setTranslation('body', $locale, $s['body']);
                    $s['alt'] !== '' ? $slide->setTranslation('image_alt', $locale, $s['alt']) : null;
                    $slide->fill($values)->save();
                } else {
                    $chapter->slides()->create($values + [
                        'heading' => [$locale => $s['heading']],
                        'body' => [$locale => $s['body']],
                        'image_alt' => $s['alt'] !== '' ? [$locale => $s['alt']] : null,
                    ]);
                }
            }
            // Slides that the new answer does not have any more.
            foreach ($slides->slice(count($c['slides'])) as $gone) {
                $gone->delete();
            }
        }
        foreach ($existing->slice(count($chapters)) as $gone) {
            $gone->delete();
        }
    }
}
