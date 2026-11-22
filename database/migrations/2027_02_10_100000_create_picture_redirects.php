<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** When a picture file gets a new name (for search engines), the old web address still works: it goes to the new one (301). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('picture_redirects', function (Blueprint $t) {
            $t->id();
            $t->string('old_path', 500)->unique();   // for example "stories/x7k2m9q4w1.webp" (inside the public storage folder)
            $t->string('new_path', 500);
            $t->json('custom_data')->nullable();
            $t->timestamps();
            $t->index('new_path');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('picture_redirects');
    }
};
