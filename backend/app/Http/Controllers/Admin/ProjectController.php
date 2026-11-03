<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProjectController extends Controller
{
    public function index()
    {
        return view('admin.projects', [
            'projects' => Project::orderBy('sort_order')->paginate(10),
            'previewPayload' => app(\App\Http\Controllers\PublicPortfolioController::class)
                ->previewPayloadForAdmin(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validatedProject($request);
        $project = DB::transaction(function () use ($validated, $request): Project {
            $project = Project::create($this->projectAttributes($validated, $request));
            $this->audit($request, 'project.created', $project);

            return $project;
        });

        return redirect()->route('admin.projects.index')->with('status', "{$project->name} added.");
    }

    public function update(Request $request, Project $project)
    {
        $validated = $this->validatedProject($request, $project);

        DB::transaction(function () use ($validated, $project, $request): void {
            $project->update($this->projectAttributes($validated, $request, $project));
            $this->audit($request, 'project.updated', $project);
        });

        return redirect()->route('admin.projects.index')->with('status', "{$project->name} saved.");
    }

    public function destroy(Request $request, Project $project)
    {
        DB::transaction(function () use ($project, $request): void {
            $this->audit($request, 'project.deleted', $project);
            $project->delete();
        });

        return redirect()->route('admin.projects.index')->with('status', 'Project removed.');
    }

    private function validatedProject(Request $request, ?Project $project = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'category' => ['required', 'in:web,tools,mobile,other'],
            'category_label' => ['required', 'string', 'max:80'],
            'repository_url' => ['required', 'url', 'max:255', 'regex:/^https:\/\/github\.com\//i'],
            'live_url' => ['nullable', 'url', 'max:255'],
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
        $slug = Str::slug($name);
        $existing = Project::where('slug', $slug)->when($project, fn ($query) => $query->whereKeyNot($project->id))->exists();
        if ($existing) {
            $slug .= '-'.Str::lower(Str::random(5));
        }

        return [
            ...$validated,
            'slug' => $project && $project->name === $name ? $project->slug : $slug,
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
