<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\MediaAsset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class MediaController extends Controller
{
    public function index()
    {
        return view('admin.media', [
            'assets' => MediaAsset::latest()->paginate(24),
            'storageBytes' => MediaAsset::sum('size_bytes'),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'file' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'label' => ['required', 'string', 'max:120'],
            'alt_text' => ['nullable', 'string', 'max:255'],
        ]);

        $file = $validated['file'];
        $dimensions = getimagesize($file->getRealPath());
        abort_if($dimensions === false, 422, 'The uploaded file is not a readable image.');
        $path = $file->storePublicly('portfolio-media', 'public');
        abort_if($path === false, 500, 'The image could not be saved to public storage.');

        $asset = MediaAsset::create([
            'label' => $validated['label'],
            'original_name' => $file->getClientOriginalName(),
            'disk' => 'public',
            'path' => $path,
            'mime_type' => $file->getMimeType(),
            'size_bytes' => $file->getSize(),
            'width' => $dimensions[0],
            'height' => $dimensions[1],
            'alt_text' => $validated['alt_text'] ?? null,
            'uploaded_by' => $request->user()->id,
        ]);

        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => 'media.uploaded',
            'subject_type' => 'media_asset',
            'subject_id' => $asset->id,
            'ip_address' => $request->ip(),
            'details' => ['filename' => $asset->original_name],
        ]);

        return redirect()->route('admin.media.index')->with('status', 'Image uploaded to the media library.');
    }

    public function destroy(Request $request, MediaAsset $mediaAsset)
    {
        DB::transaction(function () use ($request, $mediaAsset): void {
            AuditLog::create([
                'user_id' => $request->user()->id,
                'action' => 'media.deleted',
                'subject_type' => 'media_asset',
                'subject_id' => $mediaAsset->id,
                'ip_address' => $request->ip(),
                'details' => ['filename' => $mediaAsset->original_name],
            ]);
            Storage::disk($mediaAsset->disk)->delete($mediaAsset->path);
            $mediaAsset->delete();
        });

        return redirect()->route('admin.media.index')->with('status', 'Media file removed.');
    }
}
