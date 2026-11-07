<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('font_assets', function (Blueprint $table) {
            $table->string('source_url', 2048)->nullable();
            $table->boolean('is_active')->default(false)->index();
            $table->string('original_name')->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
        });
        Schema::table('media_assets', fn (Blueprint $table) => $table->boolean('reencoded')->default(false));
        Schema::table('portfolio_settings', fn (Blueprint $table) => $table->foreignId('og_image_media_id')->nullable()->constrained('media_assets')->restrictOnDelete());
        Schema::table('projects', fn (Blueprint $table) => $table->foreignId('media_asset_id')->nullable()->constrained('media_assets')->restrictOnDelete());
    }

    public function down(): void
    {
        Schema::table('projects', fn (Blueprint $table) => $table->dropConstrainedForeignId('media_asset_id'));
        Schema::table('portfolio_settings', fn (Blueprint $table) => $table->dropConstrainedForeignId('og_image_media_id'));
        Schema::table('media_assets', fn (Blueprint $table) => $table->dropColumn('reencoded'));
        Schema::table('font_assets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('uploaded_by');
            $table->dropColumn(['source_url', 'is_active', 'original_name', 'size_bytes']);
        });
    }
};
