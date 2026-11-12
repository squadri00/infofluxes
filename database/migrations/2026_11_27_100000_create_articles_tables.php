<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Step 3: articles, with their extra categories, pictures and YouTube videos. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('articles', function (Blueprint $table) {
            $table->id();                                                   // the permanent number; the code shown is A-<id>
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();   // the main category
            $table->json('title');
            $table->string('working_name')->nullable();                     // only for the admin
            $table->string('slug')->unique();
            $table->json('short_detail')->nullable();
            $table->json('long_detail')->nullable();
            $table->json('search_words')->nullable();                       // many words, e.g. ["prophet","night journey"]
            $table->string('main_image')->nullable();
            $table->json('main_image_alt')->nullable();
            $table->string('source_line', 500)->nullable();                 // one optional line, shown only when filled
            $table->dateTime('publish_date')->nullable();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reviewer_name')->nullable();
            $table->string('status', 20)->default('draft')->index();        // draft, in_review, published, archived
            $table->json('page_title')->nullable();
            $table->json('meta_description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('custom_data')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // The other categories an article also sits in (ECT calls them "additional sections").
        Schema::create('article_category', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained('articles')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
            $table->unique(['article_id', 'category_id']);
        });

        Schema::create('article_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained('articles')->cascadeOnDelete();
            $table->string('image');
            $table->json('alt')->nullable();
            $table->json('caption')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('article_videos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained('articles')->cascadeOnDelete();
            $table->string('url', 500);
            $table->string('youtube_id', 20)->nullable();                   // worked out from the address
            $table->json('title')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('article_videos');
        Schema::dropIfExists('article_images');
        Schema::dropIfExists('article_category');
        Schema::dropIfExists('articles');
    }
};
