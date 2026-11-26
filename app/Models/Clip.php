<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * A video clip of the Clips page: a video file, a name, optional words shown on the video, and a button that opens one article or one story.
 * The files are in storage/app/public/clips. A file put there by hand is found by Clip::scan().
 */
class Clip extends Model
{
    public const FOLDER = 'clips';

    public const TYPES = ['mp4', 'webm'];

    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    protected static function booted(): void
    {
        // The file goes with the clip (else the folder scan would bring it back).
        static::deleted(function (Clip $clip) {
            if ($clip->file !== '' && str_starts_with($clip->file, self::FOLDER.'/') && ! static::where('file', $clip->file)->exists()) {
                Storage::disk('public')->delete($clip->file);
            }
        });
    }

    public function scopePlayable(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort_order')->orderBy('id');
    }

    public function exists(): bool
    {
        return $this->file !== '' && Storage::disk('public')->exists($this->file);
    }

    public function src(): string
    {
        return Storage::disk('public')->url($this->file);
    }

    /** The published article or story this clip leads to: [url, button words], or null. */
    public function target(): ?array
    {
        if ($this->link_type === 'article' && $this->article_id) {
            $a = Article::published()->find($this->article_id);

            return $a ? [url($a->publicPath()), $this->button_label ?: __('site.read_article')] : null;
        }
        if ($this->link_type === 'story' && $this->story_id) {
            $s = Story::published()->find($this->story_id);

            return $s ? [route('story.show', $s->slug), $this->button_label ?: __('site.read_story')] : null;
        }

        return null;
    }

    /** What the player on the page needs. */
    public function forPlayer(): array
    {
        $t = $this->target();

        return ['id' => $this->id, 'title' => $this->title, 'blurb' => (string) $this->blurb, 'src' => $this->src(), 'url' => $t[0] ?? '', 'label' => $t[1] ?? ''];
    }

    /** Registers the video files that were put in the clips folder by hand. Returns how many are new. */
    public static function scan(): int
    {
        $disk = Storage::disk('public');
        $known = static::query()->pluck('file')->all();
        $next = (int) static::max('sort_order');
        $new = 0;
        foreach ($disk->files(self::FOLDER) as $path) {
            if (! in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), self::TYPES, true) || in_array($path, $known, true)) {
                continue;
            }
            static::create(['title' => Str::headline(pathinfo($path, PATHINFO_FILENAME)), 'file' => $path, 'sort_order' => ++$next, 'is_active' => true]);
            $new++;
        }

        return $new;
    }
}
