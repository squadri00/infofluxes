<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

/** One picture of the home page slider, with its words and button. */
class HeroSlide extends Model
{
    use HasTranslations;

    protected $guarded = [];

    public array $translatable = ['image_alt', 'heading', 'text', 'button_label'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'new_tab' => 'boolean', 'custom_data' => 'array'];
    }
}
