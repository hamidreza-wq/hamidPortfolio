<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\PortfolioSetting;
use App\Services\PrivacyData;
use Illuminate\Http\Request;

class PrivacyController extends Controller
{
    public const RETENTION_CHOICES = [null => 'Keep until I delete them', 90 => '90 days', 180 => '6 months', 365 => '1 year', 730 => '2 years'];

    public function index(PrivacyData $privacy)
    {
        return $this->page($privacy, null);
    }

    /** POST, so the email address never appears in URLs, server logs or browser history. */
    public function lookup(Request $request, PrivacyData $privacy)
    {
        return $this->page($privacy, $request->validate(['email' => ['required', 'email', 'max:255']])['email']);
    }

    private function page(PrivacyData $privacy, ?string $email)
    {
        return view('admin.privacy', [
            'settings' => PortfolioSetting::findOrFail(1),
            'inventory' => $privacy->inventory(),
            'email' => $email,
            'found' => $email ? $privacy->find($email) : null,
            'choices' => self::RETENTION_CHOICES,
            'requests' => AuditLog::with('user')->whereIn('action', ['privacy.exported', 'privacy.erased', 'privacy.retention_changed', 'privacy.retention_applied'])
                ->latest()->latest('id')->limit(10)->get(),
            'measurementId' => config('services.google_analytics.measurement_id'),
        ]);
    }

    public function retention(Request $request)
    {
        $allowed = array_filter(array_keys(self::RETENTION_CHOICES));
        $data = $request->validate([
            'contact_retention_days' => ['nullable', 'integer', 'in:'.implode(',', $allowed)],
            'activity_ip_retention_days' => ['nullable', 'integer', 'in:'.implode(',', $allowed)],
        ]);
        $settings = PortfolioSetting::findOrFail(1);
        $settings->forceFill([
            'contact_retention_days' => $data['contact_retention_days'] ?? null,
            'activity_ip_retention_days' => $data['activity_ip_retention_days'] ?? null,
        ])->save();
        AuditLog::create(['user_id' => $request->user()->id, 'action' => 'privacy.retention_changed', 'details' => array_map(fn ($v) => $v ?? 'keep', $data)]);

        return back()->with('status', 'Retention saved. It runs once a day; the privacy notice now states these periods.');
    }

    public function export(Request $request, PrivacyData $privacy)
    {
        $email = $request->validate(['email' => ['required', 'email', 'max:255']])['email'];

        return response()->json($privacy->export($email, $request->user()->id), 200, [
            'Content-Disposition' => 'attachment; filename="personal-data-'.PrivacyData::reference($email).'.json"',
            'Cache-Control' => 'private, no-store',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    public function erase(Request $request, PrivacyData $privacy)
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'current_password'],
            'confirm' => ['accepted'],
        ]);
        $counts = $privacy->erase($data['email'], $request->user()->id);

        return redirect()->route('admin.privacy')->with('status',
            "Erased {$counts['messages']} message(s) and {$counts['testimonials']} testimonial(s) for that address. Copies in older backups disappear as they are replaced, and notification emails in your mailbox must be deleted there.");
    }
}
