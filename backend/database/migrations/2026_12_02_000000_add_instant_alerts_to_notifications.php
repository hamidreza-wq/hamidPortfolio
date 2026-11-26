<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Schema 9 -> 10. Additive. Adds opt-in immediate email for high-priority
 * notices, and turns the new notice categories on for administrators who
 * already saved preferences (their existing choices are kept).
 */
return new class extends Migration
{
    private const NEW_CATEGORIES = ['security', 'health', 'backups', 'messages'];

    public function up(): void
    {
        if (! Schema::hasColumn('notification_preferences', 'instant_alerts')) {
            Schema::table('notification_preferences', function (Blueprint $table) {
                $table->boolean('instant_alerts')->default(false);
            });
        }
        if (! Schema::hasColumn('platform_notices', 'alerted_at')) {
            Schema::table('platform_notices', function (Blueprint $table) {
                $table->timestamp('alerted_at')->nullable();
            });
            // Existing notices are history, not new alerts.
            DB::table('platform_notices')->update(['alerted_at' => now()]);
        }
        foreach (DB::table('notification_preferences')->get(['id', 'categories']) as $row) {
            $categories = json_decode((string) $row->categories, true) ?: [];
            $merged = array_values(array_unique([...$categories, ...self::NEW_CATEGORIES]));
            DB::table('notification_preferences')->where('id', $row->id)->update(['categories' => json_encode($merged)]);
        }
        // Health and backup notices were filed under "updates" before they had their own categories.
        DB::table('platform_notices')->where('route_name', 'admin.health')->update(['category' => 'health']);
        DB::table('platform_notices')->where('route_name', 'admin.backups.index')->update(['category' => 'backups']);
    }

    public function down(): void
    {
        Schema::table('notification_preferences', fn (Blueprint $table) => $table->dropColumn('instant_alerts'));
        Schema::table('platform_notices', fn (Blueprint $table) => $table->dropColumn('alerted_at'));
    }
};
