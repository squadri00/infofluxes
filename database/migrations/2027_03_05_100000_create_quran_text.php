<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Quran text of the site: the Arabic of every verse (the verified Tanzil text), and any number of English translations.
 * It is used by the Verse helper and by the AI import, which fills in and checks the verse blocks of an article.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quran_verses', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('surah');
            $table->unsignedSmallInteger('ayah');
            $table->text('arabic');
            $table->json('custom_data')->nullable();
            $table->timestamps();
            $table->unique(['surah', 'ayah']);
        });

        Schema::create('quran_translations', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name');
            $table->string('translator')->nullable();
            $table->string('source')->nullable();
            $table->text('notice')->nullable();
            $table->boolean('is_default')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('custom_data')->nullable();
            $table->timestamps();
        });

        Schema::create('quran_translation_verses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('translation_id')->constrained('quran_translations')->cascadeOnDelete();
            $table->unsignedSmallInteger('surah');
            $table->unsignedSmallInteger('ayah');
            $table->text('text');
            $table->timestamps();
            $table->unique(['translation_id', 'surah', 'ayah']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quran_translation_verses');
        Schema::dropIfExists('quran_translations');
        Schema::dropIfExists('quran_verses');
    }
};
