<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // An old web address that leads to a new one. "auto" ones are made when the address of an article, story, category or page changes.
        Schema::create('redirects', function (Blueprint $table) {
            $table->id();
            $table->string('from_path', 500);
            $table->string('from_key', 500)->unique();                       // lower case, no trailing slash: what is looked up
            $table->string('to', 1000);                                      // a path on this site (/new-address) or a full web address
            $table->string('type', 10)->default('manual');                   // manual or auto
            $table->unsignedSmallInteger('code')->default(301);              // 301 = moved for good, 302 = for now
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('hits')->default(0);
            $table->timestamp('last_hit_at')->nullable();
            $table->string('subject_type', 120)->nullable();                 // for an auto one: the record whose address changed
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('note', 255)->nullable();
            $table->json('custom_data')->nullable();
            $table->timestamps();
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('redirects');
    }
};
