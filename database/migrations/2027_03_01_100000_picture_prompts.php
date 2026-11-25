<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A picture row of an article can wait for its picture: the picture may be empty, and the row keeps the prompt for the image maker. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('article_images', function (Blueprint $table) {
            $table->string('image')->nullable()->change();
            $table->text('prompt')->nullable()->after('image');
        });
    }

    public function down(): void
    {
        Schema::table('article_images', function (Blueprint $table) {
            $table->dropColumn('prompt');
        });
    }
};
