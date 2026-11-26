<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Schema 8 -> 9. Additive. Both retention settings default to null, meaning
 * "keep", so nothing is deleted until the owner chooses a period.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('portfolio_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('portfolio_settings', 'contact_retention_days')) {
                $table->unsignedSmallInteger('contact_retention_days')->nullable();
            }
            if (! Schema::hasColumn('portfolio_settings', 'activity_ip_retention_days')) {
                $table->unsignedSmallInteger('activity_ip_retention_days')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('portfolio_settings', function (Blueprint $table) {
            $table->dropColumn(['contact_retention_days', 'activity_ip_retention_days']);
        });
    }
};
