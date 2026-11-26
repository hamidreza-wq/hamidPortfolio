<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\ContactMessage;
use App\Models\PageView;
use App\Models\PortfolioSetting;
use App\Models\Testimonial;
use App\Services\Backups\BackupManager;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Finds, exports and erases the personal data this site holds about one email
 * address, and applies the owner's retention settings.
 *
 * Data outside this database cannot be reached from here: copies in backups
 * (until they rotate out), notification emails in the owner's mailbox, and
 * anything Google Analytics collected with consent.
 */
class PrivacyData
{
    /** A one-way reference for the audit log, so erasure records don't keep the address. */
    public static function reference(string $email): string
    {
        return substr(hash_hmac('sha256', Str::lower(trim($email)), (string) config('app.key')), 0, 16);
    }

    /** @return array{messages: Collection, testimonials: Collection} */
    public function find(string $email): array
    {
        $email = Str::lower(trim($email));

        return [
            'messages' => ContactMessage::whereRaw('lower(email) = ?', [$email])->latest()->get(),
            'testimonials' => Testimonial::whereRaw('lower(email) = ?', [$email])->latest()->get(),
        ];
    }

    public function export(string $email, ?int $userId): array
    {
        $found = $this->find($email);
        AuditLog::create(['user_id' => $userId, 'action' => 'privacy.exported',
            'details' => ['name' => 'Request '.self::reference($email), 'messages' => $found['messages']->count(), 'testimonials' => $found['testimonials']->count()]]);

        return [
            'about' => 'Personal data held by '.(PortfolioSetting::find(1)?->site_title ?: config('app.name')).' for this email address.',
            'email' => Str::lower(trim($email)),
            'exported_at' => now()->utc()->toIso8601String(),
            'contact_messages' => $found['messages']->map(fn (ContactMessage $message) => [
                'received_at' => $message->created_at?->toIso8601String(),
                'name' => $message->name, 'email' => $message->email, 'subject' => $message->subject,
                'message' => $message->message, 'status' => $message->status,
                'owner_note' => $message->internal_note,
                'ip_address_hash' => $message->ip_hash,
            ])->values()->all(),
            'testimonials' => $found['testimonials']->map(fn (Testimonial $testimonial) => [
                'submitted_at' => $testimonial->created_at?->toIso8601String(),
                'name' => $testimonial->name, 'email' => $testimonial->email, 'role' => $testimonial->role,
                'company' => $testimonial->company, 'testimonial' => $testimonial->quote, 'rating' => $testimonial->rating,
                'status' => $testimonial->status, 'consent_given_at' => $testimonial->consent_at?->toIso8601String(),
                'published' => $testimonial->status === 'approved',
                'owner_note' => $testimonial->internal_note,
                'ip_address_hash' => $testimonial->ip_hash,
            ])->values()->all(),
            'not_included' => [
                'IP address hashes are one-way: the original addresses are not stored.',
                'Notification emails the site sent to the owner live in the owner\'s mailbox, not here.',
                'Server backups may hold earlier copies until they are replaced by newer backups.',
            ],
        ];
    }

    /** @return array{messages: int, testimonials: int} */
    public function erase(string $email, ?int $userId): array
    {
        return DB::transaction(function () use ($email, $userId) {
            $found = $this->find($email);
            $counts = ['messages' => $found['messages']->count(), 'testimonials' => $found['testimonials']->count()];
            ContactMessage::whereIn('id', $found['messages']->pluck('id'))->delete();
            Testimonial::whereIn('id', $found['testimonials']->pluck('id'))->delete();
            AuditLog::create(['user_id' => $userId, 'action' => 'privacy.erased', 'details' => ['name' => 'Request '.self::reference($email)] + $counts]);

            return $counts;
        });
    }

    /** Applies the retention settings. Returns what was removed. */
    public function prune(): array
    {
        $settings = PortfolioSetting::find(1);
        $removed = ['messages' => 0, 'activity_ips' => 0];
        if ($days = $settings?->contact_retention_days) {
            $removed['messages'] = ContactMessage::where('created_at', '<', now()->subDays($days))->delete();
        }
        if ($days = $settings?->activity_ip_retention_days) {
            $removed['activity_ips'] = AuditLog::whereNotNull('ip_address')->where('created_at', '<', now()->subDays($days))->update(['ip_address' => null]);
        }
        if (array_sum($removed)) {
            AuditLog::create(['action' => 'privacy.retention_applied', 'details' => $removed]);
        }

        return $removed;
    }

    /** What personal data the site holds, how much, and for how long. */
    public function inventory(): array
    {
        $settings = PortfolioSetting::findOrFail(1);
        $days = fn (?int $value, string $keep = 'Kept until you delete them') => $value ? "Deleted after {$value} days" : $keep;
        $oldest = fn ($model) => $model::min('created_at');

        return [
            ['Contact messages', 'Name, email, message, your notes, and a one-way hash of the sender\'s IP address', ContactMessage::count(), $days($settings->contact_retention_days), $oldest(ContactMessage::class)],
            ['Testimonials', 'Name, email (private), role, company, words, rating, consent time, IP hash', Testimonial::count(), 'Approved: until removed. Rejected: deleted after 90 days', $oldest(Testimonial::class)],
            ['Activity log', 'Admin actions, sign-ins and failed sign-ins, with IP addresses', AuditLog::whereNotNull('ip_address')->count().' with an IP', $settings->activity_ip_retention_days ? "IP addresses cleared after {$settings->activity_ip_retention_days} days; entries kept" : 'IP addresses kept', $oldest(AuditLog::class)],
            ['Admin sessions', 'IP address and browser of signed-in administrators', config('session.driver') === 'database' ? DB::table(config('session.table', 'sessions'))->count() : '—', 'Removed when the session expires ('.config('session.lifetime').' minutes idle)', null],
            ['Pageview counts', 'Page path and referring website only; no IP, cookie or browser details', PageView::count(), 'Deleted after '.config('portfolio.analytics_retention_days').' days', PageView::min('viewed_at')],
            ['Backups', 'Copies of all of the above', count(app(BackupManager::class)->list()), 'Newest '.config('portfolio.backups.keep').' kept, older ones removed', null],
        ];
    }
}
