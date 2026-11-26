<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Schema 5 -> 6. Additive. Seeds the existing GitHub and LinkedIn profile URLs
 * so the public site keeps showing the same links after the upgrade.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('social_links')) {
            return;
        }
        Schema::create('social_links', function (Blueprint $table) {
            $table->id();
            $table->string('platform', 30);
            $table->string('label', 60)->nullable();
            $table->string('url', 500);
            $table->boolean('is_enabled')->default(true);
            $table->boolean('show_in_header')->default(false);
            $table->boolean('show_in_footer')->default(true);
            $table->boolean('show_in_contact')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        $settings = DB::table('portfolio_settings')->where('id', 1)->first();
        $order = 0;
        foreach (['github' => $settings->github_url ?? null, 'linkedin' => $settings->linkedin_url ?? null] as $platform => $url) {
            if (is_string($url) && str_starts_with(strtolower($url), 'https://')) {
                DB::table('social_links')->insert([
                    'platform' => $platform, 'url' => $url, 'is_enabled' => true,
                    'show_in_header' => false, 'show_in_footer' => true, 'show_in_contact' => true,
                    'sort_order' => $order += 10, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('social_links');
    }
};
