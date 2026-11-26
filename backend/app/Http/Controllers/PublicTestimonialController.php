<?php

namespace App\Http\Controllers;

use App\Models\PortfolioSetting;
use App\Models\Testimonial;
use App\Services\ContactSubmissionGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;

/**
 * A shareable form for clients. Submissions are never published automatically:
 * each one waits in the admin review queue.
 */
class PublicTestimonialController extends Controller
{
    public function create(ContactSubmissionGuard $guard)
    {
        $settings = PortfolioSetting::findOrFail(1);

        return response()->view('portfolio.testimonial-form', [
            'open' => (bool) $settings->testimonial_submissions_open,
            'ownerName' => $settings->name ?: 'the site owner',
            'token' => $guard->issueToken(),
        ])->header('Cache-Control', 'private, no-store')->header('X-Robots-Tag', 'noindex');
    }

    public function store(Request $request, ContactSubmissionGuard $guard)
    {
        if (! PortfolioSetting::findOrFail(1)->testimonial_submissions_open) {
            return redirect()->route('testimonials.create');
        }
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'role' => ['nullable', 'string', 'max:120'],
            'company' => ['nullable', 'string', 'max:120'],
            'quote' => ['required', 'string', 'min:20', 'max:1500'],
            'rating' => ['nullable', 'integer', 'between:1,5'],
            'consent' => ['accepted'],
            'website' => ['nullable', 'string', 'max:0'],
            'submission_token' => ['required', 'string', 'max:2048'],
        ], [
            'consent.accepted' => 'Please confirm your words may be published with your name.',
            'quote.min' => 'Please write at least a sentence or two.',
        ]);
        $keep = $request->only('name', 'email', 'role', 'company', 'quote', 'rating');
        if ($validator->fails()) {
            return redirect()->route('testimonials.create')->withErrors($validator)->withInput($keep);
        }
        $data = $validator->validated();

        if (! $guard->isValid($data['submission_token'])) {
            return redirect()->route('testimonials.create')
                ->withErrors(['quote' => 'Please reload the form, wait a moment, and try again.'])->withInput($keep);
        }
        $emailKey = 'testimonial-email:'.hash_hmac('sha256', strtolower($data['email']), (string) config('app.key'));
        if (RateLimiter::tooManyAttempts($emailKey, 2)) {
            return redirect()->route('testimonials.create')
                ->withErrors(['quote' => 'A testimonial from this email address is already waiting for review. Thank you!']);
        }
        if (! Cache::add('testimonial-token:'.hash('sha256', $data['submission_token']), true, now()->addSeconds(config('portfolio.contact_max_seconds')))) {
            return redirect()->route('testimonials.create')
                ->withErrors(['quote' => 'This form was already sent. Reload it to send another.']);
        }
        RateLimiter::hit($emailKey, 86400);

        Testimonial::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'role' => $data['role'] ?? null,
            'company' => $data['company'] ?? null,
            'quote' => $data['quote'],
            'rating' => $data['rating'] ?? null,
            'source' => 'website',
            'status' => 'pending',
            'consent_at' => now(),
            'given_on' => now()->toDateString(),
            'ip_hash' => hash_hmac('sha256', $request->ip() ?? 'unknown', (string) config('app.key')),
        ]);

        return redirect()->route('testimonials.create')->with('testimonial_sent', true);
    }
}
