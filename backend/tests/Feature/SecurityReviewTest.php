<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/** Regression tests for fixes from the November 2026 security review (SECURITY-REVIEW.md). */
class SecurityReviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_private_disk_has_no_signed_url_routes(): void
    {
        $this->assertFalse(config('filesystems.disks.local.serve'));
        $this->assertFalse(collect(Route::getRoutes()->getRoutes())->contains(fn ($route) => str_starts_with($route->uri(), 'storage/')));
        $this->get('/storage/backups/anything.zip?signature=x&expires=9999999999')->assertNotFound();
    }

    public function test_sessions_are_encrypted_by_default_and_reported(): void
    {
        $this->assertStringContainsString("env('SESSION_ENCRYPT', true)", file_get_contents(config_path('session.php')), 'new installs encrypt sessions');
        $this->assertStringContainsString('SESSION_ENCRYPT=true', file_get_contents(base_path('.env.example')));
        config(['session.encrypt' => false]);
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->get('/admin/security')->assertSee('Session data is stored unencrypted');
        config(['session.encrypt' => true]);
        $this->actingAs($admin)->get('/admin/security')->assertSee('Session data is encrypted at rest');
    }

    public function test_cross_origin_opener_policy_is_sent(): void
    {
        $this->get('/')->assertHeader('Cross-Origin-Opener-Policy', 'same-origin');
    }

    public function test_hsts_include_subdomains_is_opt_in(): void
    {
        $this->app['env'] = 'production';
        $secure = fn () => $this->withServerVariables(['HTTPS' => 'on'])->get('https://localhost/');

        $secure()->assertHeader('Strict-Transport-Security', 'max-age=31536000');
        config(['portfolio.hsts_include_subdomains' => true]);
        $secure()->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }
}
