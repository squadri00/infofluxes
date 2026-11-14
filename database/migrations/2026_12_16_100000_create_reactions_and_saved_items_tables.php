<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A like (1) or a dislike (-1) of an article or a story. One per person and item.
        Schema::create('reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('reactable_type', 40);
            $table->unsignedBigInteger('reactable_id');
            $table->tinyInteger('value');
            $table->json('custom_data')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'reactable_type', 'reactable_id']);
            $table->index(['reactable_type', 'reactable_id']);
        });

        // An article or a story a person saved to read later.
        Schema::create('saved_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('saveable_type', 40);
            $table->unsignedBigInteger('saveable_id');
            $table->json('custom_data')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'saveable_type', 'saveable_id']);
            $table->index(['saveable_type', 'saveable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_items');
        Schema::dropIfExists('reactions');
    }
};
