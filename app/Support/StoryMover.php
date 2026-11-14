<?php

namespace App\Support;

use App\Models\Article;
use App\Models\Story;
use Illuminate\Support\Facades\DB;

/**
 * Moves the articles that are told as picture stories (the ones with chapters and slides) into Visual Stories.
 * Everything is copied: the words, the cover, the audio, the chapters, the slides, the other categories, the author, the dates, "featured".
 * The story keeps the web address of the article, so the old link still finds it. The article is then put in the Trash
 * (it can be brought back from there); its old chapters and slides stay in their tables, untouched.
 * Without "apply" nothing is changed. Running it again does nothing for a story that was moved already.
 */
class StoryMover
{
    /**
     * @return array{stories: list<array{article: string, story: string|null, title: string, chapters: int, slides: int, status: string}>, applied: bool}
     */
    public static function run(bool $apply): array
    {
        $rows = [];

        $articles = Article::query()->whereHas('chapters')->with(['chapters.slides', 'extraCategories'])->orderBy('id')->get();

        foreach ($articles as $article) {
            $chapters = $article->chapters->count();
            $slides = $article->chapters->sum(fn ($c) => $c->slides->count());
            $row = ['article' => $article->code, 'story' => null, 'title' => $article->mainTitle(), 'chapters' => $chapters, 'slides' => $slides, 'status' => 'would move'];

            if (! $apply) {
                $rows[] = $row;

                continue;
            }

            DB::transaction(function () use ($article, &$row, $chapters, $slides) {
                $story = Story::create([
                    'category_id' => $article->category_id,
                    'title' => $article->getTranslations('title'),
                    'slug' => static::slugFor($article),
                    'short_detail' => $article->getTranslations('short_detail') ?: null,
                    'long_detail' => $article->getTranslations('long_detail') ?: null,
                    'search_words' => $article->search_words,
                    'main_image' => $article->main_image,
                    'main_image_alt' => $article->getTranslations('main_image_alt') ?: null,
                    'main_image_credit' => $article->main_image_credit,
                    'main_image_licence' => $article->main_image_licence,
                    'audio' => $article->audio,
                    'audio_state' => $article->audio_state ?: 'draft',
                    'cover_audio' => $article->cover_audio,
                    'cover_audio_state' => $article->cover_audio_state ?: 'draft',
                    'source_line' => $article->source_line,
                    'publish_date' => $article->publish_date,
                    'author_id' => $article->author_id,
                    'contributor_id' => $article->contributor_id,
                    'reviewer_name' => $article->reviewer_name,
                    'ai_assisted' => $article->ai_assisted,
                    'is_featured' => $article->is_featured,
                    'featured_order' => $article->featured_order,
                    'status' => $article->status,
                    'page_title' => $article->getTranslations('page_title') ?: null,
                    'meta_description' => $article->getTranslations('meta_description') ?: null,
                    'sort_order' => $article->sort_order,
                    'custom_data' => array_merge((array) $article->custom_data, ['old_article_id' => $article->id, 'old_article_code' => $article->code, 'old_slug' => $article->slug] + (filled($article->working_name) ? ['old_id' => $article->working_name] : [])),
                ]);
                // created_at / updated_at of the article are kept
                Story::withoutTimestamps(fn () => $story->forceFill(['created_at' => $article->created_at, 'updated_at' => $article->updated_at])->save());

                foreach ($article->chapters as $chapter) {
                    $newChapter = $story->chapters()->create(['title' => $chapter->getTranslations('title'), 'sort_order' => $chapter->sort_order, 'custom_data' => $chapter->custom_data]);
                    foreach ($chapter->slides as $slide) {
                        $newChapter->slides()->create([
                            'sort_order' => $slide->sort_order, 'image' => $slide->image,
                            'image_alt' => $slide->getTranslations('image_alt') ?: null, 'image_credit' => $slide->image_credit, 'image_licence' => $slide->image_licence,
                            'heading' => $slide->getTranslations('heading'), 'body' => $slide->getTranslations('body') ?: null,
                            'audio' => $slide->audio, 'audio_state' => $slide->audio_state ?: 'draft', 'custom_data' => $slide->custom_data,
                        ]);
                    }
                }
                $story->extraCategories()->sync($article->extraCategories->pluck('id')->all());

                // Check before the article goes: the copy must have the same number of chapters and slides.
                $story->load('chapters.slides');
                if ($story->chapters->count() !== $chapters || $story->chapters->sum(fn ($c) => $c->slides->count()) !== $slides) {
                    throw new \RuntimeException('The copy of '.$article->code.' does not match the original. Nothing was changed for it.');
                }

                $data = (array) $article->custom_data;
                $data['moved_to_story'] = $story->code;
                $article->forceFill(['custom_data' => $data])->save();
                $article->delete();

                $row['story'] = $story->code;
                $row['status'] = 'moved';
            });

            $rows[] = $row;
        }

        return ['stories' => $rows, 'applied' => $apply];
    }

    /** The story keeps the article's address. (The article is in the Trash by then, but its address is still reserved by it, so it is given up here.) */
    private static function slugFor(Article $article): string
    {
        $slug = $article->slug;

        return Story::withTrashed()->where('slug', $slug)->exists() ? Story::uniqueSlug($article->mainTitle()) : $slug;
    }
}
