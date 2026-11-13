<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** An issue of the newsletter (written in the admin) and, for each issue, who it was sent to. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('newsletter_issues', function (Blueprint $table) {
            $table->id();
            $table->string('subject');
            $table->longText('body')->nullable();
            $table->string('status', 20)->default('draft')->index();      // draft, sending, sent
            $table->unsignedInteger('recipients_total')->default(0);
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('custom_data')->nullable();
            $table->timestamps();
        });

        // One line per person per issue, so an issue can never go twice to the same address, and a stopped send can carry on.
        Schema::create('newsletter_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('newsletter_issue_id')->constrained('newsletter_issues')->cascadeOnDelete();
            $table->foreignId('newsletter_subscriber_id')->constrained('newsletter_subscribers')->cascadeOnDelete();
            $table->string('status', 20)->default('pending')->index();    // pending, sent, failed, skipped
            $table->string('error', 300)->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->unique(['newsletter_issue_id', 'newsletter_subscriber_id'], 'newsletter_delivery_once');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('newsletter_deliveries');
        Schema::dropIfExists('newsletter_issues');
    }
};
