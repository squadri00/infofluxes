<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

/** One question and its answer on the FAQ page. */
class Faq extends Model
{
    use HasTranslations, \App\Models\Concerns\LogsActivity;

    protected $guarded = [];

    public array $translatable = ['question', 'answer', 'group'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'custom_data' => 'array'];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function mainQuestion(): string
    {
        return (string) $this->getTranslation('question', config('infofluxes.default_locale'), true);
    }

    public function activityLabel(): string
    {
        return 'FAQ: '.$this->mainQuestion();
    }
}
