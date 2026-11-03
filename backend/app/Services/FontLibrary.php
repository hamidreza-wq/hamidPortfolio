<?php

namespace App\Services;

use App\Models\FontAsset;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class FontLibrary
{
    public function activeOptions(): Collection
    {
        return FontAsset::where('is_active', true)->where('license_confirmed', true)->orderBy('family')->get()
            ->filter(fn ($font) => Storage::disk($font->disk)->exists($font->path)
                && in_array($font->format, ['ttf', 'otf', 'woff', 'woff2'], true)
                && preg_match('/^[1-9]00$/', $font->weight)
                && in_array($font->style, ['normal', 'italic', 'oblique'], true));
    }

    public function validateSelection(string $value, string $field): void
    {
        if (str_starts_with($value, 'PortfolioFont')) {
            if (! preg_match('/^PortfolioFont([1-9][0-9]*)$/', $value, $match)
                || ! $this->activeOptions()->contains('id', (int) $match[1])) {
                throw ValidationException::withMessages([$field => 'Select an active, licensed uploaded font.']);
            }
        } elseif (! preg_match('/^[\w ,_-]{1,60}$/', $value)) {
            throw ValidationException::withMessages([$field => 'Use a safe font family or system font stack.']);
        }
    }

    public function payload(): array
    {
        return $this->activeOptions()->map(fn ($font) => [
            'id' => $font->id, 'family' => $font->family, 'css_family' => $font->cssFamily(),
            'weight' => $font->weight, 'style' => $font->style,
            'format' => $font->format, 'url' => route('fonts.show', $font),
        ])->values()->all();
    }

    public function css(): string
    {
        return implode("\n", array_map(function ($font) {
            $format = ['ttf' => 'truetype', 'otf' => 'opentype', 'woff' => 'woff', 'woff2' => 'woff2'][$font['format']];
            $url = str_replace(['\\', '"', "\r", "\n", '<', '>'], ['\\\\', '\\"', '', '', '%3C', '%3E'], $font['url']);

            return '@font-face{font-family:"'.$font['css_family'].'";src:url("'.$url.'") format("'.$format.'");font-weight:'.$font['weight'].';font-style:'.$font['style'].';font-display:swap;}';
        }, $this->payload()));
    }
}
