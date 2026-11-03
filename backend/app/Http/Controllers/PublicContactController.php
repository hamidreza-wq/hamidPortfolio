<?php

namespace App\Http\Controllers;

use App\Models\ContactMessage;
use App\Services\ContactSubmissionGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;

class PublicContactController extends Controller
{
    public function store(Request $request, ContactSubmissionGuard $guard): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'subject' => ['required', 'string', 'max:150'],
            'message' => ['required', 'string', 'max:5000'],
            'website' => ['nullable', 'string', 'max:0'],
            'submission_token' => ['required', 'string', 'max:2048'],
        ]);
        if ($validator->fails()) {
            return redirect('/#contact')
                ->withErrors($validator)
                ->withInput()
                ->with('contact_status', $validator->errors()->first());
        }
        $validated = $validator->validated();
        if (! $guard->isValid($validated['submission_token'])) {
            return redirect('/#contact')->withErrors(['message' => 'Please reload the contact form, wait a moment, and try again.'])->withInput($request->only('name', 'email', 'subject', 'message'));
        }
        $emailKey = 'contact-email:'.hash_hmac('sha256', strtolower($validated['email']), (string) config('app.key'));
        if (RateLimiter::tooManyAttempts($emailKey, 3)) {
            return redirect('/#contact')->withErrors(['message' => 'Too many messages. Please try again in ten minutes.'])->withInput($request->only('name', 'email', 'subject', 'message'));
        }
        $tokenKey = 'contact-token:'.hash('sha256', $validated['submission_token']);
        if (! Cache::add($tokenKey, true, now()->addSeconds(config('portfolio.contact_max_seconds')))) {
            return redirect('/#contact')->withErrors(['message' => 'This form has already been submitted. Reload it before sending another message.']);
        }
        RateLimiter::hit($emailKey, 600);

        ContactMessage::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'subject' => $validated['subject'],
            'message' => $validated['message'],
            'status' => 'unread',
            'ip_hash' => hash_hmac('sha256', $request->ip() ?? 'unknown', (string) config('app.key')),
        ]);

        return redirect('/#contact')->with('contact_status', 'Thanks for reaching out. Your message has been received.');
    }
}
