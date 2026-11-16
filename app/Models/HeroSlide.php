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

    /** Is this slide a video clip (and does it have its clip)? */
    public function isVideo(): bool
    {
        return $this->media_type === 'video' && filled($this->video);
    }

    public function videoMime(): string
    {
        return str_ends_with(strtolower((string) $this->video), '.webm') ? 'video/webm' : 'video/mp4';
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'new_tab' => 'boolean', 'custom_data' => 'array'];
    }
}
