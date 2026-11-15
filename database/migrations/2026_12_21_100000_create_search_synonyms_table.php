<?php

use App\Models\SearchSynonym;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A group of words the search treats as the same: a visitor who types one also finds the others.
        Schema::create('search_synonyms', function (Blueprint $table) {
            $table->id();
            $table->json('terms');                       // the words of the group, written in a plain form (lower case, straight apostrophe)
            $table->string('note', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('custom_data')->nullable();
            $table->timestamps();
        });

        SearchSynonym::installDefaults();
    }

    public function down(): void
    {
        Schema::dropIfExists('search_synonyms');
    }
};
