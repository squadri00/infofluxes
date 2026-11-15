<?php

namespace App\Support;

use App\Models\Article;
use App\Models\Comment;
use App\Models\DidYouKnow;
use Illuminate\Support\Facades\DB;

/**
 * Moves an article that was made on its own into the blank index entry about the same subject.
 * The entry keeps its index ID and its category; it takes the article's text, pictures, videos, comments, likes, saves and web address.
 * The article goes to the Trash (it can be brought back), and its code (A-4026) keeps leading to the entry.
 * Without "apply" nothing is changed.
 */
class IndexMerger
{
    /**
     * @return array{ok: bool, problem: ?string, from: string, into: string, moves: array<string, int>, applied: bool}
     */
    public static function run(Article $from, Article $entry, bool $apply): array
    {
        $result = ['ok' => false, 'problem' => null, 'from' => $from->code.' '.$from->mainTitle(), 'into' => IndexEntries::label($entry).' '.$entry->mainTitle(), 'moves' => [], 'applied' => false];

        if ($from->id === $entry->id) {
            return $result + ['problem' => 'These are the same article.'];
        }
        if ($from->trashed() || $from->is_index_line) {
            return array_merge($result, ['problem' => 'The first article must be an ordinary article (not an index entry, not in the Trash).']);
        }
        if (! IndexEntries::isBlank($entry)) {
            return array_merge($result, ['problem' => 'The index entry already has text, so nothing is moved into it.']);
        }

        $result['moves'] = [
            'pictures' => $from->images()->count(), 'videos' => $from->videos()->count(), 'comments' => $from->comments()->count(),
            'likes and dislikes' => $from->reactions()->count(), 'saves' => $from->saves()->count(), 'Did You Know? links' => DidYouKnow::where('article_id', $from->id)->count(),
            'other categories' => $from->extraCategories()->count(),
        ];
        $result['ok'] = true;
        if (! $apply) {
            return $result;
        }

        DB::transaction(function () use ($from, $entry) {
            $slug = $from->slug;
            $custom = array_merge((array) $entry->custom_data, (array) $from->custom_data);
            $custom['index_title'] = ((array) $entry->custom_data)['index_title'] ?? $entry->mainTitle();
            $custom['merged_from'] = $from->code;

            // The article gives up its address and its code is remembered, then the entry takes the address.
            $fromCustom = (array) $from->custom_data;
            $fromCustom['moved_to_article'] = $entry->id;
            $from->forceFill(['slug' => $slug.'-moved-'.$from->id, 'custom_data' => $fromCustom])->save();

            foreach (['title', 'short_detail', 'long_detail', 'main_image_alt', 'page_title', 'meta_title', 'meta_description'] as $field) {
                $entry->setTranslations($field, $from->getTranslations($field));
            }
            foreach (['search_words', 'source_line', 'main_image', 'main_image_credit', 'main_image_licence', 'audio', 'audio_state', 'cover_audio', 'cover_audio_state',
                'publish_date', 'contributor_id', 'reviewer_name', 'ai_assisted', 'is_featured', 'featured_order', 'status'] as $field) {
                $entry->{$field} = $from->{$field};
            }
            // columns that may not be empty
            $entry->is_featured = (bool) $from->is_featured;
            $entry->ai_assisted = (bool) $from->ai_assisted;
            $entry->featured_order = (int) $from->featured_order;
            $entry->audio_state = $from->audio_state ?: 'draft';
            $entry->cover_audio_state = $from->cover_audio_state ?: 'draft';
            $entry->slug = $slug;
            $entry->author_id = $from->author_id ?? $entry->author_id;
            $entry->custom_data = $custom;
            $entry->save();

            $entry->extraCategories()->syncWithoutDetaching($from->extraCategories()->pluck('categories.id')->all());
            $from->images()->update(['article_id' => $entry->id]);
            $from->videos()->update(['article_id' => $entry->id]);
            Comment::where('article_id', $from->id)->update(['article_id' => $entry->id]);
            DidYouKnow::where('article_id', $from->id)->update(['article_id' => $entry->id]);

            // A person who reacted to or saved both keeps the one on the entry.
            foreach (['reactions' => 'user_id', 'saves' => 'user_id'] as $relation => $column) {
                $have = $entry->{$relation}()->pluck($column)->all();
                $from->{$relation}()->whereIn($column, $have)->delete();
                // a query update, because the type and id columns are not open to mass assignment
                $from->{$relation}()->toBase()->update([$relation === 'reactions' ? 'reactable_id' : 'saveable_id' => $entry->id]);
            }

            $from->delete();
        });

        return array_merge($result, ['applied' => true]);
    }

    /** An article, by number (4026), code (A-4026) or index ID (AL-2.1.001). */
    public static function find(string $ref): ?Article
    {
        $ref = trim($ref);
        if (preg_match('/^(?:A-)?(\d+)$/i', $ref, $m)) {
            return Article::withTrashed()->find((int) $m[1]);
        }

        return Article::withTrashed()->where('working_name', $ref)->first();
    }
}
