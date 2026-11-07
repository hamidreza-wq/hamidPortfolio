<?php

namespace App\Services;

use App\Models\ContentRevision;
use App\Models\PortfolioSection;
use App\Models\PortfolioSetting;
use App\Models\Project;
use App\Models\SkillCategory;

class ContentHistory
{
    public function site(string $label, ?int $userId): ContentRevision
    {
        return ContentRevision::create([
            'scope' => 'site',
            'label' => $label,
            'user_id' => $userId,
            'snapshot' => [
                'settings' => PortfolioSetting::query()->lockForUpdate()->findOrFail(1)->toArray(),
                'sections' => PortfolioSection::query()->lockForUpdate()->orderBy('id')->get()->toArray(),
                'categories' => SkillCategory::query()->lockForUpdate()->with('skills')->get()->toArray(),
            ],
        ]);
    }

    public function project(Project $project, string $label, ?int $userId): ContentRevision
    {
        return ContentRevision::create([
            'scope' => 'project',
            'subject_id' => $project->id,
            'label' => $label,
            'snapshot' => $project->fresh()->toArray(),
            'user_id' => $userId,
        ]);
    }
}
