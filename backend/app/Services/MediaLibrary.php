<?php

namespace App\Services;

use App\Models\MediaAsset;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class MediaLibrary
{
    public function options(): Collection
    {
        return MediaAsset::orderBy('label')->get();
    }

    public function selectionRules(): array
    {
        return ['nullable', 'integer', 'exists:media_assets,id', function ($attribute, $value, $fail) {
            $asset = MediaAsset::find($value);
            if ($asset && ! Storage::disk($asset->disk)->exists($asset->path)) {
                $fail('The selected library image is missing from storage. Restore the file or choose another image.');
            }
        }];
    }

    public function publicPayload(?int $id): ?array
    {
        $asset = $id ? MediaAsset::find($id) : null;
        if (! $asset || ! Storage::disk($asset->disk)->exists($asset->path)) {
            return null;
        }

        return ['id' => $asset->id, 'url' => $asset->publicUrl(), 'alt' => $asset->alt_text ?? '',
            'width' => $asset->width, 'height' => $asset->height];
    }
}
