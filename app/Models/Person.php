<?php

namespace App\Models;

use App\Support\Html;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/** A contributor: someone credited on articles. When "show_page" is on they also have a page of their own at /people/their-name. */
class Person extends Model
{
    use SoftDeletes, \App\Models\Concerns\LogsActivity;

    public array $activityUndo = ['name', 'role_title', 'bio', 'qualification_note', 'page_body'];

    protected $table = 'people';

    protected $guarded = [];

    /** Addresses under /people that are never a person. */
    public const RESERVED = [];

    protected function casts(): array
    {
        return ['show_page' => 'boolean', 'custom_data' => 'array'];
    }

    protected static function booted(): void
    {
        // Every person gets a web address from their name, and it stays free of clashes.
        static::saving(function (self $person) {
            if (blank($person->slug)) {
                $person->slug = static::freeSlug($person->name, $person->id);
            }
        });
    }

    public static function freeSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'person';
        $slug = $base;

        for ($n = 2; static::withTrashed()->where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists(); $n++) {
            $slug = $base.'-'.$n;
        }

        return $slug;
    }

    public function getCodeAttribute(): string
    {
        return 'K-'.$this->id;
    }

    /** The text of the person's own page, cleaned so nothing dangerous typed into the editor reaches a visitor. */
    public function safeBody(): HtmlString
    {
        return new HtmlString(Html::clean((string) $this->page_body));
    }

    /** The person's page address, or null when they have no public page. */
    public function pageUrl(): ?string
    {
        return $this->show_page && $this->slug ? route('people.show', $this->slug) : null;
    }

    public function articles(): HasMany
    {
        return $this->hasMany(Article::class, 'contributor_id');
    }
}
