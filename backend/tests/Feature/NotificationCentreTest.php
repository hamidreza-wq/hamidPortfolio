<?php

namespace Tests\Feature;

use App\Mail\PlatformAlert;
use App\Models\AuditLog;
use App\Models\ContactMessage;
use App\Models\NotificationPreference;
use App\Models\PlatformNotice;
use App\Models\Testimonial;
use App\Models\User;
use App\Services\Alerts;
use App\Services\Platform\Notices;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class NotificationCentreTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->admin = User::factory()->create(['is_admin' => true, 'email' => 'owner@example.test']);
    }

    private function notice(string $title): ?PlatformNotice
    {
        return PlatformNotice::where('title', $title)->first();
    }

    public function test_contact_messages_raise_a_count_only_notice_that_clears_when_read(): void
    {
        $this->post('/contact', [
            'name' => 'Private Person', 'email' => 'p@example.test', 'subject' => 'Secret subject', 'message' => 'Secret body',
            'website' => '', 'submission_token' => Crypt::encryptString((string) (time() - 5)),
        ]);
        $notice = $this->notice('New contact messages');
        $this->assertNotNull($notice);
        $this->assertSame('messages', $notice->category);
        $this->assertStringContainsString('1 unread', $notice->message);
        $this->assertStringNotContainsString('Private Person', $notice->message);
        $this->assertStringNotContainsString('Secret', $notice->message);

        $message = ContactMessage::sole();
        $this->actingAs($this->admin)->patch("/admin/inbox/{$message->id}", ['status' => 'read']);
        $this->assertNotNull($notice->fresh()->resolved_at);
    }

    public function test_testimonials_waiting_for_review_raise_and_clear_a_notice(): void
    {
        $testimonial = Testimonial::create(['name' => 'T', 'quote' => 'Great.', 'status' => 'pending', 'source' => 'website']);
        app(Alerts::class)->testimonialsChanged();
        $this->assertStringContainsString('1 testimonial', $this->notice('Testimonials waiting for review')->message);

        $this->actingAs($this->admin)->patch("/admin/testimonials/{$testimonial->id}/status", ['status' => 'approved']);
        $this->assertNotNull($this->notice('Testimonials waiting for review')->resolved_at);
    }

    public function test_security_events_raise_high_priority_notices(): void
    {
        foreach (range(1, Alerts::FAILED_SIGN_IN_THRESHOLD) as $i) {
            AuditLog::create(['action' => 'authentication.failed', 'ip_address' => '203.0.113.'.$i]);
        }
        $this->post('/admin/login', ['email' => 'owner@example.test', 'password' => 'wrong']);
        $this->assertSame('high', $this->notice('Many failed sign-in attempts')->priority);

        app(Alerts::class)->twoFactorDisabled('from the server command line');
        $this->assertSame('security', $this->notice('Two-factor sign-in was turned off')->category);
        app(Alerts::class)->twoFactorEnabled();
        $this->assertNotNull($this->notice('Two-factor sign-in was turned off')->resolved_at);
    }

    public function test_immediate_alerts_are_opt_in_batched_and_never_repeat_for_reworded_messages(): void
    {
        Mail::fake();
        $notices = app(Notices::class);
        $notices->put('test:one', 'health', 'high', 'Disk nearly full', 'Only 100 MB free.', 'admin.health');

        $this->artisan('platform:alerts')->assertSuccessful();
        Mail::assertNothingSent();
        $this->assertNotNull($this->notice('Disk nearly full')->alerted_at, 'nobody opted in, so it is marked handled');

        NotificationPreference::forUser($this->admin->id)->fill(['instant_alerts' => true, 'digest_frequency' => 'never'])->save();
        $notices->put('test:two', 'backups', 'high', 'Backup failed', 'Disk full.', 'admin.backups.index');
        $notices->put('test:three', 'security', 'high', 'Two-factor sign-in was turned off', 'From the CLI.', 'admin.security');
        $notices->put('test:low', 'messages', 'low', 'New contact messages', '1 unread.', 'admin.inbox.index');

        $this->artisan('platform:alerts')->assertSuccessful();
        Mail::assertSent(PlatformAlert::class, fn ($mail) => $mail->hasTo('owner@example.test') && count($mail->items) === 2);

        $notices->put('test:four', 'health', 'high', 'Certificate expiring', '3 days left.', 'admin.health');
        $this->artisan('platform:alerts');
        Mail::assertSent(PlatformAlert::class, 1);
        $this->assertNull($this->notice('Certificate expiring')->alerted_at, 'held until the 15-minute window passes');

        $this->travel(16)->minutes();
        $this->artisan('platform:alerts');
        Mail::assertSent(PlatformAlert::class, 2);

        $notices->put('test:four', 'health', 'high', 'Certificate expiring', '2 days left.', 'admin.health');
        $this->travel(16)->minutes();
        $this->artisan('platform:alerts');
        Mail::assertSent(PlatformAlert::class, 2, 'a reworded but unresolved problem does not email again');

        $notices->resolve('test:four');
        $notices->put('test:four', 'health', 'high', 'Certificate expiring', '1 day left.', 'admin.health');
        $this->artisan('platform:alerts');
        Mail::assertSent(PlatformAlert::class, 3, 'a problem that comes back after being fixed alerts again');
    }

    public function test_alerts_respect_category_choices(): void
    {
        Mail::fake();
        NotificationPreference::forUser($this->admin->id)->fill(['instant_alerts' => true, 'categories' => ['security'], 'digest_frequency' => 'never'])->save();
        app(Notices::class)->put('test:seo', 'seo', 'high', 'Site blocks search engines', 'robots.txt disallows everything.', 'admin.platform.seo');

        $this->artisan('platform:alerts');
        Mail::assertNothingSent();
    }

    public function test_page_filters_mark_all_read_and_preferences(): void
    {
        $notices = app(Notices::class);
        $notices->put('test:a', 'security', 'high', 'Security thing', 'A.', 'admin.security');
        $notices->put('test:b', 'seo', 'low', 'SEO thing', 'B.', 'admin.platform.seo');

        $this->actingAs($this->admin)->get('/admin/notifications')->assertOk()->assertSee('Security thing')->assertSee('SEO thing')->assertSee('All · 2');
        $this->actingAs($this->admin)->get('/admin/notifications?category=security')->assertOk()->assertSee('Security thing')->assertDontSee('SEO thing');
        $this->actingAs($this->admin)->get('/admin/notifications?category=bogus')->assertSessionHasErrors('category');

        $this->actingAs($this->admin)->post('/admin/notifications/read-all', ['category' => 'security'])->assertSessionHas('status', 'Marked 1 notification(s) as read.');
        $this->assertNotNull($this->notice('Security thing')->read_at);
        $this->assertNull($this->notice('SEO thing')->read_at);

        $this->actingAs($this->admin)->put('/admin/notification-preferences', ['categories' => ['security', 'health'], 'digest_frequency' => 'daily', 'instant_alerts' => '1'])->assertSessionHas('status');
        $preference = NotificationPreference::forUser($this->admin->id);
        $this->assertSame(['security', 'health'], $preference->categories);
        $this->assertTrue($preference->instant_alerts);
        $this->actingAs($this->admin)->put('/admin/notification-preferences', ['categories' => ['nope'], 'digest_frequency' => 'daily'])->assertSessionHasErrors('categories.0');
    }

    public function test_upgrade_turns_new_categories_on_for_existing_preferences(): void
    {
        \DB::table('notification_preferences')->insert(['user_id' => $this->admin->id, 'categories' => json_encode(['updates', 'seo']), 'digest_frequency' => 'weekly', 'created_at' => now(), 'updated_at' => now()]);
        \Schema::table('notification_preferences', fn ($table) => $table->dropColumn('instant_alerts'));
        \Schema::table('platform_notices', fn ($table) => $table->dropColumn('alerted_at'));
        \DB::table('migrations')->where('migration', '2026_12_02_000000_add_instant_alerts_to_notifications')->delete();

        $this->artisan('migrate', ['--path' => 'database/migrations/2026_12_02_000000_add_instant_alerts_to_notifications.php'])->assertSuccessful();

        $categories = NotificationPreference::where('user_id', $this->admin->id)->sole()->categories;
        $this->assertSame(['updates', 'seo', 'security', 'health', 'backups', 'messages'], $categories, 'existing choices kept, new categories added');
    }
}
