<?php

namespace App\Services\Platform;

use App\Models\GithubRepository;
use App\Models\Project;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GithubDiscovery
{
    public function scan(): array
    {
        $api = app(GithubApi::class);
        $identity = $api->json('/users/hamidreza-wq');
        if (($identity['login'] ?? '') !== 'hamidreza-wq' || ! is_int($identity['id'] ?? null)) {
            throw new IntegrationFailure('GitHub returned an unexpected account identity.');
        }
        $repos = [];
        $started = microtime(true);
        for ($page = 1; $page <= 10; $page++) {
            if (microtime(true) - $started > 100) {
                throw new IntegrationFailure('Repository scan exceeded its time budget. No partial import was applied.');
            }
            $batch = $api->json('/users/hamidreza-wq/repos', ['type' => 'owner', 'sort' => 'full_name', 'per_page' => 100, 'page' => $page]);
            if (! array_is_list($batch) || count($batch) > 100) {
                throw new IntegrationFailure('GitHub repository page is malformed.');
            }
            foreach ($batch as $repo) {
                $data = $this->metadata($repo, $identity['id']);
                $repos[$data['id']] = $data;
            }
            if (count($batch) < 100) {
                break;
            }
            if ($page === 10) {
                throw new IntegrationFailure('Repository scan exceeded 1000 entries. No partial import was applied.');
            }
        }
        $new = 0;
        DB::transaction(function () use ($repos, &$new): void {
            foreach ($repos as $id => $data) {
                $row = GithubRepository::query()->lockForUpdate()->find($id);
                $eligible = ! $data['fork'] && ! $data['archived'];
                if (! $row) {
                    $existing = Project::whereRaw('LOWER(repository_url) = ?', [strtolower($data['url'])])->first();
                    $row = new GithubRepository(['id' => $id, 'state' => $eligible ? ($existing ? 'linked' : 'pending') : 'excluded']);
                    $row->project_id = $existing?->id;
                    $row->publication_approved_at = $existing ? now() : null;
                    if ($eligible && ! $existing) {
                        $row->project_id = $this->draft($data)->id;
                        $new++;
                    }
                } elseif ($row->metadata !== $data) {
                    $row->previous_metadata = $row->metadata;
                    if (in_array($row->state, ['approved', 'linked'], true)) {
                        $row->state = 'changes';
                    }
                }
                if ($row->state === 'unavailable') {
                    $row->state = $eligible ? 'changes' : 'excluded';
                }
                // Discovery never mutates an existing project's editorial content.
                $row->fill(['full_name' => $data['full_name'], 'metadata' => $data, 'last_seen_at' => now()])->save();
            }
            GithubRepository::whereNotIn('id', array_keys($repos))->whereNotIn('state', ['unavailable', 'rejected', 'excluded'])->update(['state' => 'unavailable']);
        });
        $this->notifyReview();

        return ['message' => 'Public repository scan completed. Existing editorial content was preserved.', 'discovered' => count($repos), 'new_drafts' => $new];
    }

    public function notifyReview(): void
    {
        $count = GithubRepository::whereIn('state', ['pending', 'changes', 'unavailable'])->count();
        if ($count > 0) {
            app(Notices::class)->put('github:review', 'github', 'medium', 'New GitHub drafts need review',
                $count.' new drafts or changed/unavailable repository sources await review. No project content was overwritten or published.', 'admin.platform.github');
        } else {
            app(Notices::class)->resolve('github:review');
        }
    }

    private function metadata(mixed $repo, int $ownerId): array
    {
        if (! is_array($repo) || ! is_int($repo['id'] ?? null) || $repo['id'] < 1
            || ($repo['owner']['id'] ?? null) !== $ownerId || ($repo['owner']['login'] ?? '') !== 'hamidreza-wq'
            || ! is_string($repo['name'] ?? null) || ! preg_match('/^[a-zA-Z0-9_.-]{1,100}$/D', $repo['name'])
            || ($repo['full_name'] ?? '') !== 'hamidreza-wq/'.$repo['name']
            || ($repo['html_url'] ?? '') !== 'https://github.com/hamidreza-wq/'.$repo['name']
            || ($repo['private'] ?? true) !== false
            || (isset($repo['description']) && ! is_string($repo['description']))
            || (isset($repo['language']) && ! is_string($repo['language']))
            || (isset($repo['license']) && ! is_array($repo['license']))
            || ! is_bool($repo['fork'] ?? null) || ! is_bool($repo['archived'] ?? null)) {
            throw new IntegrationFailure('Repository ownership, visibility or metadata is invalid.');
        }
        $license = $repo['license']['spdx_id'] ?? null;

        return [
            'id' => $repo['id'], 'name' => $repo['name'], 'full_name' => $repo['full_name'],
            'url' => $repo['html_url'], 'description' => Str::limit(strip_tags((string) ($repo['description'] ?? '')), 500, ''),
            'language' => Str::limit(strip_tags((string) ($repo['language'] ?? '')), 80, ''),
            'license' => is_string($license) ? Str::limit($license, 100, '') : null,
            'fork' => $repo['fork'], 'archived' => $repo['archived'],
            'owner_login' => 'hamidreza-wq', 'owner_id' => $ownerId,
        ];
    }

    private function draft(array $data): Project
    {
        $slug = (Str::slug($data['name']) ?: 'github-project').'-gh-'.$data['id'];
        while (Project::where('slug', $slug)->exists() || DB::table('project_slug_redirects')->where('slug', $slug)->exists()) {
            $slug = 'github-'.$data['id'].'-'.Str::lower(Str::random(8));
        }

        return Project::create([
            'name' => $data['name'], 'slug' => $slug, 'category' => 'other',
            'category_label' => 'GitHub project', 'repository_url' => $data['url'],
            'repository_is_public' => true,
            'description' => $data['description'], 'details' => $data['description'],
            'technologies' => $data['language'] ? [$data['language']] : [], 'features' => [],
            'visual' => 'planet', 'visual_label' => 'DRAFT / REVIEW', 'status' => 'draft',
            'is_featured' => false, 'is_published' => false, 'sort_order' => 1000,
            'license_name' => $data['license'],
        ]);
    }
}
