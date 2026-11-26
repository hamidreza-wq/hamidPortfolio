<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Schema 4 -> 5. Additive: a new testimonials table and one settings flag that
 * defaults to closed, so nothing changes publicly until the owner opts in.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('testimonials')) {
            Schema::create('testimonials', function (Blueprint $table) {
                $table->id();
                $table->string('name', 120);
                $table->string('role', 120)->nullable();
                $table->string('company', 120)->nullable();
                $table->text('quote');
                $table->unsignedTinyInteger('rating')->nullable();
                $table->string('source', 20)->default('manual');
                $table->string('source_url', 500)->nullable();
                // Private: used only to confirm a submission is genuine. Never published.
                $table->string('email')->nullable();
                $table->timestamp('consent_at')->nullable();
                $table->string('status', 20)->default('pending')->index();
                $table->boolean('is_featured')->default(false);
                $table->integer('sort_order')->default(0);
                $table->date('given_on')->nullable();
                $table->string('ip_hash', 64)->nullable();
                $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('reviewed_at')->nullable();
                $table->text('internal_note')->nullable();
                $table->timestamps();
            });
        }
        if (! Schema::hasColumn('portfolio_settings', 'testimonial_submissions_open')) {
            Schema::table('portfolio_settings', function (Blueprint $table) {
                $table->boolean('testimonial_submissions_open')->default(false);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('testimonials');
        Schema::table('portfolio_settings', function (Blueprint $table) {
            $table->dropColumn('testimonial_submissions_open');
        });
    }
};
