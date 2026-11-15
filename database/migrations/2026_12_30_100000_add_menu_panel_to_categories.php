<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** The big menu panel: a small icon for each category, and a feature card for each main menu heading. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->string('menu_icon', 30)->nullable();       // one of App\Support\MenuIcons
            $table->json('feature_title')->nullable();         // the card on the right of the panel (all optional)
            $table->json('feature_text')->nullable();
            $table->json('feature_button')->nullable();
            $table->string('feature_image')->nullable();
            $table->string('feature_url', 500)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn(['menu_icon', 'feature_title', 'feature_text', 'feature_button', 'feature_image', 'feature_url']);
        });
    }
};
