<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\MediaAsset;
use App\Services\AssetStorage;
use App\Services\AssetUpload;
use App\Services\AssetUsage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class MediaController extends Controller
{
    public function index(Request $request, AssetUsage $usage)
    {
        $search = $request->validate(['q' => ['nullable', 'string', 'max:120']])['q'] ?? '';
        $assets = MediaAsset::when($search !== '', fn ($query) => $query->where(function ($query) use ($search) {
            $query->where('label', 'like', '%'.$search.'%')->orWhere('original_name', 'like', '%'.$search.'%')
                ->orWhere('alt_text', 'like', '%'.$search.'%');
        }))->latest()->paginate(24)->withQueryString();

        return view('admin.media', ['assets' => $assets, 'search' => $search,
            'storageBytes' => MediaAsset::sum('size_bytes'),
            'usage' => $assets->mapWithKeys(fn ($asset) => [$asset->id => $usage->media($asset)])]);
    }

    public function store(Request $request, AssetUpload $upload, AssetStorage $storage)
    {
        $data = $request->validate($this->rules(true));
        $image = $upload->image($data['file']);
        $path = $storage->put('portfolio-media', $image['extension'], $image['bytes']);
        $storage->create($path, function () use ($data, $image, $path, $request) {
            $asset = MediaAsset::create($this->attributes($data, $image, $path) + ['uploaded_by' => $request->user()->id]);
            $this->audit($request, $asset, 'uploaded');
        });

        return redirect()->route('admin.media.index')->with('status', 'Image uploaded to the media library.');
    }

    public function update(Request $request, MediaAsset $mediaAsset, AssetUpload $upload, AssetStorage $storage, AssetUsage $usage)
    {
        $data = $request->validate($this->rules(false));
        $image = isset($data['file']) ? $upload->image($data['file']) : null;
        $path = $image ? $storage->put('portfolio-media', $image['extension'], $image['bytes']) : null;
        $operation = function () use ($request, $mediaAsset, $data, $image, $path, $usage) {
            $asset = MediaAsset::lockForUpdate()->findOrFail($mediaAsset->id);
            if ($asset->path !== $mediaAsset->path || $asset->disk !== $mediaAsset->disk) {
                throw ValidationException::withMessages(['media' => 'This image changed during editing. Reload the library and try again.']);
            }
            if ($image && $usage->mediaLegacyReferences($asset)) {
                throw ValidationException::withMessages(['media' => 'Legacy storage URLs still reference this image. Switch them to its stable library URL or library selector before replacing it.']);
            }
            $asset->update($image ? $this->attributes($data, $image, $path) : [
                'label' => $data['label'], 'alt_text' => $data['alt_text'] ?? null,
            ]);
            $this->audit($request, $asset, $image ? 'replaced' : 'updated');
        };
        if ($path) {
            $storage->remove($mediaAsset->disk, $mediaAsset->path, $operation, $path,
                fn () => $this->lockCurrent($mediaAsset));
        } else {
            DB::transaction($operation);
        }

        return redirect()->route('admin.media.index')->with('status', 'Media updated. Its public URL is unchanged.');
    }

    public function destroy(Request $request, MediaAsset $mediaAsset, AssetUsage $usage, AssetStorage $storage)
    {
        $storage->remove($mediaAsset->disk, $mediaAsset->path, function () use ($request, $mediaAsset, $usage) {
            $asset = MediaAsset::lockForUpdate()->findOrFail($mediaAsset->id);
            if ($asset->path !== $mediaAsset->path || $asset->disk !== $mediaAsset->disk) {
                throw ValidationException::withMessages(['media' => 'This image changed during editing. Reload the library and try again.']);
            }
            if ($usage->media($asset)) {
                throw ValidationException::withMessages(['media' => 'This image is used by current content or saved history and cannot be deleted.']);
            }
            $this->audit($request, $asset, 'deleted');
            $asset->delete();
        }, null, fn () => $this->lockCurrent($mediaAsset));

        return redirect()->route('admin.media.index')->with('status', 'Unused media file removed.');
    }

    public function show(MediaAsset $mediaAsset)
    {
        abort_unless(in_array($mediaAsset->mime_type, ['image/jpeg', 'image/png', 'image/webp'], true)
            && Storage::disk($mediaAsset->disk)->exists($mediaAsset->path), 404);

        return Storage::disk($mediaAsset->disk)->response($mediaAsset->path, null, [
            'Content-Type' => $mediaAsset->mime_type, 'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'public, max-age=0, must-revalidate',
        ]);
    }

    private function rules(bool $required): array
    {
        return ['file' => [$required ? 'required' : 'nullable', 'file', 'max:5120'],
            'label' => ['required', 'string', 'max:120'], 'alt_text' => ['nullable', 'string', 'max:255']];
    }

    private function lockCurrent(MediaAsset $asset): void
    {
        $current = MediaAsset::lockForUpdate()->findOrFail($asset->id);
        if ($current->path !== $asset->path || $current->disk !== $asset->disk) {
            throw ValidationException::withMessages(['media' => 'This image changed during editing. Reload the library and try again.']);
        }
    }

    private function attributes(array $data, array $image, string $path): array
    {
        return ['label' => $data['label'], 'alt_text' => $data['alt_text'] ?? null, 'disk' => 'public', 'path' => $path,
            'original_name' => mb_substr(basename($data['file']->getClientOriginalName()), 0, 255),
            'mime_type' => $image['mime_type'], 'size_bytes' => $image['size_bytes'],
            'width' => $image['width'], 'height' => $image['height'], 'reencoded' => $image['reencoded']];
    }

    private function audit(Request $request, MediaAsset $asset, string $action): void
    {
        AuditLog::create(['user_id' => $request->user()->id, 'action' => 'media.'.$action,
            'subject_type' => 'media_asset', 'subject_id' => $asset->id, 'ip_address' => $request->ip(),
            'details' => ['filename' => $asset->original_name]]);
    }
}
