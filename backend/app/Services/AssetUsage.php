<?php

namespace App\Services;

use App\Models\FontAsset;
use App\Models\MediaAsset;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AssetUsage
{
    public function font(FontAsset $asset): array
    {
        return $this->find(fn ($value) => is_string($value) && preg_match('/\b'.preg_quote($asset->cssFamily(), '/').'\b/', $value));
    }

    public function media(MediaAsset $asset): array
    {
        return $this->find(function ($value, $key) use ($asset) {
            if (in_array($key, ['media_asset_id', 'og_image_media_id', 'gallery_media_ids', 'featured_media_id', 'image_media_id', 'background_media_id'], true)
                && (string) $value === (string) $asset->id) {
                return true;
            }

            return is_string($value) && (str_contains($value, $asset->path)
                || preg_match('~/media/'.preg_quote((string) $asset->id, '~').'(?:[/?#\s"\'<>]|$)~', $value));
        });
    }

    public function mediaLegacyReferences(MediaAsset $asset): array
    {
        return $this->find(fn ($value) => is_string($value) && str_contains($value, $asset->path));
    }

    private function find(callable $matches): array
    {
        $usage = [];
        foreach (['portfolio_settings', 'projects', 'portfolio_sections', 'theme_presets', 'content_revisions'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            foreach (DB::table($table)->orderBy('id')->cursor() as $row) {
                if ($this->contains((array) $row, '', $matches)) {
                    $usage[] = $table.' #'.$row->id;
                }
            }
        }

        return $usage;
    }

    private function contains(mixed $value, string $key, callable $matches): bool
    {
        if (is_array($value)) {
            foreach ($value as $childKey => $child) {
                if ($this->contains($child, is_string($childKey) ? $childKey : $key, $matches)) {
                    return true;
                }
            }
        } elseif (is_string($value) && is_array($decoded = json_decode($value, true))) {
            return $this->contains($decoded, $key, $matches);
        } else {
            return (bool) $matches($value, $key);
        }

        return false;
    }
}
