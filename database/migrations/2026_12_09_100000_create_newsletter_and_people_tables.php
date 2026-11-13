<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** The newsletter list, and the contributors (people credited on articles, with a page of their own). */
return new class extends Migration
{
    public function up(): void
    {
        // Someone who asked for the newsletter. Only an address confirmed by a code sent to it is "active".
        Schema::create('newsletter_subscribers', function (Blueprint $table) {
            $table->id();
            $table->string('email', 190)->unique();
            $table->string('status', 20)->default('pending')->index();   // pending, active, unsubscribed
            $table->string('unsubscribe_token', 64)->unique();
            $table->string('code_hash', 64)->nullable();                  // a one-way fingerprint, never the code
            $table->timestamp('code_expires_at')->nullable();
            $table->timestamp('code_sent_at')->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->string('source', 40)->nullable();
            $table->string('language', 10)->nullable();
            $table->timestamp('consent_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('unsubscribed_at')->nullable();
            $table->json('custom_data')->nullable();
            $table->timestamps();
        });

        // A contributor: a name, a role, a picture and (when switched on) a page of their own.
        Schema::create('people', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('role_title')->nullable();
            $table->string('tradition')->nullable();
            $table->text('bio')->nullable();
            $table->text('qualification_note')->nullable();
            $table->string('photo')->nullable();
            $table->string('photo_alt')->nullable();
            $table->string('link', 2048)->nullable();
            $table->boolean('show_page')->default(false);
            $table->longText('page_body')->nullable();
            $table->json('custom_data')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('articles', function (Blueprint $table) {
            $table->foreignId('contributor_id')->nullable()->after('author_id')->constrained('people')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('contributor_id');
        });
        Schema::dropIfExists('people');
        Schema::dropIfExists('newsletter_subscribers');
    }
};
