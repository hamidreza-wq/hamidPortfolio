<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Schema 7 -> 8. Additive. Saved themes can now hold fonts, layout and motion
 * as well as colours. Existing colour-only presets keep working unchanged.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('theme_presets', 'settings')) {
            Schema::table('theme_presets', function (Blueprint $table) {
                $table->json('settings')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::table('theme_presets', function (Blueprint $table) {
            $table->dropColumn('settings');
        });
    }
};
