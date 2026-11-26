<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\FontAsset;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Imports Google Fonts into this site's own font library, so visitors'
 * browsers never contact Google (the privacy-safe way to use Google Fonts, and
 * the only way the site's CSP allows).
 *
 * Only two fixed hosts are ever contacted: fonts.googleapis.com for the CSS
 * that names the file, and fonts.gstatic.com for the file itself. Family names
 * and weights are validated before any request, and downloaded files go
 * through the same checks as uploaded fonts.
 */
class GoogleFonts
{
    private const CSS_URL = 'https://fonts.googleapis.com/css2';

    private const LIST_URL = 'https://www.googleapis.com/webfonts/v1/webfonts';

    public const WEIGHTS = [100, 200, 300, 400, 500, 600, 700, 800, 900];

    /** Popular families offered when no Google Fonts API key is configured. */
    public const POPULAR = [
        'Inter' => 'sans-serif', 'Roboto' => 'sans-serif', 'Open Sans' => 'sans-serif', 'Lato' => 'sans-serif',
        'Montserrat' => 'sans-serif', 'Poppins' => 'sans-serif', 'Source Sans 3' => 'sans-serif', 'Nunito' => 'sans-serif',
        'Raleway' => 'sans-serif', 'Work Sans' => 'sans-serif', 'DM Sans' => 'sans-serif', 'Manrope' => 'sans-serif',
        'IBM Plex Sans' => 'sans-serif', 'Space Grotesk' => 'sans-serif', 'Outfit' => 'sans-serif', 'Sora' => 'sans-serif',
        'Plus Jakarta Sans' => 'sans-serif', 'Rubik' => 'sans-serif', 'Karla' => 'sans-serif', 'Archivo' => 'sans-serif',
        'Barlow' => 'sans-serif', 'Josefin Sans' => 'sans-serif', 'Noto Sans' => 'sans-serif', 'Figtree' => 'sans-serif',
        'Playfair Display' => 'serif', 'Merriweather' => 'serif', 'Lora' => 'serif', 'Libre Baskerville' => 'serif',
        'EB Garamond' => 'serif', 'Cormorant Garamond' => 'serif', 'Crimson Pro' => 'serif', 'Source Serif 4' => 'serif',
        'IBM Plex Serif' => 'serif', 'Noto Serif' => 'serif', 'Fraunces' => 'serif', 'DM Serif Display' => 'serif',
        'JetBrains Mono' => 'monospace', 'Fira Code' => 'monospace', 'IBM Plex Mono' => 'monospace', 'Space Mono' => 'monospace',
        'Bebas Neue' => 'display', 'Oswald' => 'display', 'Syne' => 'display', 'Unbounded' => 'display',
    ];

    public function hasApiKey(): bool
    {
        return filled(config('services.google_fonts.api_key'));
    }

    /** @return list<array{family: string, category: string}> */
    public function search(string $query): array
    {
        $needle = Str::lower(trim($query));
        $catalogue = $this->hasApiKey() ? $this->catalogue() : collect(self::POPULAR)->map(fn ($category, $family) => ['family' => $family, 'category' => $category])->values()->all();

        return array_values(array_slice(array_filter($catalogue, fn ($font) => $needle === '' || str_contains(Str::lower($font['family']), $needle)), 0, 30));
    }

    /**
     * Downloads each requested weight into the font library.
     *
     * @param  list<int>  $weights
     * @return array{imported: list<string>, skipped: list<string>, failed: list<string>}
     */
    public function import(string $family, array $weights, string $style, int $userId): array
    {
        $family = $this->validFamily($family);
        if ($weights === [] || array_diff($weights, self::WEIGHTS) || ! in_array($style, ['normal', 'italic'], true)) {
            throw ValidationException::withMessages(['weights' => 'Choose at least one weight.']);
        }
        $result = ['imported' => [], 'skipped' => [], 'failed' => []];
        foreach (array_unique($weights) as $weight) {
            $label = "{$weight} {$style}";
            if (FontAsset::where('family', $family)->where('weight', (string) $weight)->where('style', $style)->exists()) {
                $result['skipped'][] = $label;

                continue;
            }
            try {
                $this->importOne($family, (int) $weight, $style, $userId);
                $result['imported'][] = $label;
            } catch (ValidationException $exception) {
                $result['failed'][] = $label.': '.collect($exception->errors())->flatten()->first();
            }
        }

        return $result;
    }

    public function specimenUrl(string $family): string
    {
        return 'https://fonts.google.com/specimen/'.str_replace(' ', '+', $family);
    }

    private function importOne(string $family, int $weight, string $style, int $userId): void
    {
        $axis = $style === 'italic' ? "ital,wght@1,{$weight}" : "wght@{$weight}";
        try {
            // A non-browser user agent gets one complete TrueType file (every glyph) instead of subsets.
            $css = Http::withHeaders(['User-Agent' => 'HamidPortfolio-FontImport'])->timeout(15)->connectTimeout(5)
                ->withoutRedirecting()->get(self::CSS_URL, ['family' => "{$family}:{$axis}", 'display' => 'swap']);
        } catch (ConnectionException) {
            throw ValidationException::withMessages(['font' => 'Google Fonts could not be reached. Try again later.']);
        }
        if ($css->status() === 400) {
            throw ValidationException::withMessages(['font' => 'not available from Google Fonts in this weight and style']);
        }
        if (! $css->successful() || strlen($css->body()) > 20000
            || ! preg_match("~src:\\s*url\\((https://fonts\\.gstatic\\.com/[A-Za-z0-9/._-]+\\.ttf)\\)\\s*format\\('truetype'\\)~", $css->body(), $match)) {
            throw ValidationException::withMessages(['font' => 'Google Fonts returned an unexpected response.']);
        }

        try {
            $file = Http::timeout(30)->connectTimeout(5)->withoutRedirecting()->withOptions([
                'on_headers' => function ($response) {
                    if ((int) $response->getHeaderLine('Content-Length') > 2 * 1024 * 1024) {
                        throw new \RuntimeException('too large');
                    }
                },
            ])->get($match[1]);
        } catch (Throwable) {
            throw ValidationException::withMessages(['font' => 'The font file could not be downloaded, or is larger than 2 MB.']);
        }
        if (! $file->successful() || strlen($file->body()) > 2 * 1024 * 1024) {
            throw ValidationException::withMessages(['font' => 'The font file could not be downloaded, or is larger than 2 MB.']);
        }

        // Same checks as an uploaded font.
        $temp = tempnam(sys_get_temp_dir(), 'gfont-');
        file_put_contents($temp, $file->body());
        try {
            $font = app(AssetUpload::class)->font(new UploadedFile($temp, Str::slug($family).'-'.$weight.'.ttf', 'font/ttf', null, true));
        } finally {
            @unlink($temp);
        }
        $storage = app(AssetStorage::class);
        $path = $storage->put('portfolio-fonts', $font['extension'], $font['bytes']);
        $storage->create($path, function () use ($family, $weight, $style, $font, $path, $userId) {
            $asset = FontAsset::create([
                'family' => $family, 'weight' => (string) $weight, 'style' => $style,
                'format' => $font['extension'], 'disk' => 'public', 'path' => $path, 'size_bytes' => $font['size_bytes'],
                'original_name' => Str::slug($family).'-'.$weight.($style === 'italic' ? '-italic' : '').'.ttf',
                'license_note' => 'From Google Fonts, under an open licence (SIL Open Font Licence, Apache 2.0 or Ubuntu Font Licence) that allows web embedding. See the specimen page.',
                'source_url' => $this->specimenUrl($family),
                'license_confirmed' => true, 'is_active' => true, 'uploaded_by' => $userId,
            ]);
            AuditLog::create(['user_id' => $userId, 'action' => 'font.google_imported', 'subject_type' => 'font_asset',
                'subject_id' => $asset->id, 'details' => ['name' => "{$family} {$weight} {$style}"]]);
        });
    }

    private function validFamily(string $family): string
    {
        $family = trim(preg_replace('/\s+/', ' ', $family));
        if (! preg_match('/^[A-Za-z0-9][A-Za-z0-9 ]{0,59}$/', $family)) {
            throw ValidationException::withMessages(['family' => 'Use the font family name exactly as Google Fonts shows it, such as "Playfair Display".']);
        }

        return $family;
    }

    /** @return list<array{family: string, category: string}> */
    private function catalogue(): array
    {
        return Cache::remember('google-fonts:catalogue', now()->addDay(), function () {
            try {
                $response = Http::timeout(15)->connectTimeout(5)->withoutRedirecting()
                    ->get(self::LIST_URL, ['key' => config('services.google_fonts.api_key'), 'sort' => 'popularity']);
            } catch (ConnectionException) {
                return collect(self::POPULAR)->map(fn ($c, $f) => ['family' => $f, 'category' => $c])->values()->all();
            }
            $items = $response->successful() ? ($response->json('items') ?? []) : [];

            return collect($items)->filter(fn ($item) => is_string($item['family'] ?? null) && preg_match('/^[A-Za-z0-9][A-Za-z0-9 ]{0,59}$/', $item['family']))
                ->map(fn ($item) => ['family' => $item['family'], 'category' => (string) ($item['category'] ?? '')])
                ->values()->all() ?: collect(self::POPULAR)->map(fn ($c, $f) => ['family' => $f, 'category' => $c])->values()->all();
        });
    }
}
