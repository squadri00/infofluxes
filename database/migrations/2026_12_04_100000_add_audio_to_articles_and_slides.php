<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audio for the Listen button: an MP3 for a slide, for the cover, or for the whole story.
 * A file plays only when its state is "approved" (a person listened to it). Without a file, the voice of the visitor's own device is used.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('article_slides', function (Blueprint $table) {
            $table->string('audio')->nullable()->after('body');
            $table->string('audio_state', 20)->default('draft')->after('audio');
        });

        Schema::table('articles', function (Blueprint $table) {
            $table->string('audio')->nullable()->after('main_image_licence');              // one file for the whole story
            $table->string('audio_state', 20)->default('draft')->after('audio');
            $table->string('cover_audio')->nullable()->after('audio_state');               // the cover words
            $table->string('cover_audio_state', 20)->default('draft')->after('cover_audio');
        });
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropColumn(['audio', 'audio_state', 'cover_audio', 'cover_audio_state']);
        });
        Schema::table('article_slides', function (Blueprint $table) {
            $table->dropColumn(['audio', 'audio_state']);
        });
    }
};
