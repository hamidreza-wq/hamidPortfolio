<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\PublicPortfolioController;
use App\Models\AuditLog;
use App\Models\Project;
use App\Services\ContentHistory;
use App\Services\MediaLibrary;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ProjectController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) ($request->validate(['q' => ['nullable', 'string', 'max:100']])['q'] ?? ''));
        $like = '%'.addcslashes($search, '\\%_').'%';

        return view('admin.projects', [
            'projects' => Project::query()
                ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                    ->where('name', 'like', $like)
                    ->orWhere('slug', 'like', $like)
                    ->orWhere('category_label', 'like', $like)
                    ->orWhere('description', 'like', $like)
                    ->orWhere('technologies', 'like', $like)))
                ->orderBy('sort_order')
                ->paginate(10)
                ->withQueryString(),
            'search' => $search,
            'previewPayload' => app(PublicPortfolioController::class)
                ->previewPayloadForAdmin(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validatedProject($request);
        $project = DB::transaction(function () use ($validated, $request): Project {
            $project = Project::create($this->projectAttributes($validated, $request));
            app(ContentHistory::class)->project($project, 'Project created: '.$project->name, $request->user()->id);
            $this->audit($request, 'project.created', $project);

            return $project;
        });

        return redirect()->route('admin.projects.index')->with('status', "{$project->name} added.");
    }

    public function update(Request $request, Project $project)
    {
        $validated = $this->validatedProject($request, $project);

        DB::transaction(function () use ($validated, $project, $request): void {
            $project = Project::query()->lockForUpdate()->findOrFail($project->id);
            app(ContentHistory::class)->project($project, 'Before project update: '.$project->name, $request->user()->id);
            $attributes = $this->projectAttributes($validated, $request, $project);
            if ($attributes['slug'] !== $project->slug) {
                DB::table('project_slug_redirects')->where('project_id', $project->id)->where('slug', $attributes['slug'])->delete();
                DB::table('project_slug_redirects')->updateOrInsert(['slug' => $project->slug], ['project_id' => $project->id]);
            }
            $project->update($attributes);
            $this->audit($request, 'project.updated', $project);
        });

        return redirect()->route('admin.projects.index')->with('status', "{$project->name} saved.");
    }

    public function destroy(Request $request, Project $project)
    {
        DB::transaction(function () use ($project, $request): void {
            $project = Project::query()->lockForUpdate()->findOrFail($project->id);
            app(ContentHistory::class)->project($project, 'Before project deletion: '.$project->name, $request->user()->id);
            $this->audit($request, 'project.deleted', $project);
            $project->delete();
        });

        return redirect()->route('admin.projects.index')->with('status', 'Project removed.');
    }

    private function validatedProject(Request $request, ?Project $project = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:160', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('projects', 'slug')->ignore($project?->id), Rule::unique('project_slug_redirects', 'slug')->ignore($project?->id, 'project_id')],
            'category' => ['required', 'in:web,tools,mobile,other'],
            'category_label' => ['required', 'string', 'max:80'],
            'repository_url' => ['required', 'url', 'max:255', 'regex:/^https:\/\/github\.com\//i'],
            'live_url' => ['nullable', 'url:http,https', 'max:255'],
            'media_asset_id' => app(MediaLibrary::class)->selectionRules(),
            'gallery_media_ids' => ['nullable', 'array', 'max:12'],
            'gallery_media_ids.*' => [...app(MediaLibrary::class)->selectionRules(), 'required', 'distinct'],
            'description' => ['required', 'string', 'max:500'],
            'details' => ['required', 'string', 'max:3000'],
            'technologies' => ['required', 'string', 'max:1000'],
            'features' => ['required', 'string', 'max:2000'],
            'visual' => ['required', 'regex:/^[a-z0-9-]{1,40}$/'],
            'visual_label' => ['required', 'string', 'max:100'],
            'status' => ['required', 'string', 'max:40'],
            'is_featured' => ['required', 'boolean'],
            'is_published' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'between:0,1000'],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string', 'max:320'],
        ]);
    }

    private function projectAttributes(array $validated, Request $request, ?Project $project = null): array
    {
        $name = $validated['name'];
        $slug = $validated['slug'] ?? $project?->slug ?? (Str::slug($name) ?: 'project');
        $existing = Project::where('slug', $slug)->when($project, fn ($query) => $query->whereKeyNot($project->id))->exists()
            || DB::table('project_slug_redirects')->where('slug', $slug)->when($project, fn ($query) => $query->where('project_id', '!=', $project->id))->exists();
        if ($existing && empty($validated['slug'])) {
            $slug .= '-'.Str::lower(Str::random(5));
        }

        return [
            ...$validated,
            'slug' => $slug,
            'gallery_media_ids' => array_map('intval', $validated['gallery_media_ids'] ?? []),
            'technologies' => collect(preg_split('/\R/', $validated['technologies']) ?: [])
                ->map(fn (string $value) => trim($value))->filter()->unique()->values()->all(),
            'features' => collect(preg_split('/\R/', $validated['features']) ?: [])
                ->map(fn (string $value) => trim($value))->filter()->values()->all(),
            'created_by' => $project?->created_by ?? $request->user()->id,
        ];
    }

    private function audit(Request $request, string $action, Project $project): void
    {
        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => $action,
            'subject_type' => 'project',
            'subject_id' => $project->id,
            'ip_address' => $request->ip(),
            'details' => ['name' => $project->name],
        ]);
    }
}
