<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clips', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('file');                       // the video, on the public disk (clips/name.mp4)
            $table->string('blurb', 300)->nullable();     // the words shown on the video
            $table->string('link_type', 10)->nullable();  // article | story | null
            $table->unsignedBigInteger('article_id')->nullable();
            $table->unsignedBigInteger('story_id')->nullable();
            $table->string('button_label', 60)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clips');
    }
};
