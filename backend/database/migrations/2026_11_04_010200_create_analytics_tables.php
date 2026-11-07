<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('page_views', function (Blueprint $table): void {
            $table->id();
            $table->string('path', 255);
            $table->string('referrer_host', 255)->nullable();
            $table->timestamp('viewed_at')->useCurrent();
            $table->index('viewed_at');
            $table->index(['path', 'viewed_at']);
            $table->index(['referrer_host', 'viewed_at']);
        });

        Schema::create('google_search_console_connections', function (Blueprint $table): void {
            $table->id();
            $table->string('site_url', 255)->unique();
            $table->text('refresh_token');
            $table->timestamp('connected_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('google_search_console_connections');
        Schema::dropIfExists('page_views');
    }
};
