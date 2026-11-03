<?php

namespace App\Http\Controllers;

use App\Models\ContactMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PublicContactController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'subject' => ['required', 'string', 'max:150'],
            'message' => ['required', 'string', 'max:5000'],
        ]);
        if ($validator->fails()) {
            return redirect('/#contact')
                ->withErrors($validator)
                ->withInput()
                ->with('contact_status', $validator->errors()->first());
        }
        $validated = $validator->validated();

        ContactMessage::create([
            ...$validated,
            'status' => 'unread',
            'ip_hash' => hash_hmac('sha256', $request->ip() ?? 'unknown', (string) config('app.key')),
        ]);

        return redirect('/#contact')->with('contact_status', 'Thanks for reaching out. Your message has been received.');
    }
}
