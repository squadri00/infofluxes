<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Story slides: an article can have chapters of slides (a picture, a heading and a few words).
 * An article with slides is shown with the slide viewer above its text.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('article_chapters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained('articles')->cascadeOnDelete();
            $table->json('title');
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('custom_data')->nullable();
            $table->timestamps();
        });

        Schema::create('article_slides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_chapter_id')->constrained('article_chapters')->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('image')->nullable();
            $table->json('image_alt')->nullable();
            $table->string('image_credit')->nullable();        // left empty, the viewer shows "AI Generated"
            $table->string('image_licence')->nullable();
            $table->json('heading');
            $table->json('body')->nullable();                  // about 25 words, plain text; a blank line starts a new paragraph
            $table->json('custom_data')->nullable();
            $table->timestamps();
        });

        // The cover picture of the viewer is the main picture of the article: it needs a credit and a licence too.
        Schema::table('articles', function (Blueprint $table) {
            $table->string('main_image_credit')->nullable()->after('main_image_alt');
            $table->string('main_image_licence')->nullable()->after('main_image_credit');
        });
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropColumn(['main_image_credit', 'main_image_licence']);
        });
        Schema::dropIfExists('article_slides');
        Schema::dropIfExists('article_chapters');
    }
};
