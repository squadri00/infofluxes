<?php

namespace App\Models;

use App\Support\YouTube;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Translatable\HasTranslations;

class ArticleVideo extends Model
{
    use HasTranslations;

    protected $guarded = [];

    public array $translatable = ['title'];

    protected static function booted(): void
    {
        // The video code is always worked out from the address, so the two can never disagree.
        static::saving(fn (ArticleVideo $v) => $v->youtube_id = YouTube::id($v->url));
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }
}
