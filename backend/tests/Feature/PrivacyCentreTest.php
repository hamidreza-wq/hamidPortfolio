<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\ContactMessage;
use App\Models\PortfolioSetting;
use App\Models\Testimonial;
use App\Models\User;
use App\Services\PrivacyData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PrivacyCentreTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'Long-test-password-2026!';

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed();
        $this->admin = User::factory()->create(['is_admin' => true, 'password' => self::PASSWORD]);
    }

    private function person(): void
    {
        ContactMessage::create(['name' => 'Dana', 'email' => 'Dana@Example.test', 'subject' => 'Hello', 'message' => 'Private words', 'status' => 'read', 'ip_hash' => str_repeat('a', 64)]);
        ContactMessage::create(['name' => 'Other', 'email' => 'other@example.test', 'subject' => 'Keep me', 'message' => 'x', 'status' => 'unread']);
        Testimonial::create(['name' => 'Dana', 'email' => 'dana@example.test', 'quote' => 'Great work.', 'status' => 'approved', 'source' => 'website', 'consent_at' => now()]);
    }

    public function test_page_requires_an_administrator_and_shows_the_inventory(): void
    {
        $this->get('/admin/privacy')->assertRedirect('/admin/login');
        $this->actingAs($this->admin)->get('/admin/privacy')->assertOk()
            ->assertSee('Personal data on this site')->assertSee('Contact messages')->assertSee('Kept until you delete them')
            ->assertSee("don't make the site legally compliant", false);
    }

    public function test_lookup_is_case_insensitive_and_uses_post(): void
    {
        $this->person();
        $this->actingAs($this->admin)->post('/admin/privacy/lookup', ['email' => 'dana@EXAMPLE.test'])->assertOk()
            ->assertSee('1 contact message(s)')->assertSee('1 testimonial(s)')->assertSee('(shown on the site)')
            ->assertDontSee('Keep me');
        $this->actingAs($this->admin)->post('/admin/privacy/lookup', ['email' => 'nobody@example.test'])->assertOk()->assertSee('Nothing to export or erase');
        $this->actingAs($this->admin)->get('/admin/privacy?email=dana@example.test')->assertOk()->assertDontSee('contact message(s)', false);
    }

    public function test_export_contains_only_that_persons_records_and_logs_without_the_address(): void
    {
        $this->person();
        $response = $this->actingAs($this->admin)->post('/admin/privacy/export', ['email' => 'dana@example.test'])->assertOk();
        $data = $response->json();

        $this->assertStringContainsString('attachment;', $response->headers->get('Content-Disposition'));
        $this->assertCount(1, $data['contact_messages']);
        $this->assertSame('Private words', $data['contact_messages'][0]['message']);
        $this->assertCount(1, $data['testimonials']);
        $this->assertStringNotContainsString('Keep me', $response->getContent());

        $log = AuditLog::where('action', 'privacy.exported')->sole();
        $this->assertStringNotContainsString('dana', strtolower(json_encode($log->details)));
        $this->assertSame('Request '.PrivacyData::reference('DANA@example.test'), $log->details['name']);
    }

    public function test_erase_needs_password_and_confirmation_and_removes_only_that_person(): void
    {
        $this->person();
        $this->actingAs($this->admin)->delete('/admin/privacy/erase', ['email' => 'dana@example.test', 'password' => 'wrong', 'confirm' => '1'])->assertSessionHasErrors('password');
        $this->actingAs($this->admin)->delete('/admin/privacy/erase', ['email' => 'dana@example.test', 'password' => self::PASSWORD])->assertSessionHasErrors('confirm');
        $this->assertSame(2, ContactMessage::count());

        $this->actingAs($this->admin)->delete('/admin/privacy/erase', ['email' => 'dana@example.test', 'password' => self::PASSWORD, 'confirm' => '1'])
            ->assertRedirect('/admin/privacy')->assertSessionHas('status');

        $this->assertSame(['other@example.test'], ContactMessage::pluck('email')->all());
        $this->assertSame(0, Testimonial::count());
        $this->assertSame(['messages' => 1, 'testimonials' => 1], array_intersect_key(AuditLog::where('action', 'privacy.erased')->sole()->details, ['messages' => 1, 'testimonials' => 1]));
    }

    public function test_retention_settings_prune_daily_and_update_the_public_notice(): void
    {
        $this->get('/privacy')->assertSee('kept in the inbox until the site owner deletes them');
        $this->actingAs($this->admin)->put('/admin/privacy/retention', ['contact_retention_days' => 37])->assertSessionHasErrors('contact_retention_days');
        $this->actingAs($this->admin)->put('/admin/privacy/retention', ['contact_retention_days' => 90, 'activity_ip_retention_days' => 180])->assertSessionHas('status');
        $this->assertSame([90, 180], [PortfolioSetting::find(1)->contact_retention_days, PortfolioSetting::find(1)->activity_ip_retention_days]);
        $this->get('/privacy')->assertSee('deleted from the inbox 90 days after they arrive');

        $old = ContactMessage::create(['name' => 'Old', 'email' => 'old@example.test', 'subject' => 'Old', 'message' => 'x', 'status' => 'read']);
        $old->forceFill(['created_at' => now()->subDays(91)])->save();
        ContactMessage::create(['name' => 'New', 'email' => 'new@example.test', 'subject' => 'New', 'message' => 'x', 'status' => 'unread']);
        $oldLog = AuditLog::create(['action' => 'authentication.failed', 'ip_address' => '203.0.113.5']);
        $oldLog->forceFill(['created_at' => now()->subDays(181)])->save();
        $newLog = AuditLog::create(['action' => 'authentication.failed', 'ip_address' => '203.0.113.6']);

        $removed = app(PrivacyData::class)->prune();

        $this->assertSame(['messages' => 1, 'activity_ips' => 1], $removed);
        $this->assertSame(['New'], ContactMessage::pluck('name')->all());
        $this->assertNull($oldLog->fresh()->ip_address);
        $this->assertSame('203.0.113.6', $newLog->fresh()->ip_address);
        $this->assertTrue($oldLog->fresh()->exists, 'entries stay; only the address is cleared');

        $this->actingAs($this->admin)->put('/admin/privacy/retention', [])->assertSessionHas('status');
        $this->assertNull(PortfolioSetting::find(1)->contact_retention_days, 'back to keep');
        $this->assertSame(['messages' => 0, 'activity_ips' => 0], app(PrivacyData::class)->prune());
    }

    public function test_public_notice_explains_rights_and_testimonials(): void
    {
        $this->get('/privacy')->assertOk()->assertSee('Your rights')->assertSee('Testimonials');
    }
}
