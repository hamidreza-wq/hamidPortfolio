<?php

namespace App\Console\Commands;

use App\Mail\PlatformAlert;
use App\Models\NotificationPreference;
use App\Models\PlatformNotice;
use App\Models\PlatformRun;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

/**
 * Emails new high-priority notices straight away to administrators who opted
 * in. Runs from the scheduler every minute, so it needs no queue worker.
 * Alerts are batched: at most one email per administrator every 15 minutes.
 */
class SendPlatformAlerts extends Command
{
    protected $signature = 'platform:alerts';

    protected $description = 'Email new high-priority notices to administrators who turned on immediate alerts';

    public const BATCH_MINUTES = 15;

    public function handle(): int
    {
        $lock = Cache::lock('platform:alerts', 120);
        if (! $lock->get()) {
            return self::SUCCESS;
        }
        try {
            $pending = PlatformNotice::visible()->where('priority', 'high')->whereNull('alerted_at')
                ->where('last_seen_at', '>=', now()->subDay())->orderBy('id')->get();
            if ($pending->isEmpty()) {
                return self::SUCCESS;
            }
            $sentAny = false;
            $waiting = false;
            foreach (NotificationPreference::where('instant_alerts', true)->get() as $preference) {
                $admin = User::whereKey($preference->user_id)->where('is_admin', true)->first();
                $items = $pending->whereIn('category', $preference->categories ?? []);
                if (! $admin || $items->isEmpty()) {
                    continue;
                }
                $throttle = 'platform:alerts:sent:'.$admin->id;
                if (Cache::has($throttle)) {
                    $waiting = true;

                    continue;
                }
                try {
                    Mail::to($admin->email)->send(new PlatformAlert($items->map->only(['title', 'message', 'category'])->values()->all()));
                    Cache::put($throttle, true, now()->addMinutes(self::BATCH_MINUTES));
                    $sentAny = true;
                } catch (TransportExceptionInterface) {
                    PlatformRun::create(['kind' => 'digest', 'status' => 'failed', 'message' => 'Immediate alert email failed. Check the server mail configuration. No credentials were recorded.']);
                    Log::warning('Platform alert transport failed', ['user_id' => $admin->id]);

                    return self::FAILURE;
                }
            }
            // Leave notices pending while any opted-in admin is inside their batching window.
            if (! $waiting) {
                PlatformNotice::whereIn('id', $pending->pluck('id'))->update(['alerted_at' => now()]);
            }
            if ($sentAny) {
                PlatformRun::create(['kind' => 'digest', 'status' => 'submitted', 'message' => 'Immediate alert submitted to the configured '.config('mail.default').' transport. This is not a guarantee of inbox delivery.']);
            }
        } finally {
            $lock->release();
        }

        return self::SUCCESS;
    }
}
