<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\ContactMessage;
use App\Models\Testimonial;
use App\Services\Platform\Notices;

/**
 * Turns site events into notification-centre notices. Messages hold counts
 * only, never names or message text, because digests and alert emails must
 * not carry private inbox content.
 */
class Alerts
{
    public const FAILED_SIGN_IN_THRESHOLD = 10;

    public function __construct(private Notices $notices) {}

    public function inboxChanged(): void
    {
        $unread = ContactMessage::where('status', 'unread')->count();
        $unread
            ? $this->notices->put('messages:inbox', 'messages', 'low', 'New contact messages',
                "{$unread} unread message(s) in the contact inbox.", 'admin.inbox.index')
            : $this->notices->resolve('messages:inbox');
    }

    public function testimonialsChanged(): void
    {
        $pending = Testimonial::where('status', 'pending')->count();
        $pending
            ? $this->notices->put('messages:testimonials', 'messages', 'medium', 'Testimonials waiting for review',
                "{$pending} testimonial(s) are waiting for your review. Nothing is published until you approve it.", 'admin.testimonials.index')
            : $this->notices->resolve('messages:testimonials');
    }

    public function failedSignIn(): void
    {
        $lastHour = AuditLog::whereIn('action', ['authentication.failed', 'authentication.two_factor_failed'])
            ->where('created_at', '>=', now()->subHour())->count();
        if ($lastHour >= self::FAILED_SIGN_IN_THRESHOLD) {
            $this->notices->put('security:failed-sign-ins', 'security', 'high', 'Many failed sign-in attempts',
                'At least '.self::FAILED_SIGN_IN_THRESHOLD.' failed sign-in attempts in the last hour. They are throttled, but check the sources on the Security page and make sure two-factor sign-in is on.',
                'admin.security');
        }
    }

    public function twoFactorDisabled(string $how): void
    {
        $this->notices->put('security:two-factor-off', 'security', 'high', 'Two-factor sign-in was turned off',
            "Two-factor sign-in was turned off {$how}. Only a password protects the dashboard now. If you didn't do this, change your password and sign out other sessions.",
            'admin.security');
    }

    public function twoFactorEnabled(): void
    {
        $this->notices->resolve('security:two-factor-off');
    }

    public function recoveryCodeUsed(int $remaining): void
    {
        $this->notices->put('security:recovery-code', 'security', 'high', 'A recovery code was used to sign in',
            "Someone signed in with a two-factor recovery code. {$remaining} code(s) remain. If this wasn't you, change your password and sign out other sessions. Otherwise, generate new codes when you're running low.",
            'admin.security');
    }
}
