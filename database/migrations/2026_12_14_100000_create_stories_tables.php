<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Visual Stories: their own content type, apart from articles. Code S-<number>.
 * A story has chapters, a chapter has slides (a picture, a heading and a few words). The columns follow the ones the story viewer,
 * the Listen button and the PDF book already read, so they work on a story without change.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->json('title');
            $table->string('slug')->unique();
            $table->json('short_detail')->nullable();
            $table->json('long_detail')->nullable();
            $table->json('search_words')->nullable();
            $table->string('main_image')->nullable();                 // the cover
            $table->json('main_image_alt')->nullable();
            $table->string('main_image_credit')->nullable();
            $table->string('main_image_licence')->nullable();
            $table->string('audio')->nullable();
            $table->string('audio_state', 20)->default('draft');
            $table->string('cover_audio')->nullable();
            $table->string('cover_audio_state', 20)->default('draft');
            $table->string('source_line', 500)->nullable();
            $table->dateTime('publish_date')->nullable();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('contributor_id')->nullable()->constrained('people')->nullOnDelete();
            $table->string('reviewer_name')->nullable();
            $table->boolean('ai_assisted')->default(false);
            $table->boolean('is_featured')->default(false);
            $table->unsignedInteger('featured_order')->default(0);
            $table->string('status', 20)->default('draft')->index();   // draft, in_review, published, archived
            $table->json('page_title')->nullable();
            $table->json('meta_description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('custom_data')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('story_chapters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('story_id')->constrained('stories')->cascadeOnDelete();
            $table->json('title');
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('custom_data')->nullable();
            $table->timestamps();
        });

        Schema::create('story_slides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('story_chapter_id')->constrained('story_chapters')->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('image')->nullable();
            $table->json('image_alt')->nullable();
            $table->string('image_credit')->nullable();
            $table->string('image_licence')->nullable();
            $table->json('heading');
            $table->json('body')->nullable();
            $table->string('audio')->nullable();
            $table->string('audio_state', 20)->default('draft');
            $table->json('custom_data')->nullable();
            $table->timestamps();
        });

        // A story may also sit in other categories, like an article.
        Schema::create('story_category', function (Blueprint $table) {
            $table->id();
            $table->foreignId('story_id')->constrained('stories')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
            $table->unique(['story_id', 'category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('story_category');
        Schema::dropIfExists('story_slides');
        Schema::dropIfExists('story_chapters');
        Schema::dropIfExists('stories');
    }
};
