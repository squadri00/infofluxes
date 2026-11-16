<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A slide of the home page banner can be a short video clip (it plays by itself, without sound and without controls) instead of a picture. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hero_slides', function (Blueprint $table) {
            $table->string('media_type', 10)->default('image');   // image or video
            $table->string('video')->nullable();                   // the clip in the public storage
            $table->string('image')->nullable()->change();         // for a video the picture is only the still shown while it loads
        });
    }

    public function down(): void
    {
        Schema::table('hero_slides', function (Blueprint $table) {
            $table->dropColumn(['media_type', 'video']);
        });
    }
};
