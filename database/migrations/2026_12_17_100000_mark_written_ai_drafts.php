<?php

use App\Models\Article;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /** A draft that was written with AI help and has text now is "written" (waiting for review). A draft someone is still typing stays a draft. */
    public function up(): void
    {
        Article::query()->where('status', 'draft')->where('ai_assisted', true)->get()
            ->filter(fn ($a) => trim(strip_tags((string) $a->getTranslation('long_detail', config('infofluxes.default_locale'), false))) !== '')
            ->each(fn ($a) => $a->forceFill(['status' => 'written'])->saveQuietly());
    }

    public function down(): void
    {
        Article::query()->where('status', 'written')->update(['status' => 'draft']);
    }
};
