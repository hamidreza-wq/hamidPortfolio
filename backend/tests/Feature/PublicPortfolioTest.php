<?php

namespace Tests\Feature;

use App\Models\ContactMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicPortfolioTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_laravel_serves_the_existing_portfolio_and_its_allowlisted_assets(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('id="project-grid"', false)
            ->assertSee('window.portfolioPayload', false)
            ->assertSee('OnePrayer');

        $this->get('/styles.css')->assertOk()->assertHeader('content-type', 'text/css; charset=UTF-8');
        $this->get('/favicon.svg')->assertOk();
        $this->get('/.env')->assertNotFound();
    }

    public function test_public_contact_form_submissions_are_saved_to_the_inbox(): void
    {
        $this->post('/contact', [
            'name' => 'Test Visitor',
            'email' => 'visitor@example.test',
            'subject' => 'A collaboration',
            'message' => 'I would like to discuss a project.',
        ])->assertRedirect('/#contact')
            ->assertSessionHas('contact_status');

        $this->assertDatabaseHas('contact_messages', [
            'name' => 'Test Visitor',
            'email' => 'visitor@example.test',
            'status' => 'unread',
        ]);
        $this->assertNotNull(ContactMessage::firstOrFail()->ip_hash);
    }

    public function test_administrator_can_update_a_contact_message_from_the_inbox(): void
    {
        $message = ContactMessage::create([
            'name' => 'Test Visitor',
            'email' => 'visitor@example.test',
            'subject' => 'A collaboration',
            'message' => 'A private note from a visitor.',
        ]);
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->patch('/admin/inbox/'.$message->id, [
            'status' => 'read',
            'internal_note' => 'Follow up next week.',
        ])->assertRedirect();

        $this->assertDatabaseHas('contact_messages', [
            'id' => $message->id,
            'status' => 'read',
            'internal_note' => 'Follow up next week.',
        ]);
    }

    public function test_invalid_contact_submission_feedback_is_visible_on_the_portfolio(): void
    {
        $this->from('/#contact')->post('/contact', [
            'name' => '',
            'email' => 'not-an-email',
            'subject' => '',
            'message' => '',
        ])->assertRedirect('/#contact')
            ->assertSessionHasErrors(['name', 'email', 'subject', 'message']);

        $this->get('/')->assertOk()->assertSee('The name field is required.', false);
    }

    public function test_maintenance_mode_hides_only_the_public_portfolio(): void
    {
        \App\Models\PortfolioSetting::whereKey(1)->update(['maintenance_mode' => true]);

        $this->get('/')->assertStatus(503)->assertSee('A small update is underway.');
        $this->get('/admin/login')->assertOk();
    }
}
