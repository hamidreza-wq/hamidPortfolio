<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\PublicPortfolioController;
use App\Models\AuditLog;
use App\Models\PortfolioSection;
use App\Models\PortfolioSetting;
use App\Models\Skill;
use App\Models\SkillCategory;
use App\Services\ContentHistory;
use App\Services\FontLibrary;
use App\Services\MediaLibrary;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SiteEditorController extends Controller
{
    public function edit()
    {
        return view('admin.site-editor', [
            'settings' => PortfolioSetting::findOrFail(1),
            'sections' => PortfolioSection::orderBy('sort_order')->get(),
            'skillCategories' => SkillCategory::with('skills')->orderBy('sort_order')->get(),
            'previewPayload' => app(PublicPortfolioController::class)
                ->previewPayloadForAdmin(),
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'settings.name' => ['required', 'string', 'max:120'],
            'settings.professional_title' => ['required', 'string', 'max:120'],
            'settings.github_url' => ['required', 'url', 'max:255', 'regex:/^https:\/\/github\.com\//i'],
            'settings.email' => ['nullable', 'email', 'max:255'],
            'settings.linkedin_url' => ['nullable', 'url:http,https', 'max:255'],
            'settings.site_title' => ['required', 'string', 'max:255'],
            'settings.site_description' => ['required', 'string', 'max:320'],
            'settings.canonical_url' => ['nullable', 'url:http,https', 'max:255', 'regex:/^https?:\\/\\/[^\\/?#@]+(?:\\/[^?#]*)?$/i'],
            'settings.og_image' => ['nullable', 'url:http,https', 'max:255'],
            'settings.og_image_media_id' => app(MediaLibrary::class)->selectionRules(),
            'settings.og_title' => ['nullable', 'string', 'max:255'],
            'settings.og_description' => ['nullable', 'string', 'max:320'],
            'settings.language' => ['sometimes', 'required', 'regex:/^[a-zA-Z]{2,3}(?:-[a-zA-Z0-9]{2,8})*$/', 'max:35'],
            'settings.indexing_enabled' => ['sometimes', 'required', 'boolean'],
            'settings.robots_follow' => ['sometimes', 'required', 'boolean'],
            'settings.maintenance_mode' => ['required', 'boolean'],
            'settings.color_background' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'settings.color_foreground' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'settings.color_primary' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'settings.color_accent' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'settings.color_cyan' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'settings.font_body' => ['required', 'regex:/^[\w ,_-]{1,60}$/'],
            'settings.font_heading' => ['required', 'regex:/^[\w ,_-]{1,60}$/'],
            'settings.max_width' => ['required', 'integer', 'between:720,1600'],
            'settings.section_spacing' => ['required', 'integer', 'between:48,240'],
            'settings.border_radius' => ['required', 'integer', 'between:0,32'],
            'settings.animations_enabled' => ['required', 'boolean'],
            'settings.particles_enabled' => ['required', 'boolean'],
            'settings.footer_text' => ['nullable', 'string', 'max:255'],
            'sections' => ['required', 'array', 'min:6', 'max:36'],
            'sections.*' => ['array:title,subtitle,body,is_visible,sort_order,label,show_in_navigation,spacing_top,spacing_bottom,background_effect,image_media_id,background_media_id'],
            'sections.*.label' => ['sometimes', 'required', 'string', 'max:60'],
            'sections.*.show_in_navigation' => ['sometimes', 'required', 'boolean'],
            'sections.*.spacing_top' => ['sometimes', 'required', 'integer', 'between:0,240'],
            'sections.*.spacing_bottom' => ['sometimes', 'required', 'integer', 'between:0,240'],
            'sections.*.background_effect' => ['sometimes', 'required', 'in:cosmic,none'],
            'sections.*.image_media_id' => app(MediaLibrary::class)->selectionRules(),
            'sections.*.background_media_id' => app(MediaLibrary::class)->selectionRules(),
            'sections.*.title' => ['required', 'string', 'max:180'],
            'sections.*.subtitle' => ['nullable', 'string', 'max:180'],
            'sections.*.body' => ['nullable', 'string', 'max:3000'],
            'sections.*.is_visible' => ['required', 'boolean'],
            'sections.*.sort_order' => ['required', 'integer', 'between:0,100'],
            'skill_categories' => ['required', 'array'],
            'skill_categories.*.title' => ['required', 'string', 'max:120'],
            'skill_categories.*.skills' => ['required', 'string', 'max:2000'],
            'skill_categories.*.is_visible' => ['required', 'boolean'],
            'skill_categories.*.sort_order' => ['required', 'integer', 'between:0,100'],
        ]);
        app(FontLibrary::class)->validateSelection($validated['settings']['font_body'], 'settings.font_body');
        app(FontLibrary::class)->validateSelection($validated['settings']['font_heading'], 'settings.font_heading');

        DB::transaction(function () use ($validated, $request): void {
            $settings = PortfolioSetting::query()->lockForUpdate()->findOrFail(1);
            app(ContentHistory::class)->site('Before publishing site changes', $request->user()->id);
            $settings->fill($validated['settings']);
            $settings->save();

            $sections = PortfolioSection::query()->lockForUpdate()->get()->keyBy('key');
            foreach ($validated['sections'] as $key => $values) {
                abort_unless($sections->has($key), 422, 'An unknown portfolio section was submitted.');
                $sections->get($key)->fill($values)->save();
            }

            $categories = SkillCategory::query()->lockForUpdate()->get()->keyBy('id');
            foreach ($validated['skill_categories'] as $id => $values) {
                abort_unless($categories->has($id), 422, 'An unknown skill category was submitted.');
                $category = $categories->get($id);
                $category->fill([
                    'title' => $values['title'],
                    'is_visible' => $values['is_visible'],
                    'sort_order' => $values['sort_order'],
                ])->save();

                $desiredNames = collect(preg_split('/\R/', $values['skills']) ?: [])
                    ->map(fn (string $value) => trim($value))
                    ->filter()
                    ->unique()
                    ->values();
                $existingSkills = Skill::query()->where('skill_category_id', $category->id)->get()->keyBy('name');
                foreach ($desiredNames as $order => $name) {
                    $skill = $existingSkills->pull($name) ?? new Skill([
                        'skill_category_id' => $category->id,
                        'name' => $name,
                    ]);
                    $skill->sort_order = $order;
                    $skill->is_visible = true;
                    $skill->save();
                }
                $existingSkills->each->delete();
            }

            AuditLog::create([
                'user_id' => $request->user()->id,
                'action' => 'portfolio.published',
                'subject_type' => 'portfolio',
                'ip_address' => $request->ip(),
                'details' => ['sections' => count($validated['sections'])],
            ]);
        });

        return redirect()
            ->route('admin.site-editor.edit')
            ->with('status', 'Portfolio changes published successfully.');
    }
}
