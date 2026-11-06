<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class RequestSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_html_has_nonce_csp_and_is_not_shared_cached(): void
    {
        $response = $this->get('/')->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("frame-ancestors 'self'", $csp);
        $this->assertStringNotContainsString("'unsafe-eval'", $csp);
        $this->assertStringContainsString('https://www.googletagmanager.com', $csp);
        $this->assertStringContainsString('https://*.google-analytics.com', $csp);
        preg_match("/script-src 'self' 'nonce-([^']+)'/", $csp, $match);
        $this->assertNotEmpty($match[1]);
        $response->assertSee('nonce="'.$match[1].'"', false);
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_non_administrator_cannot_login_or_access_inbox(): void
    {
        $user = User::factory()->create(['password' => 'Long-test-password!']);
        $this->post('/admin/login', ['email' => $user->email, 'password' => 'Long-test-password!'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->actingAs($user)->get('/admin/inbox')->assertForbidden();
        $this->actingAs($user)->get('/preview')->assertForbidden();
    }

    public function test_admin_login_has_nonce_headers_and_https_production_has_hsts(): void
    {
        $this->get('/admin/login')->assertOk()
            ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()')
            ->assertSee('nonce=', false);
        $this->app['env'] = 'production';
        $this->get('https://localhost/admin/login')->assertOk()
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000');
        $this->get('http://localhost/admin/login')->assertHeaderMissing('Strict-Transport-Security');
    }

    public function test_login_cannot_redirect_to_an_external_intended_url(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'password' => 'Long-test-password!']);
        $this->withSession(['url.intended' => 'https://attacker.example/'])->post('/admin/login', [
            'email' => $admin->email,
            'password' => 'Long-test-password!',
        ])->assertRedirect('/admin');
        $this->assertDatabaseHas('audit_logs', ['action' => 'authentication.login', 'user_id' => $admin->id]);
    }

    public function test_user_mass_assignment_cannot_grant_admin_privileges(): void
    {
        $user = new User;
        $user->fill(['name' => 'Ordinary', 'email' => 'ordinary@example.test', 'password' => 'Long-password!', 'is_admin' => true]);
        $this->assertFalse((bool) $user->is_admin);
    }

    public function test_contact_rejects_honeypot_and_forged_or_early_tokens(): void
    {
        $payload = $this->contactPayload();
        $this->post('/contact', [...$payload, 'website' => 'spam'])->assertSessionHasErrors('website');
        $this->post('/contact', [...$payload, 'submission_token' => 'forged'])->assertSessionHasErrors('message');
        $this->post('/contact', [...$payload, 'submission_token' => Crypt::encryptString((string) time())])->assertSessionHasErrors('message');
        $this->assertDatabaseCount('contact_messages', 0);
    }

    public function test_contact_token_cannot_be_reused(): void
    {
        $payload = $this->contactPayload();
        $this->post('/contact', $payload)->assertSessionHasNoErrors();
        $this->post('/contact', $payload)->assertSessionHasErrors('message');
        $this->assertDatabaseCount('contact_messages', 1);
    }

    public function test_contact_email_limit_survives_ip_changes(): void
    {
        for ($i = 1; $i <= 3; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => "192.0.2.{$i}"])
                ->post('/contact', $this->contactPayload())->assertSessionHasNoErrors();
        }
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.4'])
            ->post('/contact', $this->contactPayload())->assertSessionHasErrors('message');
        $this->assertDatabaseCount('contact_messages', 3);
    }

    public function test_private_files_are_not_publicly_served(): void
    {
        foreach (['/.env', '/.git/config', '/composer.json', '/phpunit.xml'] as $path) {
            $this->get($path)->assertNotFound();
        }
        $this->get('/storage/logs/laravel.log')->assertForbidden();
    }

    public function test_csrf_is_enforced_when_test_bypass_is_disabled(): void
    {
        $this->app['env'] = 'local';
        $this->post('/contact', $this->contactPayload())->assertStatus(419);
        $this->post('/admin/login', ['email' => 'owner@example.test', 'password' => 'guess'])->assertStatus(419);
    }

    public function test_login_regenerates_session_and_logout_invalidates_it(): void
    {
        $admin = User::factory()->create(['is_admin' => true, 'password' => 'Long-test-password!']);
        $this->get('/admin/login');
        $oldId = session()->getId();
        $this->post('/admin/login', ['email' => $admin->email, 'password' => 'Long-test-password!'])->assertRedirect('/admin');
        $this->assertNotSame($oldId, session()->getId());
        $this->post('/admin/logout')->assertRedirect('/');
        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', ['action' => 'authentication.logout']);
    }

    public function test_login_attempts_are_rate_limited(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post('/admin/login', ['email' => 'missing@example.test', 'password' => 'guess'])->assertSessionHasErrors('email');
        }
        $this->post('/admin/login', ['email' => 'missing@example.test', 'password' => 'guess'])->assertStatus(429);
        $this->assertGuest();
    }

    public function test_cli_cannot_promote_an_existing_ordinary_user(): void
    {
        $user = User::factory()->create(['email' => 'ordinary@example.test']);
        $this->artisan('portfolio:admin:create')
            ->expectsQuestion('Administrator name', 'Owner')
            ->expectsQuestion('Administrator email', $user->email)
            ->expectsQuestion('Password (minimum 12 characters)', 'A-long-password!')
            ->assertFailed();
        $this->assertFalse($user->fresh()->is_admin);
    }

    public function test_cli_reset_requires_explicit_confirmation(): void
    {
        $admin = User::factory()->create(['email' => 'owner@example.test', 'is_admin' => true]);
        $hash = $admin->password;
        $this->artisan('portfolio:admin:create')
            ->expectsQuestion('Administrator name', 'Owner')
            ->expectsQuestion('Administrator email', $admin->email)
            ->expectsQuestion('Password (minimum 12 characters)', 'A-new-long-password!')
            ->expectsConfirmation('Reset this administrator password and revoke remembered sign-ins?', 'no')
            ->assertFailed();
        $this->assertSame($hash, $admin->fresh()->password);
    }

    private function contactPayload(): array
    {
        return [
            'name' => 'Visitor',
            'email' => 'visitor@example.test',
            'subject' => 'Hello',
            'message' => 'A real project enquiry.',
            'submission_token' => Crypt::encryptString((string) (time() - 5)),
        ];
    }
}
