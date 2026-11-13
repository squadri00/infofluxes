<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** The home page banner: words on one side (one set at random on each visit) and a slider of pictures with words and a button over them. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hero_messages', function (Blueprint $table) {
            $table->id();
            $table->json('title');
            $table->json('subtitle')->nullable();
            $table->json('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('custom_data')->nullable();
            $table->timestamps();
        });

        Schema::create('hero_slides', function (Blueprint $table) {
            $table->id();
            $table->string('image');
            $table->json('image_alt')->nullable();
            $table->json('heading')->nullable();
            $table->json('text')->nullable();               // one short line over the picture
            $table->json('button_label')->nullable();
            $table->string('button_url', 2048)->nullable();
            $table->boolean('new_tab')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('custom_data')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hero_slides');
        Schema::dropIfExists('hero_messages');
    }
};
