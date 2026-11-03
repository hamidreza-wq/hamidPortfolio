<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\ThemePreset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ThemePresetController extends Controller
{
    public function store(Request $request)
    {
        $rules = ['name' => ['required', 'string', 'max:120', Rule::unique('theme_presets')]];
        foreach (['color_background', 'color_foreground', 'color_primary', 'color_accent', 'color_cyan'] as $field) {
            $rules[$field] = ['required', 'regex:/^#[0-9a-fA-F]{6}$/'];
        }
        $validated = $request->validate($rules);
        DB::transaction(function () use ($request, $validated): void {
            $preset = ThemePreset::create($validated);
            AuditLog::create([
                'user_id' => $request->user()->id,
                'action' => 'theme_preset.created',
                'subject_type' => 'theme_preset',
                'subject_id' => $preset->id,
            ]);
        });

        return redirect()->route('admin.site-editor.edit')->with('status', 'Theme preset saved. Public colors have not changed.');
    }

    public function destroy(Request $request, ThemePreset $themePreset)
    {
        DB::transaction(function () use ($request, $themePreset): void {
            AuditLog::create([
                'user_id' => $request->user()->id,
                'action' => 'theme_preset.deleted',
                'subject_type' => 'theme_preset',
                'subject_id' => $themePreset->id,
            ]);
            $themePreset->delete();
        });

        return redirect()->route('admin.site-editor.edit')->with('status', 'Preset removed. Published colors have not changed.');
    }
}
