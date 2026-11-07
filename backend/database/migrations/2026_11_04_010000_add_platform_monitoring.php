<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A failed MySQL DDL migration can leave its first table behind.
        if (! Schema::hasTable('platform_runs')) {
            Schema::create('platform_runs', function (Blueprint $table): void {
                $table->id();
                $table->string('kind', 30)->index();
                $table->string('status', 30);
                $table->text('message')->nullable();
                $table->json('summary')->nullable();
                $table->timestamps();
            });
        }
        Schema::create('platform_notices', function (Blueprint $table): void {
            $table->id();
            $table->string('fingerprint', 64)->unique();
            $table->string('category', 30)->index();
            $table->string('priority', 15);
            $table->string('title');
            $table->text('message');
            $table->string('route_name');
            $table->timestamp('read_at')->nullable();
            $table->timestamp('dismissed_at')->nullable();
            $table->timestamp('snoozed_until')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('last_seen_at')->useCurrent();
            $table->timestamps();
        });
        Schema::create('release_candidates', function (Blueprint $table): void {
            $table->id();
            $table->string('tag', 80)->unique();
            $table->string('version', 80);
            $table->string('status', 30)->default('available');
            $table->text('notes')->nullable();
            $table->timestamp('released_at')->useCurrent();
            $table->json('release_assets');
            $table->json('manifest')->nullable();
            $table->json('inspection')->nullable();
            $table->string('artifact_path')->nullable();
            $table->string('artifact_sha256', 64)->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });
        Schema::create('github_repositories', function (Blueprint $table): void {
            $table->unsignedBigInteger('id')->primary();
            $table->string('full_name');
            $table->string('state', 30)->default('pending')->index();
            $table->json('metadata');
            $table->json('previous_metadata')->nullable();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('last_seen_at')->useCurrent();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('publication_approved_at')->nullable();
            $table->timestamps();
        });
        Schema::create('seo_findings', function (Blueprint $table): void {
            $table->id();
            $table->string('fingerprint', 64)->unique();
            $table->string('path');
            $table->string('check', 60);
            $table->string('priority', 15);
            $table->text('message');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('last_seen_at')->useCurrent();
            $table->timestamps();
        });
        Schema::table('projects', function (Blueprint $table): void {
            $table->string('author_name', 120)->nullable();
            $table->string('maintainer_name', 120)->nullable();
            $table->string('license_name', 160)->nullable();
            $table->text('attribution')->nullable();
            $table->string('copyright_holder', 120)->nullable();
            $table->timestamp('rights_confirmed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            $table->dropColumn(['author_name', 'maintainer_name', 'license_name', 'attribution', 'copyright_holder', 'rights_confirmed_at']);
        });
        Schema::dropIfExists('seo_findings');
        Schema::dropIfExists('github_repositories');
        Schema::dropIfExists('release_candidates');
        Schema::dropIfExists('platform_notices');
        Schema::dropIfExists('platform_runs');
    }
};
