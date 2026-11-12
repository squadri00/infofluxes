<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Step 1: who a person is on the site (role), whether they may log in (status), and which admin screens they may open. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('member')->after('password');       // superuser, moderator, author, member
            $table->string('status', 20)->default('active')->after('role');          // active, waiting, switched_off
            $table->json('admin_screens')->nullable()->after('status');              // the ticked admin screens (empty = the role's usual screens)
            $table->json('custom_data')->nullable()->after('admin_screens');         // extra items the owner wants to keep
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'status', 'admin_screens', 'custom_data']);
        });
    }
};
