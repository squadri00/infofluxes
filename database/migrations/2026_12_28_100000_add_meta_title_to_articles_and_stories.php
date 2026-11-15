<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A separate "Meta title" (what Google shows), kept apart from the Page title. The AI import fills this one. */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['articles', 'stories'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->json('meta_title')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['articles', 'stories'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropColumn('meta_title'));
        }
    }
};
