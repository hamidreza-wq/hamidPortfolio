<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\PublicPortfolioController;
use App\Models\AuditLog;
use App\Models\GithubRepository;
use App\Models\Project;
use App\Services\ContentHistory;
use App\Services\MediaLibrary;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

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
            'repository_is_public' => ['sometimes', 'boolean'],
            'live_url_verified' => ['sometimes', 'boolean'],
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
            'author_name' => ['nullable', 'string', 'max:120'],
            'maintainer_name' => ['nullable', 'string', 'max:120'],
            'license_name' => ['nullable', 'string', 'max:160'],
            'attribution' => ['nullable', 'string', 'max:3000'],
            'copyright_holder' => ['nullable', 'string', 'max:120'],
            'rights_confirmed' => ['sometimes', 'boolean'],
        ]);
    }

    private function projectAttributes(array $validated, Request $request, ?Project $project = null): array
    {
        if ($project && $validated['is_published'] && GithubRepository::where('project_id', $project->id)
            ->where(fn ($query) => $query->whereNull('publication_approved_at')
                ->orWhereNotIn('state', ['approved', 'linked', 'changes']))->exists()) {
            throw ValidationException::withMessages(['is_published' => 'Review and approve this GitHub candidate before publishing.']);
        }
        $confirmed = array_key_exists('rights_confirmed', $validated)
            ? (bool) $validated['rights_confirmed'] : (bool) $project?->rights_confirmed_at;
        if (! $confirmed && (! empty($validated['author_name']) || ! empty($validated['copyright_holder']))) {
            throw ValidationException::withMessages(['rights_confirmed' => 'Confirm authorship/ownership and preserve contributor and licence rights before making an author or copyright claim.']);
        }
        unset($validated['rights_confirmed']);
        if (! $confirmed) {
            $validated['author_name'] = null;
            $validated['copyright_holder'] = null;
        }
        $name = $validated['name'];
        $slug = $validated['slug'] ?? $project?->slug ?? (Str::slug($name) ?: 'project');
        $existing = Project::where('slug', $slug)->when($project, fn ($query) => $query->whereKeyNot($project->id))->exists()
            || DB::table('project_slug_redirects')->where('slug', $slug)->when($project, fn ($query) => $query->where('project_id', '!=', $project->id))->exists();
        if ($existing && empty($validated['slug'])) {
            $slug .= '-'.Str::lower(Str::random(5));
        }

        return [
            ...$validated,
            'repository_is_public' => (bool) ($validated['repository_is_public'] ?? ($project?->repository_url === $validated['repository_url'] && $project?->repository_is_public)),
            'live_url_verified' => ! empty($validated['live_url']) && (bool) ($validated['live_url_verified'] ?? ($project?->live_url === $validated['live_url'] && $project?->live_url_verified)),
            'rights_confirmed_at' => $confirmed ? ($project?->rights_confirmed_at ?? now()) : null,
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
