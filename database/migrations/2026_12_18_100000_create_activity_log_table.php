<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One line of the history: who did what to which record, and what changed.
        Schema::create('activity_log', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->index();        // null = the system (a command), not a person
            $table->string('event', 20);                                       // created, updated, deleted, restored, purged
            $table->string('subject_type', 120);
            $table->unsignedBigInteger('subject_id');
            $table->string('subject_label', 255)->nullable();                  // what the record was called then (it may be gone later)
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('source', 12)->default('web');                      // web or command
            $table->json('custom_data')->nullable();
            $table->timestamp('created_at')->nullable()->index();
            $table->index(['subject_type', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_log');
    }
};
