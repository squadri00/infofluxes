<?php

use App\Models\Language;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The languages of the site. Only English is on to start with; others are added here when they are ready.
        Schema::create('languages', function (Blueprint $table) {
            $table->id();
            $table->string('code', 12)->unique();                   // en, ar, ur ...
            $table->string('name', 80);                             // in English: Arabic
            $table->string('native_name', 80);                      // in the language itself
            $table->string('flag', 4)->default('un');               // the picture in public/flags
            $table->boolean('is_rtl')->default(false);              // written from right to left
            $table->string('status', 12)->default('off');           // active, coming_soon or off
            $table->boolean('is_default')->default(false);          // the main language: it is always on
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('custom_data')->nullable();
            $table->timestamps();
        });

        Language::installDefaults();
    }

    public function down(): void
    {
        Schema::dropIfExists('languages');
    }
};
