<?php

namespace App\Support;

use App\Models\Article;
use App\Models\Story;
use App\Models\User;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Moves the old slide stories (the old "Stories in pictures" that have chapters and slides) into this site, each as a Visual Story,
 * and copies their pictures.
 *
 * - The cards (claims with sources) and the fact files are not moved. They stay in the old database.
 * - The old ID (for example T-14) is kept inside the story (and was the working name of the article it was before), so a story is never moved twice.
 * - A story whose title is already a story or an article here is left alone and reported.
 * - A story that was published and is not AI-assisted (or has a reviewer name) is published; any other is a draft,
 *   because AI text must be read by a person first.
 * - All or nothing for the data. The old database and the old files are only read.
 */
class OldStoryImport
{
    private ConnectionInterface $old;

    private bool $dry = true;

    /** @var array<string, int> */
    private array $report = [];

    public function __construct(private ?int $categoryId = null, private ?int $authorId = null)
    {
        $this->old = DB::connection('old_site');
    }

    public static function available(): bool
    {
        try {
            return OldSiteImport::available()
                && \Illuminate\Support\Facades\Schema::connection('old_site')->hasTable('story_slides')
                && filled(config('infofluxes.old_site_path'));
        } catch (\Throwable) {
            return false;
        }
    }

    public function check(): array
    {
        return $this->run(true);
    }

    public function move(?User $author = null): array
    {
        $this->authorId = $author?->id ?? $this->authorId;

        return $this->run(false);
    }

    /** What would be copied: audio files of stories that were already moved. Writes nothing. */
    public function checkAudio(): array
    {
        return $this->runAudio(true);
    }

    /** Copy the audio files (and their state) of old stories into the matching slides here. A slide that already has audio is left alone. */
    public function moveAudio(): array
    {
        return $this->runAudio(false);
    }

    private function runAudio(bool $dry): array
    {
        $this->dry = $dry;
        $this->report = ['audio_copied' => 0, 'audio_missing' => 0, 'stories_matched' => 0, 'stories_not_moved' => 0, 'slides_matched' => 0, 'slides_unmatched' => 0];
        $locale = config('infofluxes.default_locale');

        $work = function () use ($locale) {
            $topics = $this->old->table('topics')->whereNull('deleted_at')
                ->whereExists(fn ($q) => $q->selectRaw('1')->from('story_chapters')->whereColumn('story_chapters.topic_id', 'topics.id'))->orderBy('id')->get();

            foreach ($topics as $topic) {
                $article = static::moved($topic->public_id);
                if (! $article) {
                    $this->report['stories_not_moved']++;

                    continue;
                }
                $this->report['stories_matched']++;

                // the cover and the whole-story files
                foreach ([['cover_audio', 'cover_audio_state'], ['audio', 'audio_state']] as [$col, $stateCol]) {
                    $path = $topic->{$col} ?? null;
                    if (filled($path) && blank($article->{$col})) {
                        $copied = $this->image($path, 'audio');
                        if ($copied && ! $this->dry) {
                            $article->{$col} = $copied;
                            $article->{$stateCol} = ($topic->{$stateCol} ?? null) === 'approved' ? 'approved' : 'draft';
                            $article->save();
                        }
                    }
                }

                // the slides, matched by their place (chapter number, slide number) and their heading
                $chapters = $this->old->table('story_chapters')->where('topic_id', $topic->id)->orderBy('sort_order')->orderBy('id')->get()->filter(
                    fn ($c) => $this->old->table('story_slides')->where('story_chapter_id', $c->id)->exists()
                )->values();
                $newChapters = $article->chapters()->with('slides')->get()->filter(fn ($c) => $c->slides->isNotEmpty())->values();
                foreach ($chapters as $ci => $chapter) {
                    $oldSlides = $this->old->table('story_slides')->where('story_chapter_id', $chapter->id)->orderBy('sort_order')->orderBy('id')->get();
                    foreach ($oldSlides as $si => $oldSlide) {
                        if (blank($oldSlide->audio ?? null)) {
                            continue;
                        }
                        $new = $newChapters->get($ci)?->slides->get($si);
                        if (! $new || mb_strtolower(trim($new->getTranslation('heading', $locale))) !== mb_strtolower(trim($oldSlide->heading))) {
                            $this->report['slides_unmatched']++;

                            continue;
                        }
                        $this->report['slides_matched']++;
                        if (filled($new->audio)) {
                            continue;
                        }
                        $copied = $this->image($oldSlide->audio, 'audio');
                        if ($copied && ! $this->dry) {
                            $new->audio = $copied;
                            $new->audio_state = ($oldSlide->audio_state ?? null) === 'approved' ? 'approved' : 'draft';
                            $new->save();
                        }
                    }
                }
            }
        };

        $dry ? $work() : DB::transaction($work);

        return ['total' => $this->report, 'dry' => $dry];
    }

