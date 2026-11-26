<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SecurityCentreTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_security_page_requires_an_administrator(): void
    {
        $this->get('/admin/security')->assertRedirect('/admin/login');
        $this->actingAs(User::factory()->create(['is_admin' => false]))
            ->get('/admin/security')->assertForbidden();
    }

    public function test_security_page_shows_checks_failed_sign_ins_and_the_activity_log(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        AuditLog::create(['action' => 'authentication.failed', 'ip_address' => '203.0.113.9']);
        AuditLog::create(['action' => 'authentication.failed', 'ip_address' => '203.0.113.9']);
        AuditLog::create(['user_id' => $admin->id, 'action' => 'media.updated', 'details' => ['name' => 'Cover art']]);

        $this->actingAs($admin)->get('/admin/security')
            ->assertOk()
            ->assertSee('Protection checks')
            ->assertSee('Two-factor sign-in')
            ->assertSee('203.0.113.9')
            ->assertSee('Unknown visitor')
            ->assertSee('Cover art');

        $this->actingAs($admin)->get('/admin/security?filter=failed')
            ->assertOk()
            ->assertDontSee('Cover art');
    }

    public function test_unknown_filter_falls_back_to_all_activity(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        AuditLog::create(['user_id' => $admin->id, 'action' => 'media.updated', 'details' => ['name' => 'Cover art']]);

        $this->actingAs($admin)->get('/admin/security?filter=%27or1=1')
            ->assertOk()
            ->assertSee('Cover art');
    }

    public function test_signing_out_other_sessions_requires_the_current_password(): void
    {
        config(['session.driver' => 'database']);
        $admin = User::factory()->create(['is_admin' => true, 'password' => 'Long-test-password-2026!']);
        DB::table('sessions')->insert([
            'id' => 'other-session-id-0000000000000000000000',
            'user_id' => $admin->id,
            'ip_address' => '198.51.100.4',
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0) Chrome/130.0',
            'payload' => '',
            'last_activity' => now()->timestamp,
        ]);

        $this->actingAs($admin)->delete('/admin/security/sessions', ['password' => 'wrong-password'])
            ->assertSessionHasErrors('password');
        $this->assertDatabaseHas('sessions', ['id' => 'other-session-id-0000000000000000000000']);

        $this->actingAs($admin)->delete('/admin/security/sessions', ['password' => 'Long-test-password-2026!'])
            ->assertRedirect('/admin/security');
        $this->assertDatabaseMissing('sessions', ['id' => 'other-session-id-0000000000000000000000']);
        $this->assertDatabaseHas('audit_logs', ['user_id' => $admin->id, 'action' => 'security.sessions_revoked']);
    }

    public function test_overview_groups_repeated_actions_and_links_to_the_seo_monitor(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        foreach (range(1, 5) as $i) {
            AuditLog::create(['user_id' => $admin->id, 'action' => 'media.updated']);
        }

        $this->actingAs($admin)->get('/admin')
            ->assertOk()
            ->assertSee('× 5', false)
            ->assertSee(route('admin.platform.seo'), false)
            ->assertSee(route('admin.security'), false);
    }
}
