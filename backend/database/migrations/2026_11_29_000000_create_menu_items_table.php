<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Schema 6 -> 7. Additive. With no rows, the header menu stays automatic
 * (built from sections marked "Show in navigation"), so nothing changes until
 * the owner switches it to a custom menu.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('menu_items')) {
            return;
        }
        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            $table->string('menu', 20)->index();
            $table->string('type', 20);
            $table->string('target', 500);
            $table->string('label', 60)->nullable();
            $table->boolean('open_in_new_tab')->default(false);
            $table->boolean('is_button')->default(false);
            $table->boolean('is_enabled')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_items');
    }
};
