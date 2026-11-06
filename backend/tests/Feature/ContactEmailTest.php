<?php

namespace Tests\Feature;

use App\Mail\ContactMessageReceived;
use App\Models\ContactMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;

class ContactEmailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['portfolio.contact_recipient' => 'owner@example.test']);
    }

    public function test_valid_message_is_saved_and_emailed_to_the_configured_owner(): void
    {
        Mail::fake();
        $this->post('/contact', $this->payload())->assertRedirect('/#contact')
            ->assertSessionHasNoErrors();

        $message = ContactMessage::firstOrFail();
        Mail::assertSent(ContactMessageReceived::class, function ($mail) use ($message) {
            $mail->build();

            return $mail->hasTo('owner@example.test')
                && ! $mail->hasTo('visitor@example.test')
                && $mail->hasReplyTo('visitor@example.test', 'Test Visitor')
                && $mail->mailer === 'smtp'
                && $mail->subject === 'Portfolio contact: A collaboration'
                && $mail->contactMessage->id === $message->id;
        });
        Mail::assertSentCount(1);
    }

    public function test_email_content_is_escaped_and_has_the_private_inbox_link(): void
    {
        $message = new ContactMessage([
            ...$this->payload(), 'message' => '<script>alert("unsafe")</script>',
        ]);
        $html = (new ContactMessageReceived($message))->render();
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringContainsString(url('/admin/inbox'), $html);
    }

    public function test_inbox_only_configuration_sends_no_email(): void
    {
        config(['portfolio.contact_recipient' => null]);
        Mail::fake();
        $this->post('/contact', $this->payload())->assertSessionHasNoErrors();
        $this->assertDatabaseCount('contact_messages', 1);
        Mail::assertNothingSent();
    }

    public function test_rejected_and_replayed_submissions_send_no_extra_email(): void
    {
        Mail::fake();
        $payload = $this->payload();
        $this->post('/contact', [...$payload, 'website' => 'spam'])->assertSessionHasErrors();
        Mail::assertNothingSent();
        $this->post('/contact', $payload)->assertSessionHasNoErrors();
        $this->post('/contact', $payload)->assertSessionHasErrors();
        Mail::assertSentCount(1);
        $this->assertDatabaseCount('contact_messages', 1);
    }

    public function test_smtp_failure_preserves_message_and_reports_safe_explicit_feedback(): void
    {
        Mail::shouldReceive('mailer')->once()->with('smtp')->andReturnSelf();
        Mail::shouldReceive('to')->once()->with('owner@example.test')->andReturnSelf();
        Mail::shouldReceive('send')->once()->andThrow(new TransportException('Sensitive transport details'));
        Log::shouldReceive('error')->once()->withArgs(function ($text, $context) {
            return $text === 'Contact email transport failed; message retained in website inbox.'
                && $context === ['contact_message_id' => ContactMessage::firstOrFail()->id];
        });

        $this->post('/contact', $this->payload())->assertRedirect('/#contact')
            ->assertSessionHas('contact_status', 'Your message was saved in the website inbox, but the email notification could not be sent. You do not need to submit it again.');
        $this->assertDatabaseCount('contact_messages', 1);
    }

    private function payload(): array
    {
        return [
            'name' => 'Test Visitor',
            'email' => 'visitor@example.test',
            'subject' => 'A collaboration',
            'message' => "I would like to discuss a project.\nPlease reply.",
            'submission_token' => Crypt::encryptString((string) (time() - 5)),
        ];
    }
}
