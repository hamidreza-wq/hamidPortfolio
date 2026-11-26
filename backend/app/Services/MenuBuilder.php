<?php

namespace App\Services;

use App\Models\MenuItem;
use App\Models\PortfolioSection;
use App\Models\PortfolioSetting;
use App\Models\Project;
use Illuminate\Support\Collection;

/**
 * Turns stored menu items into links. Items whose target is hidden, deleted or
 * unpublished are left out, so a menu never shows a broken link.
 */
class MenuBuilder
{
    public function isHeaderCustom(): bool
    {
        return MenuItem::where('menu', 'header')->exists();
    }

    /** @return list<array{label: string, href: string, new_tab: bool, button: bool}> */
    public function links(string $menu): array
    {
        $items = MenuItem::where('menu', $menu)->where('is_enabled', true)->orderBy('sort_order')->orderBy('id')->get();
        if ($items->isEmpty()) {
            return [];
        }
        $sections = PortfolioSection::where('is_visible', true)->get()->keyBy('key');
        $projects = Project::where('is_published', true)->whereIn('id', $items->where('type', 'project')->pluck('target'))->get()->keyBy('id');
        $testimonialsOpen = (bool) PortfolioSetting::find(1)?->testimonial_submissions_open;

        return $items->map(fn (MenuItem $item) => $this->resolve($item, $sections, $projects, $testimonialsOpen))
            ->filter()->values()->all();
    }

    /** What the header menu shows today when it is automatic, as items to start a custom menu from. */
    public function automaticHeaderItems(): array
    {
        return PortfolioSection::where('is_visible', true)->where('show_in_navigation', true)
            ->orderBy('sort_order')->orderBy('id')->get()
            ->map(fn (PortfolioSection $section) => ['type' => 'section', 'target' => $section->key, 'label' => null])
            ->all();
    }

    /** A short description of where an item points, for the admin list. */
    public function describe(MenuItem $item): string
    {
        return match ($item->type) {
            'section' => 'Section: '.(PortfolioSection::where('key', $item->target)->value('title') ?? $item->target.' (deleted)'),
            'project' => 'Project: '.(Project::find($item->target)?->name ?? 'deleted project'),
            'page' => 'Page: '.(MenuItem::PAGES[$item->target] ?? $item->target),
            default => $item->target,
        };
    }

    /** Why an enabled item is not currently shown, or null when it is. */
    public function hiddenReason(MenuItem $item): ?string
    {
        return match ($item->type) {
            'section' => PortfolioSection::where('key', $item->target)->value('is_visible') ? null : 'Hidden: that section is not visible',
            'project' => Project::where('id', $item->target)->where('is_published', true)->exists() ? null : 'Hidden: that project is not published',
            'page' => $item->target === 'testimonials' && ! PortfolioSetting::find(1)?->testimonial_submissions_open ? 'Hidden: the testimonial form is closed' : null,
            default => null,
        };
    }

    private function resolve(MenuItem $item, Collection $sections, Collection $projects, bool $testimonialsOpen): ?array
    {
        [$href, $label] = match ($item->type) {
            'section' => ($section = $sections->get($item->target))
                ? ['#'.$section->key, $section->label ?: $section->title] : [null, null],
            'project' => ($project = $projects->get((int) $item->target))
                ? ['/projects/'.$project->slug, $project->name] : [null, null],
            'page' => match ($item->target) {
                'home' => ['#home', 'Home'],
                'privacy' => ['/privacy', 'Privacy'],
                'testimonials' => $testimonialsOpen ? ['/testimonials/new', 'Leave a testimonial'] : [null, null],
                default => [null, null],
            },
            'url' => MenuItem::isAllowedUrl($item->target) ? [$item->target, null] : [null, null],
            default => [null, null],
        };
        $label = $item->label ?: $label;
        if (! $href || ! $label) {
            return null;
        }

        return ['label' => $label, 'href' => $href, 'new_tab' => $item->open_in_new_tab, 'button' => $item->is_button && $item->menu === 'header'];
    }
}
