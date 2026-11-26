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
    public const SORTS = ['newest' => 'Newest first', 'oldest' => 'Oldest first', 'largest' => 'Largest first', 'name' => 'Label A–Z'];

    public const TYPES = ['image/jpeg' => 'JPG', 'image/png' => 'PNG', 'image/webp' => 'WebP'];

    public function index(Request $request, AssetUsage $usage)
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'type' => ['nullable', 'in:'.implode(',', array_keys(self::TYPES))],
            'usage' => ['nullable', 'in:used,unused'],
            'alt' => ['nullable', 'in:missing'],
            'sort' => ['nullable', 'in:'.implode(',', array_keys(self::SORTS))],
        ]);
        $search = $filters['q'] ?? '';
        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%';
        $query = MediaAsset::query()
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($like) {
                $query->where('label', 'like', $like)->orWhere('original_name', 'like', $like)->orWhere('alt_text', 'like', $like);
            }))
            ->when($filters['type'] ?? null, fn ($query, $type) => $query->where('mime_type', $type))
            ->when(($filters['alt'] ?? null) === 'missing', fn ($query) => $query->where(fn ($q) => $q->whereNull('alt_text')->orWhere('alt_text', '')));
        match ($filters['sort'] ?? 'newest') {
            'oldest' => $query->oldest()->oldest('id'),
            'largest' => $query->orderByDesc('size_bytes')->orderByDesc('id'),
            'name' => $query->orderBy('label')->orderBy('id'),
            default => $query->latest()->latest('id'),
        };

        // Usage lives in content and history, not on the asset row, so the
        // used/unused filter is applied after checking each candidate.
        if ($wanted = $filters['usage'] ?? null) {
            $ids = $query->get()->filter(fn (MediaAsset $asset) => ($wanted === 'used') === (bool) $usage->media($asset))->pluck('id');
            $query = MediaAsset::whereIn('id', $ids)->orderByRaw($ids->isEmpty() ? '1' : 'FIELD(id, '.$ids->implode(',').')');
        }
        $assets = $query->with('uploader')->paginate(24)->withQueryString();

        return view('admin.media', ['assets' => $assets, 'search' => $search, 'filters' => $filters,
            'sorts' => self::SORTS, 'types' => self::TYPES,
            'storageBytes' => MediaAsset::sum('size_bytes'),
            'totalCount' => MediaAsset::count(),
            'missingAlt' => MediaAsset::where(fn ($q) => $q->whereNull('alt_text')->orWhere('alt_text', ''))->count(),
            'usage' => $assets->mapWithKeys(fn ($asset) => [$asset->id => $usage->media($asset)])]);
    }

    /**
     * Accepts either one "file" (the original form) or up to 10 "files[]".
     * The drag-and-drop uploader sends one file per request, so a large batch
     * never exceeds the server's request size limit.
     */
    public function store(Request $request, AssetUpload $upload, AssetStorage $storage)
    {
        if (! $request->hasFile('files')) {
            $data = $request->validate($this->rules(true));
            $asset = $this->storeOne($request, $upload, $storage, $data['file'], $data['label'] ?? null, $data['alt_text'] ?? null);

            return $request->wantsJson()
                ? response()->json(['uploaded' => [['id' => $asset->id, 'label' => $asset->label]], 'failed' => []])
                : redirect()->route('admin.media.index')->with('status', 'Image uploaded to the media library.');
        }

        $data = $request->validate([
            'files' => ['required', 'array', 'min:1', 'max:10'],
            'files.*' => ['file', 'max:5120'],
            'alt_text' => ['nullable', 'string', 'max:255'],
        ]);
        $uploaded = $failed = [];
        foreach ($data['files'] as $file) {
            $name = mb_substr(basename($file->getClientOriginalName()), 0, 120);
            try {
                $asset = $this->storeOne($request, $upload, $storage, $file, null, $data['alt_text'] ?? null);
                $uploaded[] = ['id' => $asset->id, 'label' => $asset->label];
            } catch (ValidationException $exception) {
                $failed[] = ['name' => $name, 'error' => collect($exception->errors())->flatten()->first()];
            }
        }

        if ($request->wantsJson()) {
            return response()->json(['uploaded' => $uploaded, 'failed' => $failed], $uploaded ? 200 : 422);
        }
        $response = redirect()->route('admin.media.index', $failed ? [] : ['alt' => 'missing'])
            ->with('status', count($uploaded).' image(s) uploaded.'.($uploaded && ! ($data['alt_text'] ?? null) ? ' Add alt text to each one below.' : ''));

        return $failed
            ? $response->withErrors(['files' => collect($failed)->map(fn ($f) => $f['name'].': '.$f['error'])->implode(' · ')])
            : $response;
    }

    /** Downloads the stored (re-encoded) file under its original name. */
    public function download(MediaAsset $mediaAsset)
    {
        abort_unless(Storage::disk($mediaAsset->disk)->exists($mediaAsset->path), 404);
        $extension = pathinfo($mediaAsset->path, PATHINFO_EXTENSION);
        $base = pathinfo((string) $mediaAsset->original_name, PATHINFO_FILENAME) ?: 'media-'.$mediaAsset->id;
        $name = preg_replace('/[^A-Za-z0-9._ -]+/', '-', $base).'.'.$extension;

        return Storage::disk($mediaAsset->disk)->download($mediaAsset->path, $name, [
            'Content-Type' => $mediaAsset->mime_type, 'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function storeOne(Request $request, AssetUpload $upload, AssetStorage $storage, $file, ?string $label, ?string $alt): MediaAsset
    {
        $image = $upload->image($file);
        $path = $storage->put('portfolio-media', $image['extension'], $image['bytes']);
        $label = $label ?: $this->labelFromFilename($file->getClientOriginalName());

        return $storage->create($path, function () use ($request, $file, $image, $path, $label, $alt) {
            $asset = MediaAsset::create($this->attributes(['file' => $file, 'label' => $label, 'alt_text' => $alt], $image, $path)
                + ['uploaded_by' => $request->user()->id]);
            $this->audit($request, $asset, 'uploaded');

            return $asset;
        });
    }

    private function labelFromFilename(string $filename): string
    {
        $label = trim(preg_replace('/[\s_-]+/', ' ', pathinfo($filename, PATHINFO_FILENAME)));

        return mb_substr($label !== '' ? ucfirst($label) : 'Untitled image', 0, 120);
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
            'label' => [$required ? 'nullable' : 'required', 'string', 'max:120'], 'alt_text' => ['nullable', 'string', 'max:255']];
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
