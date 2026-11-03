<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\FontAsset;
use App\Services\AssetStorage;
use App\Services\AssetUpload;
use App\Services\AssetUsage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class FontController extends Controller
{
    public function index(AssetUsage $usage)
    {
        $assets = FontAsset::orderBy('family')->paginate(24);

        return view('admin.fonts', ['assets' => $assets,
            'usage' => $assets->mapWithKeys(fn ($asset) => [$asset->id => $usage->font($asset)])]);
    }

    public function store(Request $request, AssetUpload $upload, AssetStorage $storage)
    {
        $data = $request->validate([
            'file' => ['required', 'file', 'max:2048'],
            'family' => ['required', 'string', 'max:120'],
            'weight' => ['required', 'in:100,200,300,400,500,600,700,800,900'],
            'style' => ['required', 'in:normal,italic,oblique'],
            'license_note' => ['required', 'string', 'max:255'],
            'source_url' => ['required', 'url:http,https', 'max:2048'],
            'license_confirmed' => ['required', 'accepted'],
        ]);
        $font = $upload->font($data['file']);
        $path = $storage->put('portfolio-fonts', $font['extension'], $font['bytes']);
        $storage->create($path, function () use ($request, $data, $font, $path) {
            $asset = FontAsset::create([
                'family' => $data['family'], 'weight' => $data['weight'], 'style' => $data['style'],
                'format' => $font['extension'], 'disk' => 'public', 'path' => $path,
                'size_bytes' => $font['size_bytes'], 'original_name' => mb_substr(basename($data['file']->getClientOriginalName()), 0, 255),
                'license_note' => $data['license_note'], 'source_url' => $data['source_url'],
                'license_confirmed' => true, 'is_active' => true, 'uploaded_by' => $request->user()->id,
            ]);
            $this->audit($request, $asset, 'uploaded');
        });

        return redirect()->route('admin.fonts.index')->with('status', 'Font uploaded and activated.');
    }

    public function update(Request $request, FontAsset $fontAsset, AssetUsage $usage)
    {
        $data = $request->validate(['is_active' => ['required', 'boolean']]);
        DB::transaction(function () use ($request, $fontAsset, $usage, $data) {
            $fontAsset = FontAsset::lockForUpdate()->findOrFail($fontAsset->id);
            if (! $data['is_active'] && $usage->font($fontAsset)) {
                throw ValidationException::withMessages(['font' => 'Choose another font in current content and saved history before deactivating this font.']);
            }
            if ($data['is_active'] && (! $fontAsset->license_confirmed || ! Storage::disk($fontAsset->disk)->exists($fontAsset->path))) {
                throw ValidationException::withMessages(['font' => 'Only licensed fonts with an existing file can be activated.']);
            }
            $fontAsset->update($data);
            $this->audit($request, $fontAsset, $data['is_active'] ? 'activated' : 'deactivated');
        });

        return redirect()->route('admin.fonts.index')->with('status', 'Font activation updated.');
    }

    public function destroy(Request $request, FontAsset $fontAsset, AssetUsage $usage, AssetStorage $storage)
    {
        $storage->remove($fontAsset->disk, $fontAsset->path, function () use ($request, $fontAsset, $usage) {
            $fontAsset = FontAsset::lockForUpdate()->findOrFail($fontAsset->id);
            if ($usage->font($fontAsset)) {
                throw ValidationException::withMessages(['font' => 'This font is used by current content or saved history and cannot be deleted.']);
            }
            $this->audit($request, $fontAsset, 'deleted');
            $fontAsset->delete();
        }, null, fn () => FontAsset::lockForUpdate()->findOrFail($fontAsset->id));

        return redirect()->route('admin.fonts.index')->with('status', 'Unused font removed.');
    }

    public function show(FontAsset $fontAsset)
    {
        abort_unless($fontAsset->is_active && $fontAsset->license_confirmed
            && Storage::disk($fontAsset->disk)->exists($fontAsset->path), 404);
        $mime = ['woff2' => 'font/woff2', 'woff' => 'font/woff', 'ttf' => 'font/ttf', 'otf' => 'font/otf'][$fontAsset->format] ?? null;
        abort_unless($mime, 404);

        return Storage::disk($fontAsset->disk)->response($fontAsset->path, null,
            ['Content-Type' => $mime, 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'public, max-age=3600']);
    }

    private function audit(Request $request, FontAsset $asset, string $action): void
    {
        AuditLog::create(['user_id' => $request->user()->id, 'action' => 'font.'.$action,
            'subject_type' => 'font_asset', 'subject_id' => $asset->id, 'ip_address' => $request->ip(),
            'details' => ['family' => $asset->family]]);
    }
}
