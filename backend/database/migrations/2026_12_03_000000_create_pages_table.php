<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Schema 10 -> 11. Additive: standalone pages with soft delete (trash). */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pages')) {
            return;
        }
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->string('title', 120);
            $table->string('slug', 80)->unique();
            $table->string('summary', 320)->nullable();
            $table->mediumText('body');
            $table->string('status', 20)->default('draft')->index();
            $table->timestamp('publish_at')->nullable();
            $table->string('seo_title', 120)->nullable();
            $table->string('seo_description', 320)->nullable();
            $table->foreignId('media_asset_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->boolean('noindex')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pages');
    }
};
