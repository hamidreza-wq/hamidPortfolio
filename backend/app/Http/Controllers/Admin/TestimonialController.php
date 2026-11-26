<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\PortfolioSection;
use App\Models\PortfolioSetting;
use App\Models\Testimonial;
use App\Services\Alerts;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TestimonialController extends Controller
{
    public function index(Request $request)
    {
        $status = array_key_exists($request->query('status'), Testimonial::STATUSES) ? $request->query('status') : 'pending';

        return view('admin.testimonials', [
            'status' => $status,
            'counts' => Testimonial::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
            'testimonials' => Testimonial::with('reviewer')->where('status', $status)
                ->orderByDesc('is_featured')->orderBy('sort_order')->latest('id')->paginate(20)->withQueryString(),
            'submissionsOpen' => (bool) PortfolioSetting::findOrFail(1)->testimonial_submissions_open,
            'hasSection' => PortfolioSection::where('type', 'testimonials')->where('is_visible', true)->exists(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $publish = $request->boolean('publish');
        $testimonial = Testimonial::create($data + [
            'status' => $publish ? 'approved' : 'pending',
            'reviewed_by' => $publish ? $request->user()->id : null,
            'reviewed_at' => $publish ? now() : null,
        ]);
        $this->audit($request, 'testimonial.created', $testimonial);

        return redirect()->route('admin.testimonials.index', ['status' => $testimonial->status])
            ->with('status', $publish ? 'Testimonial added and published.' : 'Testimonial added to the review queue.');
    }

    public function update(Request $request, Testimonial $testimonial)
    {
        $data = $this->validated($request, $testimonial);
        $testimonial->update($data);
        $this->audit($request, 'testimonial.updated', $testimonial);

        return back()->with('status', 'Testimonial saved.');
    }

    /** Approve, reject or move back to review. Only approved testimonials are ever shown publicly. */
    public function moderate(Request $request, Testimonial $testimonial)
    {
        $validated = $request->validate(['status' => ['required', Rule::in(array_keys(Testimonial::STATUSES))]]);
        $testimonial->forceFill([
            'status' => $validated['status'],
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'is_featured' => $validated['status'] === 'approved' && $testimonial->is_featured,
        ])->save();
        $this->audit($request, 'testimonial.'.$validated['status'], $testimonial);
        app(Alerts::class)->testimonialsChanged();

        return back()->with('status', match ($validated['status']) {
            'approved' => "{$testimonial->name}'s testimonial is now published.",
            'rejected' => "{$testimonial->name}'s testimonial was rejected and is not shown.",
            default => "{$testimonial->name}'s testimonial is back in the review queue and hidden.",
        });
    }

    public function destroy(Request $request, Testimonial $testimonial)
    {
        $this->audit($request, 'testimonial.deleted', $testimonial);
        $testimonial->delete();
        app(Alerts::class)->testimonialsChanged();

        return back()->with('status', 'Testimonial deleted, including any private email address it held.');
    }

    public function submissions(Request $request)
    {
        $open = $request->boolean('open');
        PortfolioSetting::findOrFail(1)->forceFill(['testimonial_submissions_open' => $open])->save();
        AuditLog::create(['user_id' => $request->user()->id, 'action' => 'testimonial.submissions_'.($open ? 'opened' : 'closed')]);

        return back()->with('status', $open
            ? 'The testimonial form is open. Share the link with clients; every submission waits for your review.'
            : 'The testimonial form is closed. Visitors see a short notice instead.');
    }

    private function validated(Request $request, ?Testimonial $existing = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'role' => ['nullable', 'string', 'max:120'],
            'company' => ['nullable', 'string', 'max:120'],
            'quote' => ['required', 'string', 'max:1500'],
            'rating' => ['nullable', 'integer', 'between:1,5'],
            'source' => ['required', Rule::in(array_keys(Testimonial::SOURCES))],
            'source_url' => ['nullable', 'url:https', 'max:500'],
            'given_on' => ['nullable', 'date', 'before_or_equal:today'],
            'sort_order' => ['nullable', 'integer', 'between:-1000,1000'],
            'is_featured' => ['nullable', 'boolean'],
            'internal_note' => ['nullable', 'string', 'max:2000'],
        ]);
        $data['is_featured'] = $request->boolean('is_featured') && ($existing?->status ?? ($request->boolean('publish') ? 'approved' : 'pending')) === 'approved';
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
        // Website submissions keep their origin; it is not editable into something else.
        if ($existing?->source === 'website') {
            $data['source'] = 'website';
        } elseif ($data['source'] === 'website') {
            $data['source'] = 'manual';
        }

        return $data;
    }

    private function audit(Request $request, string $action, Testimonial $testimonial): void
    {
        AuditLog::create([
            'user_id' => $request->user()->id, 'action' => $action,
            'subject_type' => Testimonial::class, 'subject_id' => $testimonial->id,
            'details' => ['name' => $testimonial->name],
        ]);
    }
}
