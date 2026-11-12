<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Translatable\HasTranslations;

class ArticleImage extends Model
{
    use HasTranslations;

    protected $guarded = [];

    public array $translatable = ['alt', 'caption'];

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }
}