    private function run(bool $dry): array
    {
        $this->dry = $dry;
        $this->report = ['stories_new' => 0, 'stories_existing' => 0, 'stories_same_title' => 0, 'chapters' => 0, 'slides' => 0, 'images_copied' => 0, 'images_missing' => 0, 'published' => 0, 'drafts' => 0, 'audio_copied' => 0, 'audio_missing' => 0];
        $titles = [];

        if ($dry) {
            $this->all($titles);
        } else {
            DB::transaction(function () use (&$titles) {
                $this->all($titles);
            });
        }

        return ['total' => $this->report, 'same_title' => $titles, 'dry' => $dry];
    }

    private function all(array &$sameTitle): void
    {
        $topics = $this->old->table('topics')->whereNull('deleted_at')
            ->whereExists(fn ($q) => $q->selectRaw('1')->from('story_chapters')->whereColumn('story_chapters.topic_id', 'topics.id'))
            ->orderBy('id')->get();

        $existingTitles = Article::withTrashed()->get()->concat(Story::withTrashed()->get())->map(fn ($a) => mb_strtolower(trim($a->mainTitle())))->all();

        foreach ($topics as $topic) {
            $chapters = $this->old->table('story_chapters')->where('topic_id', $topic->id)->orderBy('sort_order')->orderBy('id')->get();
            $slidesBy = $this->old->table('story_slides')->whereIn('story_chapter_id', $chapters->pluck('id'))->orderBy('sort_order')->orderBy('id')->get()->groupBy('story_chapter_id');
            if ($slidesBy->flatten()->isEmpty()) {
                continue;
            }

            if (static::moved($topic->public_id) || Article::withTrashed()->where('working_name', $topic->public_id)->exists()) {
                $this->report['stories_existing']++;

                continue;
            }
            if (in_array(mb_strtolower(trim($topic->title)), $existingTitles, true)) {
                $this->report['stories_same_title']++;
                $sameTitle[] = $topic->title;

                continue;
            }

            $this->report['stories_new']++;
            $this->story($topic, $chapters, $slidesBy);
            $existingTitles[] = mb_strtolower(trim($topic->title));
        }
    }

