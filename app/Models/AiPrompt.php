<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One prompt of the AI prompt library. The words start as the files in resources/prompts/library
 * and can be edited in the admin. "Reset to the original" puts the file's words back.
 */
class AiPrompt extends Model
{
    protected $fillable = ['slug', 'kind', 'title', 'use_for', 'body', 'sort_order', 'custom_data'];

    protected function casts(): array
    {
        return ['custom_data' => 'array'];
    }

    public function getCodeAttribute(): string
    {
        return 'PR-'.$this->id;
    }

    /** The original words, from the file. */
    public function originalBody(): string
    {
        return static::fileBody($this->slug);
    }

    public function isChanged(): bool
    {
        return trim($this->body) !== trim($this->originalBody());
    }

    public function resetToOriginal(): void
    {
        $this->update(['body' => $this->originalBody()]);
    }

    public static function fileBody(string $slug): string
    {
        $file = resource_path('prompts/library/'.basename($slug).'.txt');

        return is_file($file) ? trim((string) file_get_contents($file)) : '';
    }

    /** Adds the prompts that are not in the table yet. A prompt the owner changed is never touched. */
    public static function installDefaults(): int
    {
        $added = 0;
        foreach ((array) require resource_path('prompts/library/index.php') as $slug => [$kind, $title, $useFor, $order]) {
            if (static::query()->where('slug', $slug)->exists()) {
                continue;
            }
            static::create(['slug' => $slug, 'kind' => $kind, 'title' => $title, 'use_for' => $useFor, 'body' => static::fileBody($slug), 'sort_order' => $order]);
            $added++;
        }

        return $added;
    }

    /** The master rules, then the chosen specialist prompt, joined into one text. */
    public static function combined(?string $specialistSlug): string
    {
        $master = static::query()->where('slug', 'master')->value('body');
        $special = $specialistSlug ? static::query()->where('slug', $specialistSlug)->where('kind', 'specialist')->value('body') : null;

        return trim(implode("\n\n", array_filter([$master, $special])));
    }
}
