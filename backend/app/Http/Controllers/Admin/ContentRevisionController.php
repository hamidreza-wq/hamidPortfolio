<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\ContentRevision;
use App\Models\PortfolioSection;
use App\Models\PortfolioSetting;
use App\Models\Project;
use App\Models\Skill;
use App\Models\SkillCategory;
use App\Models\User;
use App\Services\ContentHistory;
use App\Services\FontLibrary;
use App\Services\MediaLibrary;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ContentRevisionController extends Controller
{
    public function index()
    {
        return view('admin.revisions', ['revisions' => ContentRevision::query()->latest('id')->paginate(20)]);
    }

    public function restore(Request $request, ContentRevision $contentRevision)
    {
        $request->validate(['confirm' => ['accepted']]);
        DB::transaction(function () use ($request, $contentRevision) {
            PortfolioSetting::query()->lockForUpdate()->findOrFail(1);
            $snapshot = $contentRevision->snapshot;
            if ($contentRevision->scope === 'project') {
                $project = Project::query()->lockForUpdate()->find($contentRevision->subject_id);
                if ($project) {
                    app(ContentHistory::class)->project($project, 'Before restoring project revision', $request->user()->id);
                }
                Validator::make($snapshot, [
                    'slug' => [Rule::unique('projects')->ignore($contentRevision->subject_id), Rule::unique('project_slug_redirects', 'slug')->ignore($contentRevision->subject_id, 'project_id')],
                    'media_asset_id' => app(MediaLibrary::class)->selectionRules(),
                    'gallery_media_ids.*' => [...app(MediaLibrary::class)->selectionRules(), 'required'],
                ])->validate();
                if (! empty($snapshot['created_by']) && ! User::query()->whereKey($snapshot['created_by'])->exists()) {
                    $snapshot['created_by'] = null;
                }
                $project ??= new Project;
                DB::table('project_slug_redirects')->where('project_id', $contentRevision->subject_id)->where('slug', $snapshot['slug'])->delete();
                if ($project->exists && $project->slug !== $snapshot['slug']) {
                    DB::table('project_slug_redirects')->updateOrInsert(['slug' => $project->slug], ['project_id' => $project->id]);
                }
                $project->id = $contentRevision->subject_id;
                $project->fill($snapshot)->save();
            } else {
                abort_unless($contentRevision->scope === 'site', 422);
                $settings = $snapshot['settings'];
                app(FontLibrary::class)->validateSelection($settings['font_body'], 'font_body');
                app(FontLibrary::class)->validateSelection($settings['font_heading'], 'font_heading');
                Validator::make($settings, ['og_image_media_id' => app(MediaLibrary::class)->selectionRules()])->validate();
                foreach ($snapshot['sections'] as $section) {
                    Validator::make($section, [
                        'image_media_id' => app(MediaLibrary::class)->selectionRules(),
                        'background_media_id' => app(MediaLibrary::class)->selectionRules(),
                    ])->validate();
                }
                app(ContentHistory::class)->site('Before restoring site revision', $request->user()->id);
                PortfolioSetting::query()->findOrFail(1)->fill($settings)->save();
                PortfolioSection::query()->whereNotIn('key', array_column($snapshot['sections'], 'key'))->delete();
                foreach ($snapshot['sections'] as $section) {
                    PortfolioSection::query()->updateOrCreate(['key' => $section['key']], collect($section)->except(['id', 'created_at', 'updated_at'])->all());
                }
                SkillCategory::query()->whereNotIn('id', array_column($snapshot['categories'], 'id'))->delete();
                foreach ($snapshot['categories'] as $category) {
                    $model = SkillCategory::query()->find($category['id']) ?? new SkillCategory;
                    $model->id = $category['id'];
                    $model->fill($category)->save();
                    $model->skills()->delete();
                    foreach ($category['skills'] as $skill) {
                        Skill::create([...collect($skill)->except(['id', 'created_at', 'updated_at'])->all(), 'skill_category_id' => $model->id]);
                    }
                }
            }
            AuditLog::create([
                'user_id' => $request->user()->id,
                'action' => 'content.restored',
                'subject_type' => 'revision',
                'subject_id' => $contentRevision->id,
                'ip_address' => $request->ip(),
                'details' => ['scope' => $contentRevision->scope],
            ]);
        });

        return redirect()->route('admin.revisions.index')->with('status', 'Revision restored and published. The replaced content remains in history.');
    }
}
