<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Schema 11 -> 12. Additive. Existing administrators become owners, so nobody
 * loses access. The setup columns hold a hashed, expiring one-time link for
 * people invited from the Users page.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'role')) {
                $table->string('role', 20)->nullable();
            }
            if (! Schema::hasColumn('users', 'setup_token_hash')) {
                $table->string('setup_token_hash', 64)->nullable()->unique();
            }
            if (! Schema::hasColumn('users', 'setup_expires_at')) {
                $table->timestamp('setup_expires_at')->nullable();
            }
        });
        DB::table('users')->where('is_admin', true)->whereNull('role')->update(['role' => 'owner']);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['setup_token_hash']);
            $table->dropColumn(['role', 'setup_token_hash', 'setup_expires_at']);
        });
    }
};
