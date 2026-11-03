<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('portfolio_settings', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->string('name')->default('Hamid Reza Bayat');
            $table->string('professional_title')->default('Software Developer');
            $table->string('github_url')->default('https://github.com/hamidreza-wq');
            $table->string('email')->nullable();
            $table->string('linkedin_url')->nullable();
            $table->string('site_title')->default('Hamid Reza Bayat — Software Developer');
            $table->string('site_description', 320)->default('A small universe of useful software, carefully built.');
            $table->string('canonical_url')->nullable();
            $table->string('og_image')->nullable();
            $table->string('color_background', 7)->default('#070811');
            $table->string('color_foreground', 7)->default('#f3f1f8');
            $table->string('color_primary', 7)->default('#ad91ff');
            $table->string('color_accent', 7)->default('#c4b1ff');
            $table->string('color_cyan', 7)->default('#92d8ee');
            $table->string('font_body')->default('Inter');
            $table->string('font_heading')->default('Inter');
            $table->unsignedSmallInteger('max_width')->default(1180);
            $table->unsignedSmallInteger('section_spacing')->default(140);
            $table->unsignedTinyInteger('border_radius')->default(12);
            $table->boolean('animations_enabled')->default(true);
            $table->boolean('particles_enabled')->default(true);
            $table->boolean('maintenance_mode')->default(false);
            $table->text('footer_text')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('portfolio_settings');
    }
};
