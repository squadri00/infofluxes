<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

/** One set of words (title, subtitle, description) for the home page banner. */
class HeroMessage extends Model
{
    use HasTranslations;

    protected $guarded = [];

    public array $translatable = ['title', 'subtitle', 'description'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'custom_data' => 'array'];
    }
}
