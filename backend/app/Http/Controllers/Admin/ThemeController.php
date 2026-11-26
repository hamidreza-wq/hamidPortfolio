<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ThemePreset;
use App\Services\FontLibrary;
use App\Services\Themes;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ThemeController extends Controller
{
    public function index(Themes $themes)
    {
        return view('admin.themes', [
            'current' => $themes->current(),
            'builtIn' => $themes->builtIn(),
            'saved' => ThemePreset::orderBy('name')->get(),
            'themes' => $themes,
            'fontOptions' => $this->fontOptions(),
        ]);
    }

    /** Publishes a built-in or saved theme. */
    public function apply(Request $request, Themes $themes)
    {
        [$name, $values] = $this->source($request, $themes);
        $themes->apply($values, $name, $request->user()->id);

        return back()->with('status', "\"{$name}\" is now live. Content is unchanged; the previous look is saved in Content history.");
    }

    /** Saves the live look, or a copy of a built-in or saved theme, as a new editable theme. */
    public function store(Request $request, Themes $themes)
    {
        $request->validate(['name' => ['required', 'string', 'max:100', Rule::unique('theme_presets', 'name')]]);
        $values = $request->filled('source') ? $this->source($request, $themes)[1] : $themes->current();
        $preset = $themes->save($request->string('name')->trim()->toString(), $values, $request->user()->id);

        return redirect()->to(route('admin.themes.index').'#theme-'.$preset->id)->with('status', "Saved \"{$preset->name}\". The published look has not changed.");
    }

    public function update(Request $request, ThemePreset $themePreset, Themes $themes)
    {
        $request->validate(['name' => ['required', 'string', 'max:100', Rule::unique('theme_presets', 'name')->ignore($themePreset->id)]]);
        $values = $request->input('theme', []);
        foreach (['animations_enabled', 'particles_enabled'] as $flag) {
            $values[$flag] = $request->boolean("theme.$flag");
        }
        $themes->save($request->string('name')->trim()->toString(), $values, $request->user()->id, $themePreset);

        return back()->with('status', "Saved \"{$themePreset->name}\". Apply it to publish.");
    }

    public function export(Request $request, Themes $themes)
    {
        [$name, $values] = $this->source($request, $themes, allowCurrent: true);
        $file = Str::slug($name) ?: 'theme';

        return response()->json($themes->export($name, $values), 200, [
            'Content-Disposition' => 'attachment; filename="'.$file.'.theme.json"',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    public function import(Request $request, Themes $themes)
    {
        $request->validate(['theme_file' => ['required', 'file', 'max:16', 'mimetypes:application/json,text/plain']]);
        $theme = $themes->import((string) file_get_contents($request->file('theme_file')->getRealPath()));
        $name = $theme['name'];
        for ($i = 2; ThemePreset::where('name', $name)->exists(); $i++) {
            $name = mb_substr($theme['name'], 0, 90)." ({$i})";
        }
        $preset = $themes->save($name, $theme['values'], $request->user()->id);

        return redirect()->to(route('admin.themes.index').'#theme-'.$preset->id)->with('status', "Imported \"{$preset->name}\". Preview it, then apply it to publish.");
    }

    /** @return array{0: string, 1: array<string, mixed>} */
    private function source(Request $request, Themes $themes, bool $allowCurrent = false): array
    {
        $source = (string) $request->input('source');
        if ($allowCurrent && $source === 'current') {
            return ['Current look', $themes->current()];
        }
        if (str_starts_with($source, 'built-in:') && isset($themes->builtIn()[$key = substr($source, 9)])) {
            $theme = $themes->builtIn()[$key];

            return [$theme['name'], $theme['values']];
        }
        if (preg_match('/^saved:(\d+)$/', $source, $match) && ($preset = ThemePreset::find((int) $match[1]))) {
            return [$preset->name, $themes->valuesOf($preset)];
        }
        abort(404);
    }

    /** @return array<string, string> */
    private function fontOptions(): array
    {
        $options = ['Inter' => 'Inter', 'system-ui, sans-serif' => 'System UI', 'Georgia, serif' => 'Georgia (serif)', 'monospace' => 'Monospace'];
        foreach (app(FontLibrary::class)->activeOptions() as $font) {
            $options[$font->cssFamily()] = $font->family.' · '.$font->weight.' '.$font->style.' (uploaded)';
        }

        return $options;
    }
}
