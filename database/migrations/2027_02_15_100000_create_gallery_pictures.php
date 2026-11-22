<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The register of the pictures of the picture gallery: each picture gets an ID (I-12) that never changes, so the admin can
 * switch a picture on or off in the gallery (Organise > Gallery pictures). The pictures themselves stay on their pages.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gallery_pictures', function (Blueprint $t) {
            $t->id();
            $t->string('path', 500)->unique();       // the picture file inside the public storage folder
            $t->string('kind', 12);                   // story, article, fact or category
            $t->string('title', 300)->default('');    // what the picture is called (a slide heading, an article title ...)
            $t->string('parent', 300)->default('');   // the story, article ... it belongs to
            $t->string('url', 600)->default('');      // where a click goes
            $t->string('alt', 300)->default('');
            $t->unsignedBigInteger('root_id')->nullable()->index();   // the top-level category (the section)
            $t->boolean('is_shown')->default(true);   // off: the picture is left out of the gallery
            $t->boolean('present')->default(true);    // false: the picture is not on a published page any more
            $t->json('custom_data')->nullable();
            $t->timestamps();
            $t->index(['kind', 'is_shown']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gallery_pictures');
    }
};
