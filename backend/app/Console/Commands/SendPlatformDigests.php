<?php

namespace App\Console\Commands;

use App\Mail\PlatformDigest;
use App\Models\NotificationPreference;
use App\Models\PlatformNotice;
use App\Models\PlatformRun;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class SendPlatformDigests extends Command
{
    protected $signature = 'platform:digest';

    protected $description = 'Send opted-in daily/weekly administrator digests using configured mail transport';

    public function handle(): int
    {
        $lock = Cache::lock('platform:digests', 300);
        if (! $lock->get()) {
            $this->error('Digest delivery is already running.');

            return self::FAILURE;
        }
        try {
            foreach (NotificationPreference::whereIn('digest_frequency', ['daily', 'weekly'])->get() as $preference) {
                $admin = User::whereKey($preference->user_id)->where('is_admin', true)->first();
                if (! $admin || ($preference->last_sent_at && $preference->last_sent_at->greaterThanOrEqualTo(
                    $preference->digest_frequency === 'daily' ? now()->subDay() : now()->subWeek()
                ))) {
                    continue;
                }
                $items = PlatformNotice::visible()->whereNull('read_at')->whereIn('category', $preference->categories)
                    ->when($preference->last_sent_at, fn ($query) => $query->where('last_seen_at', '>', $preference->last_sent_at))
                    ->orderByRaw("FIELD(priority, 'high', 'medium', 'low')")->limit(50)
                    ->get(['title', 'priority', 'message'])->toArray();
                if ($items === []) {
                    continue;
                }
                try {
                    Mail::to($admin->email)->send(new PlatformDigest($items));
                    $preference->update(['last_sent_at' => now()]);
                    PlatformRun::create(['kind' => 'digest', 'status' => 'submitted', 'message' => 'Digest submitted to the configured '.$this->laravel['config']->get('mail.default').' transport. This is not a guarantee of inbox delivery.']);
                } catch (TransportExceptionInterface) {
                    PlatformRun::create(['kind' => 'digest', 'status' => 'failed', 'message' => 'Mail transport failed. Check server mail configuration. No credentials were recorded in this report.']);
                    Log::warning('Platform digest transport failed', ['user_id' => $admin->id]);
                    $this->error('Digest transport failed.');

                    return self::FAILURE;
                }
            }
        } finally {
            $lock->release();
        }
        $this->info('Digest processing complete. Disabled / empty subscriptions send nothing.');

        return self::SUCCESS;
    }
}
