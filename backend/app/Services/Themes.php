<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\PortfolioSetting;
use App\Models\ThemePreset;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * A theme is the site's whole look: five colours, two fonts, width, spacing,
 * corner radius and motion. Applying one never touches content, and the
 * previous look is saved to Content history first so it can be restored.
 *
 * Built-in themes are all dark: the public stylesheet hard-codes many light
 * text colours, so a light background would make parts of the site unreadable.
 */
class Themes
{
    public const FORMAT = 'hamid-portfolio-theme';

    public const COLORS = ['color_background' => 'Background', 'color_foreground' => 'Text', 'color_primary' => 'Primary', 'color_accent' => 'Accent', 'color_cyan' => 'Secondary accent'];

    public const FIELDS = [
        'color_background', 'color_foreground', 'color_primary', 'color_accent', 'color_cyan',
        'font_body', 'font_heading', 'max_width', 'section_spacing', 'border_radius',
        'animations_enabled', 'particles_enabled',
    ];

    public const DEFAULT = 'cosmic';

    public const BUILT_IN = [
        'cosmic' => ['Cosmic (default)', 'The original violet night sky with drifting particles.',
            ['#070811', '#f3f1f8', '#ad91ff', '#c4b1ff', '#92d8ee', 'Inter', 'Inter', 1180, 140, 12, true, true]],
        'midnight' => ['Midnight blue', 'Deep navy with cool blue accents.',
            ['#060b16', '#eef3fb', '#6ea8ff', '#9cc3ff', '#7ee0e6', 'Inter', 'Inter', 1180, 140, 12, true, true]],
        'emerald' => ['Emerald', 'Dark green-black with fresh green highlights.',
            ['#06100c', '#eef7f1', '#4fd18b', '#8de3b3', '#9fd8ee', 'Inter', 'Inter', 1180, 140, 14, true, true]],
        'ember' => ['Ember', 'Warm charcoal with amber and coral.',
            ['#120a08', '#faf1ec', '#ff9b5e', '#ffc28f', '#f2a7b5', 'Inter', 'Inter', 1180, 140, 10, true, false]],
        'graphite' => ['Graphite', 'Quiet neutral greys, square corners, no particles.',
            ['#0c0c0e', '#f1f1f3', '#c9c9d1', '#e4e4ea', '#a9b8c9', 'system-ui, sans-serif', 'system-ui, sans-serif', 1100, 120, 4, false, false]],
        'editorial' => ['Editorial', 'Serif headings and generous spacing, like a magazine.',
            ['#0d0b10', '#f5f1ea', '#d9b98a', '#efd9b4', '#a9c7d8', 'Inter', 'Georgia, serif', 1080, 180, 6, true, false]],
        'terminal' => ['Terminal', 'Monospace type and green-on-black, for a developer feel.',
            ['#050805', '#e6f5e6', '#4ee07a', '#9af0b0', '#7fd6e0', 'monospace', 'monospace', 1180, 120, 2, false, false]],
        'rose' => ['Rose', 'Plum-black with soft pink accents.',
            ['#100810', '#f8eef5', '#f08cc0', '#f6b9d8', '#b9a8f2', 'Inter', 'Inter', 1180, 140, 16, true, true]],
    ];

    /** @return array<string, array{name: string, description: string, values: array<string, mixed>}> */
    public function builtIn(): array
    {
        return collect(self::BUILT_IN)->map(fn ($theme) => [
            'name' => $theme[0], 'description' => $theme[1], 'values' => array_combine(self::FIELDS, $theme[2]),
        ])->all();
    }

    /** The full set of values for a saved theme. Older colour-only presets keep the current fonts and layout. */
    public function valuesOf(ThemePreset $preset, ?PortfolioSetting $current = null): array
    {
        $current ??= PortfolioSetting::findOrFail(1);

        return array_merge($this->current($current), $preset->settings ?? [], $preset->only(array_keys(self::COLORS)));
    }

