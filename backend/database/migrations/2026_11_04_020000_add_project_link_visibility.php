<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('projects', 'repository_is_public')) {
            Schema::table('projects', function (Blueprint $table): void {
                $table->boolean('repository_is_public')->default(false);
            });
        }
        if (! Schema::hasColumn('projects', 'live_url_verified')) {
            Schema::table('projects', function (Blueprint $table): void {
                $table->boolean('live_url_verified')->default(false);
            });
        }
        DB::table('projects')->where('repository_url', 'https://github.com/hamidreza-wq/officeConverters')
            ->update(['repository_is_public' => true]);
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            $table->dropColumn(['repository_is_public', 'live_url_verified']);
        });
    }
};
