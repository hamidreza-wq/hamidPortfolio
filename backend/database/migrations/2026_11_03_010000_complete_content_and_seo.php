<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('portfolio_settings', function (Blueprint $table) {
            $table->string('language', 35)->default('en');
            $table->boolean('indexing_enabled')->default(true);
            $table->boolean('robots_follow')->default(true);
            $table->string('og_title')->nullable();
            $table->string('og_description', 320)->nullable();
        });
        Schema::table('portfolio_sections', function (Blueprint $table) {
            $table->string('type', 30)->default('custom');
            $table->boolean('show_in_navigation')->default(true);
            $table->json('items')->nullable();
            $table->foreignId('image_media_id')->nullable()->constrained('media_assets')->restrictOnDelete();
            $table->foreignId('background_media_id')->nullable()->constrained('media_assets')->restrictOnDelete();
        });
        foreach (['home' => 'hero', 'about' => 'about', 'work' => 'projects', 'craft' => 'skills', 'journey' => 'experience', 'contact' => 'contact'] as $key => $type) {
            DB::table('portfolio_sections')->where('key', $key)->update(['type' => $type]);
        }
        Schema::table('projects', function (Blueprint $table) {
            $table->json('gallery_media_ids')->nullable();
        });
        Schema::create('project_slug_redirects', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 160)->unique();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
        });
        Schema::create('content_revisions', function (Blueprint $table) {
            $table->id();
            $table->string('scope', 20);
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('label');
            $table->json('snapshot');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_revisions');
        Schema::dropIfExists('project_slug_redirects');
        Schema::table('projects', fn (Blueprint $table) => $table->dropColumn('gallery_media_ids'));
        Schema::table('portfolio_sections', function (Blueprint $table) {
            $table->dropConstrainedForeignId('image_media_id');
            $table->dropConstrainedForeignId('background_media_id');
            $table->dropColumn(['type', 'show_in_navigation', 'items']);
        });
        Schema::table('portfolio_settings', fn (Blueprint $table) => $table->dropColumn(['language', 'indexing_enabled', 'robots_follow', 'og_title', 'og_description']));
    }
};
