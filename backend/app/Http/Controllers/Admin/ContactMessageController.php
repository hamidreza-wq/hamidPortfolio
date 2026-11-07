<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\ContactMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ContactMessageController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:unread,read,archived'],
        ]);
        $messages = ContactMessage::query()
            ->when($request->filled('q'), fn ($query) => $query->where(function ($subQuery) use ($request) {
                $term = '%'.$request->string('q')->toString().'%';
                $subQuery->where('name', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhere('subject', 'like', $term);
            }))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('admin.inbox', compact('messages'));
    }

    public function update(Request $request, ContactMessage $contactMessage)
    {
        $validated = $request->validate([
            'status' => ['required', 'in:unread,read,archived'],
            'internal_note' => ['nullable', 'string', 'max:3000'],
        ]);

        DB::transaction(function () use ($request, $contactMessage, $validated): void {
            $contactMessage->update([
                ...$validated,
                'archived_at' => $validated['status'] === 'archived' ? now() : null,
            ]);
            AuditLog::create([
                'user_id' => $request->user()->id,
                'action' => 'contact_message.updated',
                'subject_type' => 'contact_message',
                'subject_id' => $contactMessage->id,
                'ip_address' => $request->ip(),
            ]);
        });

        return back()->with('status', 'Message updated.');
    }

    public function destroy(Request $request, ContactMessage $contactMessage)
    {
        DB::transaction(function () use ($request, $contactMessage): void {
            AuditLog::create([
                'user_id' => $request->user()->id,
                'action' => 'contact_message.deleted',
                'subject_type' => 'contact_message',
                'subject_id' => $contactMessage->id,
                'ip_address' => $request->ip(),
            ]);
            $contactMessage->delete();
        });

        return back()->with('status', 'Message deleted.');
    }
}
