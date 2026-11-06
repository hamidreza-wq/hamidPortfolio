<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\RunPlatformMonitor;
use App\Models\AuditLog;
use App\Models\GithubRepository;
use App\Models\NotificationPreference;
use App\Models\PlatformNotice;
use App\Models\PlatformRun;
use App\Models\ReleaseCandidate;
use App\Models\SeoFinding;
use App\Services\Platform\Deployment;
use App\Services\Platform\GithubDiscovery;
use App\Services\Platform\IntegrationFailure;
use App\Services\Platform\Notices;
use App\Services\Platform\Releases;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PlatformController extends Controller
{
    public function updates()
    {
        return view('admin.platform.updates', [
            'version' => app(Releases::class)->installedVersion(),
            'deploymentConfigured' => app(Deployment::class)->configured(),
            'releases' => ReleaseCandidate::latest('released_at')->paginate(10),
            'runs' => PlatformRun::whereIn('kind', ['releases', 'prepare', 'approval', 'dependencies', 'deployment'])->latest('id')->limit(20)->get(),
        ]);
    }

    public function github(Request $request)
    {
        $search = $request->validate(['q' => ['nullable', 'string', 'max:100']])['q'] ?? '';

        return view('admin.platform.github', [
            'repositories' => GithubRepository::with('project')->when($search !== '', fn ($query) => $query->where('full_name', 'like', '%'.addcslashes($search, '\\%_').'%'))
                ->orderByRaw("state = 'pending' DESC")->orderBy('full_name')->paginate(15)->withQueryString(),
            'search' => $search,
            'runs' => PlatformRun::where('kind', 'github')->latest('id')->limit(10)->get(),
        ]);
    }

    public function seo()
    {
        return view('admin.platform.seo', [
            'findings' => SeoFinding::orderByRaw('resolved_at IS NULL DESC')
                ->orderByRaw("FIELD(priority, 'high', 'medium', 'low')")->latest('id')->paginate(20),
            'runs' => PlatformRun::where('kind', 'seo')->latest('id')->limit(10)->get(),
        ]);
    }

    public function notifications(Request $request)
    {
        return view('admin.platform.notifications', [
            'notices' => PlatformNotice::latest('last_seen_at')->paginate(25),
            'preferences' => NotificationPreference::forUser($request->user()->id),
            'runs' => PlatformRun::where('kind', 'digest')->latest('id')->limit(10)->get(),
        ]);
    }

    public function preferences(Request $request)
    {
        $data = $request->validate([
            'categories' => ['sometimes', 'array', 'max:3'],
            'categories.*' => ['required', 'in:updates,github,seo', 'distinct'],
            'digest_frequency' => ['required', 'in:never,daily,weekly'],
        ]);
        NotificationPreference::updateOrCreate(['user_id' => $request->user()->id], [
            'categories' => $data['categories'] ?? [], 'digest_frequency' => $data['digest_frequency'],
        ]);
        $this->audit($request, 'notifications.preferences_changed', (string) $request->user()->id);

        return back()->with('status', 'Notification preferences saved. Email needs a working scheduler and mail transport; log mail does not deliver to an inbox.');
    }

    public function scan(Request $request, string $kind)
    {
        abort_unless(in_array($kind, ['releases', 'github', 'seo'], true), 404);
        $request->validate(['confirm' => ['accepted']]);
        RunPlatformMonitor::dispatch($kind);
        $this->audit($request, 'platform.check_requested', $kind);

        return back()->with('status', config('queue.default') === 'sync'
            ? 'Check finished. Review its history below for the actual result.'
            : 'Check queued. Refresh this page to review progress; a running queue worker is required.');
    }

    public function prepare(Request $request, ReleaseCandidate $releaseCandidate)
    {
        $request->validate(['password' => ['required', 'current_password'], 'confirm' => ['accepted']]);
        $lock = Cache::lock('platform:releases', 180);
        if (! $lock->get()) {
            throw ValidationException::withMessages(['release' => 'Another release check or verification is running.']);
        }
        try {
            if (str_starts_with($releaseCandidate->fresh()->status, 'deployment_')) {
                throw ValidationException::withMessages(['release' => 'Review the existing deployment request in GitHub before starting another release operation.']);
            }
            $run = PlatformRun::create(['kind' => 'prepare', 'status' => 'running']);
            try {
                app(Releases::class)->prepare($releaseCandidate->fresh());
                $run->update(['status' => 'successful', 'message' => $releaseCandidate->tag.' signature, digest and archive inspected. Not staged or deployed.']);
                $this->audit($request, 'release.verified', $releaseCandidate->tag);
                app(Notices::class)->resolve('prepare:'.$releaseCandidate->tag);
            } catch (IntegrationFailure $exception) {
                $run->update(['status' => 'failed', 'message' => $exception->getMessage()]);
                $this->audit($request, 'release.verification_failed', $releaseCandidate->tag);
                app(Notices::class)->put('prepare:'.$releaseCandidate->tag, 'updates', 'high',
                    'Release verification failed', $exception->getMessage(), 'admin.platform.updates');

                return back()->withErrors(['release' => $exception->getMessage()]);
            } finally {
                if ($run->status === 'running') {
                    $run->update(['status' => 'failed', 'message' => 'Internal verification failure. Consult private logs. No activation occurred.']);
                    $releaseCandidate->update(['status' => 'failed']);
                }
            }
        } finally {
            $lock->release();
        }

        return back()->with('status', 'Verified for review only; nothing was deployed. Review every migration. Schema-preserving releases can use Update once protected deployment setup is complete.');
    }

    public function approve(Request $request, ReleaseCandidate $releaseCandidate)
    {
        $request->validate(['password' => ['required', 'current_password'], 'confirm' => ['accepted']]);
        $lock = Cache::lock('platform:releases', 180);
        if (! $lock->get()) {
            throw ValidationException::withMessages(['release' => 'Another release operation is running.']);
        }
        try {
            DB::transaction(function () use ($request, $releaseCandidate): void {
                $candidate = ReleaseCandidate::lockForUpdate()->findOrFail($releaseCandidate->id);
                $key = base64_decode((string) config('platform.release_public_key'), true);
                if ($candidate->status !== 'awaiting_approval' || ! $candidate->verified_at
                    || $key === false || strlen($key) !== 32
                    || ($candidate->inspection['trust_key_sha256'] ?? '') !== hash('sha256', $key)
                    || ! $candidate->artifact_path || ! Storage::disk('local')->exists($candidate->artifact_path)
                    || ! hash_equals($candidate->artifact_sha256, hash_file('sha256', Storage::disk('local')->path($candidate->artifact_path)))) {
                    throw ValidationException::withMessages(['release' => 'Only a verified, unchanged artifact can be approved. Verify it again.']);
                }
                $candidate->update(['status' => 'manual_action_required', 'approved_by' => $request->user()->id, 'approved_at' => now()]);
                PlatformRun::create(['kind' => 'approval', 'status' => 'manual_action_required', 'message' => $candidate->tag.' review recorded. Nothing was activated; use Update only after protected deployment setup is complete.']);
                $this->audit($request, 'release.review_approved', $candidate->tag);
            });
        } finally {
            $lock->release();
        }

        return back()->with('status', 'Review recorded, not deployed. Activation requires configured and tested protected GitHub Actions hosting.');
    }

    public function deploy(Request $request, ReleaseCandidate $releaseCandidate)
    {
        $request->validate(['password' => ['required', 'current_password'], 'confirm' => ['accepted']]);
        if (! app(Deployment::class)->configured()) {
            throw ValidationException::withMessages(['release' => 'Protected update deployment is not configured. Nothing was requested.']);
        }
        $lock = Cache::lock('platform:releases', 180);
        if (! $lock->get()) {
            throw ValidationException::withMessages(['release' => 'Another release operation is running.']);
        }
        try {
            $candidate = $releaseCandidate->fresh();
            $key = base64_decode((string) config('platform.release_public_key'), true);
            if (! in_array($candidate->status, ['awaiting_approval', 'manual_action_required'], true)
                || ! $candidate->verified_at || $key === false || strlen($key) !== 32
                || ($candidate->inspection['trust_key_sha256'] ?? '') !== hash('sha256', $key)
                || ! $candidate->artifact_path || ! Storage::disk('local')->exists($candidate->artifact_path)
                || ! hash_equals($candidate->artifact_sha256, hash_file('sha256', Storage::disk('local')->path($candidate->artifact_path)))) {
                throw ValidationException::withMessages(['release' => 'Only a verified, unchanged release can be updated. Verify it again.']);
            }
            $candidate->update(['status' => 'deployment_requesting', 'approved_by' => $request->user()->id, 'approved_at' => now(),
                'inspection' => [...$candidate->inspection, 'deployment_request' => [
                    'request_id' => (string) Str::uuid(), 'status' => 'requesting', 'requested_at' => now()->toIso8601String(),
                ]]]);
            $run = PlatformRun::create(['kind' => 'deployment', 'status' => 'running', 'message' => $candidate->tag.' deployment request in progress.']);
            try {
                $deployment = app(Deployment::class)->dispatch($candidate);
                $candidate->update(['status' => 'deployment_requested', 'inspection' => [...$candidate->inspection, 'deployment_request' => $deployment]]);
                $run->update(['status' => 'requested', 'message' => $candidate->tag.' requested through protected GitHub Actions. Independent production approval is still required; not yet applied.', 'summary' => $deployment]);
                $this->audit($request, 'release.deployment_requested', $candidate->tag);
            } catch (IntegrationFailure $exception) {
                $candidate->update(['status' => 'deployment_needs_review']);
                $run->update(['status' => 'failed', 'message' => $exception->getMessage()]);
                app(Notices::class)->put('deployment:'.$candidate->tag, 'updates', 'high', 'Update deployment needs attention', $exception->getMessage(), 'admin.platform.updates');

                return back()->withErrors(['release' => $exception->getMessage()]);
            } finally {
                if ($run->status === 'running') {
                    $run->update(['status' => 'failed', 'message' => 'Unexpected dispatch failure. Review GitHub Actions before retrying; no deployment success is assumed.']);
                    $candidate->update(['status' => 'deployment_needs_review']);
                }
            }
        } finally {
            $lock->release();
        }

        return back()->with('status', 'Update requested. Open GitHub Actions for independent production approval, then refresh deployment status.');
    }

    public function deploymentStatus(Request $request, ReleaseCandidate $releaseCandidate)
    {
        $lock = Cache::lock('platform:releases', 180);
        if (! $lock->get()) {
            throw ValidationException::withMessages(['release' => 'Another release operation is running.']);
        }
        try {
            $candidate = $releaseCandidate->fresh();
            try {
                $deployment = app(Deployment::class)->refresh($candidate->inspection['deployment_request'] ?? []);
            } catch (IntegrationFailure $exception) {
                return back()->withErrors(['release' => $exception->getMessage()]);
            }
            $candidate->update(['inspection' => [...$candidate->inspection, 'deployment_request' => $deployment]]);
            if (($deployment['status'] ?? '') === 'completed' && ($deployment['conclusion'] ?? '') === 'success'
                && $candidate->status !== 'applied') {
                $candidate->update(['status' => 'applied']);
                PlatformRun::create(['kind' => 'deployment', 'status' => 'successful',
                    'message' => $candidate->tag.' protected workflow completed successfully after backup, activation and version-specific health checks.', 'summary' => $deployment]);
                app(Notices::class)->resolve('release:'.$candidate->tag);
                app(Notices::class)->resolve('deployment:'.$candidate->tag);
            } elseif (($deployment['status'] ?? '') === 'completed'
                && in_array($deployment['conclusion'] ?? '', ['failure', 'cancelled', 'timed_out', 'startup_failure', 'action_required', 'stale', 'neutral', 'skipped'], true)
                && str_starts_with($candidate->status, 'deployment_')) {
                $candidate->update(['status' => 'failed']);
                $message = $candidate->tag.' workflow did not succeed. Review GitHub Actions and the current site before verifying and requesting another update.';
                PlatformRun::create(['kind' => 'deployment', 'status' => 'failed', 'message' => $message, 'summary' => $deployment]);
                app(Notices::class)->put('deployment:'.$candidate->tag, 'updates', 'high', 'Update workflow failed', $message, 'admin.platform.updates');
            }
            $this->audit($request, 'release.deployment_status_checked', $candidate->tag);
        } finally {
            $lock->release();
        }

        return back()->with('status', 'Deployment status refreshed. A request or queued run is not a completed update.');
    }

    public function review(Request $request, GithubRepository $githubRepository)
    {
        $data = $request->validate(['decision' => ['required', 'in:approve,reject'], 'confirm' => ['accepted']]);
        DB::transaction(function () use ($request, $githubRepository, $data): void {
            $repo = GithubRepository::lockForUpdate()->findOrFail($githubRepository->id);
            if ($data['decision'] === 'approve' && ($repo->metadata['fork'] || $repo->metadata['archived'] || in_array($repo->state, ['unavailable', 'excluded'], true) || ! $repo->project_id)) {
                throw ValidationException::withMessages(['repository' => 'This candidate is unavailable, excluded or has no project. Review it manually in the project editor.']);
            }
            $repo->update([
                'state' => $data['decision'] === 'approve' ? 'approved' : 'rejected',
                'reviewed_at' => now(),
                'publication_approved_at' => $data['decision'] === 'approve' ? now() : null,
            ]);
            $this->audit($request, 'github.'.$data['decision'], (string) $repo->id);
            app(GithubDiscovery::class)->notifyReview();
        });

        return back()->with('status', 'Review recorded. No project content was overwritten or published. Use the project editor to finish and publish an approved draft.');
    }

    public function notice(Request $request, PlatformNotice $platformNotice)
    {
        $data = $request->validate(['action' => ['required', 'in:read,dismiss,snooze']]);
        $platformNotice->update(match ($data['action']) {
            'read' => ['read_at' => now()],
            'dismiss' => ['dismissed_at' => now(), 'read_at' => now()],
            'snooze' => ['snoozed_until' => now()->addDay()],
        });

        return back()->with('status', 'Notification updated.');
    }

    private function audit(Request $request, string $action, string $subject): void
    {
        AuditLog::create([
            'user_id' => $request->user()->id, 'action' => $action,
            'subject_type' => 'platform', 'ip_address' => $request->ip(), 'details' => ['subject' => $subject],
        ]);
    }
}