    private function story(object $topic, $chapters, $slidesBy): void
    {
        $locale = config('infofluxes.default_locale');
        $cover = $this->image($topic->master_image);
        if ($this->dry) {
            $this->image($topic->audio ?? null, 'audio');
            $this->image($topic->cover_audio ?? null, 'audio');
        }
        $reviewed = ! $topic->ai_assisted || filled($topic->ai_reviewed_by);
        $publish = $topic->status === 'published' && $reviewed;
        $publish ? $this->report['published']++ : $this->report['drafts']++;

        $article = null;
        if (! $this->dry) {
            $short = trim((string) $topic->summary) !== '' ? trim((string) $topic->summary) : trim(preg_replace('/\s+/', ' ', strip_tags((string) $topic->hook)));
            $article = Story::create([
                'title' => [$locale => trim($topic->title)],
                'slug' => Story::uniqueSlug($topic->title),
                'category_id' => $this->categoryId,
                'custom_data' => ['old_id' => $topic->public_id],
                'short_detail' => [$locale => mb_substr($short, 0, 600)],
                'long_detail' => [$locale => Html::clean((string) $topic->hook).Html::clean((string) $topic->context)],
                'main_image' => $cover,
                'main_image_alt' => $topic->master_image_alt ? [$locale => $topic->master_image_alt] : null,
                'main_image_credit' => $topic->master_image_credit ?: null,
                'main_image_licence' => $topic->master_image_licence ?: null,
                'audio' => $this->image($topic->audio ?? null, 'audio'),
                'audio_state' => ($topic->audio_state ?? null) === 'approved' ? 'approved' : 'draft',
                'cover_audio' => $this->image($topic->cover_audio ?? null, 'audio'),
                'cover_audio_state' => ($topic->cover_audio_state ?? null) === 'approved' ? 'approved' : 'draft',
                'status' => $publish ? 'published' : 'draft',
                'publish_date' => $publish ? ($topic->published_at ?: now()) : null,
                'ai_assisted' => (bool) $topic->ai_assisted,
                'reviewer_name' => $topic->ai_reviewed_by ?: null,
                'author_id' => $this->authorId,
            ]);
        }

        foreach ($chapters as $c => $chapter) {
            $slides = $slidesBy->get($chapter->id) ?? collect();
            if ($slides->isEmpty()) {
                continue;
            }
            $this->report['chapters']++;
            $newChapter = $article?->chapters()->create(['title' => [$locale => trim($chapter->title)], 'sort_order' => $c + 1]);

            foreach ($slides as $s => $slide) {
                $this->report['slides']++;
                $image = $this->image($slide->image);
                if ($this->dry) {
                    $this->image($slide->audio ?? null, 'audio');
                }
                $newChapter?->slides()->create([
                    'sort_order' => $s + 1,
                    'image' => $image,
                    'image_alt' => $slide->image_alt ? [$locale => $slide->image_alt] : null,
                    'image_credit' => $slide->image_credit ?: null,
                    'image_licence' => $slide->image_licence ?: null,
                    'heading' => [$locale => trim($slide->heading)],
                    'body' => [$locale => static::plain((string) $slide->body)],
                    'audio' => $this->image($slide->audio ?? null, 'audio'),
                    'audio_state' => ($slide->audio_state ?? null) === 'approved' ? 'approved' : 'draft',
                ]);
            }
        }
    }

    /** The story that came from this old topic (by its old ID, for example T-14), or null. */
    public static function moved(string $oldId): ?Story
    {
        return Story::withTrashed()->where('custom_data->old_id', $oldId)->first();
    }

    /** Old paragraphs as plain text: a blank line between paragraphs. */
    public static function plain(string $html): string
    {
        $text = preg_replace('~</p>\s*<p[^>]*>~i', "\n\n", $html);
        $text = preg_replace('~<br\s*/?>~i', "\n", (string) $text);

        return trim(html_entity_decode(strip_tags((string) $text), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    /** Copy a picture of the old site to this site (same name). Returns its path, or null when the old file is not there. */
    private function image(?string $path, string $kind = 'images'): ?string
    {
        if (blank($path)) {
            return null;
        }
        if (str_contains($path, '..') || str_starts_with($path, '/') || str_contains($path, '\\')) {
            $this->report[$kind.'_missing']++;

            return null;
        }
        $disk = Storage::disk('public');
        $source = rtrim((string) config('infofluxes.old_site_path'), '/\\').'/storage/app/public/'.$path;

        if ($disk->exists($path)) {
            return $path;      // already here (for example from an earlier move)
        }
        if (! is_file($source)) {
            $this->report[$kind.'_missing']++;

            return null;
        }
        $this->report[$kind.'_copied']++;
        if (! $this->dry) {
            $disk->put($path, (string) file_get_contents($source), 'public');
        }

        return $path;
    }
}