    public function current(?PortfolioSetting $settings = null): array
    {
        $settings ??= PortfolioSetting::findOrFail(1);

        return collect(self::FIELDS)->mapWithKeys(fn ($field) => [$field => $settings->getAttribute($field)])->all();
    }

    /** Validates a full set of theme values (from a form, a file or a built-in theme). */
    public function validate(array $values, string $prefix = ''): array
    {
        $rules = [];
        foreach (array_keys(self::COLORS) as $color) {
            $rules[$color] = ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'];
        }
        $rules += [
            'font_body' => ['required', 'string', 'max:60'],
            'font_heading' => ['required', 'string', 'max:60'],
            'max_width' => ['required', 'integer', 'between:720,1600'],
            'section_spacing' => ['required', 'integer', 'between:48,240'],
            'border_radius' => ['required', 'integer', 'between:0,32'],
            'animations_enabled' => ['required', 'boolean'],
            'particles_enabled' => ['required', 'boolean'],
        ];
        $validated = Validator::make(array_intersect_key($values, array_flip(self::FIELDS)), $rules)->validate();
        app(FontLibrary::class)->validateSelection($validated['font_body'], $prefix.'font_body');
        app(FontLibrary::class)->validateSelection($validated['font_heading'], $prefix.'font_heading');
        foreach (array_keys(self::COLORS) as $color) {
            $validated[$color] = strtolower($validated[$color]);
        }
        foreach (['max_width', 'section_spacing', 'border_radius'] as $number) {
            $validated[$number] = (int) $validated[$number];
        }
        foreach (['animations_enabled', 'particles_enabled'] as $flag) {
            $validated[$flag] = filter_var($validated[$flag], FILTER_VALIDATE_BOOLEAN);
        }

        return $validated;
    }

    /** Publishes a theme's look. Content is untouched; the previous look goes to Content history. */
    public function apply(array $values, string $name, ?int $userId): void
    {
        $values = $this->validate($values);
        DB::transaction(function () use ($values, $name, $userId) {
            app(ContentHistory::class)->site('Before applying the "'.$name.'" theme', $userId);
            PortfolioSetting::lockForUpdate()->findOrFail(1)->forceFill($values)->save();
            AuditLog::create(['user_id' => $userId, 'action' => 'theme.applied', 'details' => ['name' => $name]]);
        });
    }

    public function save(string $name, array $values, ?int $userId, ?ThemePreset $preset = null): ThemePreset
    {
        $values = $this->validate($values);
        $preset ??= new ThemePreset;
        $preset->fill(['name' => $name] + array_intersect_key($values, self::COLORS));
        $preset->settings = array_diff_key($values, self::COLORS);
        $preset->save();
        AuditLog::create(['user_id' => $userId, 'action' => $preset->wasRecentlyCreated ? 'theme.saved' : 'theme.updated',
            'subject_type' => 'theme_preset', 'subject_id' => $preset->id, 'details' => ['name' => $name]]);

        return $preset;
    }

    public function export(string $name, array $values): array
    {
        return ['format' => self::FORMAT, 'version' => 1, 'name' => $name, 'theme' => array_intersect_key($values, array_flip(self::FIELDS))];
    }

    /** Reads an exported theme file. Only known keys are accepted; uploaded fonts must exist on this site. */
    public function import(string $json): array
    {
        try {
            $data = json_decode($json, true, 8, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw ValidationException::withMessages(['theme_file' => 'That file is not valid JSON.']);
        }
        if (! is_array($data) || ($data['format'] ?? null) !== self::FORMAT || ($data['version'] ?? null) !== 1 || ! is_array($data['theme'] ?? null)) {
            throw ValidationException::withMessages(['theme_file' => 'That is not a theme file exported from this dashboard.']);
        }
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 100) {
            throw ValidationException::withMessages(['theme_file' => 'The theme file needs a name of up to 100 characters.']);
        }
        try {
            $values = $this->validate($data['theme']);
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(['theme_file' => 'The theme file has an invalid value: '.collect($exception->errors())->flatten()->first()]);
        }

        return ['name' => $name, 'values' => $values];
    }
}
