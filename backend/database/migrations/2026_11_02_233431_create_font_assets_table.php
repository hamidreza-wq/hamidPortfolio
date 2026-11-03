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
        Schema::create('font_assets', function (Blueprint $table) {
            $table->id();
            $table->string('family');
            $table->string('weight', 20)->default('400');
            $table->string('style', 20)->default('normal');
            $table->string('format', 10);
            $table->string('disk')->default('local');
            $table->string('path');
            $table->string('license_note')->nullable();
            $table->boolean('license_confirmed')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('font_assets');
    }
};
