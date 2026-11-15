<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row for each source link of an article, with what the last check found.
        Schema::create('link_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained('articles')->cascadeOnDelete();
            $table->text('url');
            $table->string('url_hash', 40);                                   // sha1 of the url: one row for each link of an article
            $table->string('ref', 400)->nullable();                           // how the source is named in the article
            $table->string('status', 12)->default('new');                     // new, ok, broken, blocked, skipped
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->timestamp('checked_at')->nullable()->index();
            $table->timestamp('failed_since')->nullable();                    // since when it has been failing
            $table->boolean('queued')->default(false)->index();               // asked to be checked in the running sweep
            $table->boolean('ignored')->default(false);
            $table->timestamp('manual_ok_at')->nullable();                    // a person opened it by hand and it was fine
            $table->unsignedBigInteger('manual_ok_by')->nullable();
            $table->json('custom_data')->nullable();
            $table->timestamps();
            $table->unique(['article_id', 'url_hash']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('link_checks');
    }
};
