<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\PublicPageController;
use App\Models\AuditLog;
use App\Models\MenuItem;
use App\Models\Page;
use App\Services\ContentHistory;
use App\Services\MediaLibrary;
use App\Services\MenuBuilder;
use App\Services\PageContent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PageController extends Controller
{
    public const VIEWS = ['all' => 'All', 'published' => 'Published', 'scheduled' => 'Scheduled', 'draft' => 'Drafts', 'trash' => 'Trash'];

    public function index(Request $request)
    {
        $filters = $request->validate(['view' => ['nullable', Rule::in(array_keys(self::VIEWS))], 'q' => ['nullable', 'string', 'max:100']]);
        $view = $filters['view'] ?? 'all';
        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $filters['q'] ?? '').'%';
        $pages = Page::query()
            ->when($view === 'trash', fn ($q) => $q->onlyTrashed())
            ->when($view === 'published', fn ($q) => $q->live())
            ->when($view === 'scheduled', fn ($q) => $q->where('status', 'published')->where('publish_at', '>', now()))
            ->when($view === 'draft', fn ($q) => $q->where('status', 'draft'))
            ->when($filters['q'] ?? null, fn ($q) => $q->where(fn ($w) => $w->where('title', 'like', $like)->orWhere('slug', 'like', $like)->orWhere('body', 'like', $like)))
            ->with('editor')->latest('updated_at')->paginate(20)->withQueryString();

        return view('admin.pages.index', [
            'pages' => $pages, 'view' => $view, 'views' => self::VIEWS, 'query' => $filters['q'] ?? '',
            'counts' => [
                'all' => Page::count(), 'published' => Page::live()->count(),
                'scheduled' => Page::where('status', 'published')->where('publish_at', '>', now())->count(),
                'draft' => Page::where('status', 'draft')->count(), 'trash' => Page::onlyTrashed()->count(),
            ],
        ]);
    }

    public function create(MenuBuilder $menus)
    {
        return view('admin.pages.edit', ['page' => new Page(['status' => 'draft']), 'headerCustom' => $menus->isHeaderCustom(), 'menuState' => []]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $page = DB::transaction(function () use ($request, $data) {
            $page = Page::create($data + ['created_by' => $request->user()->id, 'updated_by' => $request->user()->id]);
            $this->syncMenus($request, $page);
            $this->audit($request, 'page.created', $page);

            return $page;
        });

        return redirect()->route('admin.pages.edit', $page)->with('status', $page->isLive() ? 'Page published at '.$page->url().'.' : 'Page saved: '.Str::lower($page->stateLabel()).'.');
    }

    public function edit(Page $page, MenuBuilder $menus)
    {
        return view('admin.pages.edit', [
            'page' => $page,
            'headerCustom' => $menus->isHeaderCustom(),
            'menuState' => MenuItem::where('type', 'custom_page')->where('target', (string) $page->id)->pluck('menu')->all(),
        ]);
    }

    public function update(Request $request, Page $page)
    {
        $data = $this->validated($request, $page);
        DB::transaction(function () use ($request, $page, $data) {
            app(ContentHistory::class)->page($page, 'Before page update: '.$page->title, $request->user()->id);
            $page->update($data + ['updated_by' => $request->user()->id]);
            $this->syncMenus($request, $page);
            $this->audit($request, 'page.updated', $page);
        });

        return back()->with('status', 'Page saved. The previous version is in Content history.');
    }

    public function duplicate(Request $request, Page $page)
    {
        $base = Str::limit($page->slug, 70, '').'-copy';
        $slug = $base;
        for ($i = 2; Page::withTrashed()->where('slug', $slug)->exists(); $i++) {
            $slug = $base.'-'.$i;
        }
        $copy = Page::create(['title' => Str::limit($page->title.' (copy)', 120, ''), 'slug' => $slug, 'status' => 'draft']
            + $page->only(['summary', 'body', 'seo_title', 'seo_description', 'media_asset_id', 'noindex'])
            + ['created_by' => $request->user()->id, 'updated_by' => $request->user()->id]);
        $this->audit($request, 'page.duplicated', $copy);

        return redirect()->route('admin.pages.edit', $copy)->with('status', 'Copy created as a draft.');
    }

    /** Moves a page to the trash. It stops showing at once, and can be restored. */
    public function destroy(Request $request, Page $page)
    {
        app(ContentHistory::class)->page($page, 'Before moving to the trash: '.$page->title, $request->user()->id);
        $page->delete();
        $this->audit($request, 'page.trashed', $page);

        return redirect()->route('admin.pages.index')->with('status', "\"{$page->title}\" moved to the trash. Restore it from the Trash tab.");
    }

    public function restore(Request $request, int $page)
    {
        $page = Page::onlyTrashed()->findOrFail($page);
        if (Page::where('slug', $page->slug)->exists()) {
            return back()->withErrors(['slug' => 'Another page now uses /'.$page->slug.'. Change that page\'s address first.']);
        }
        $page->restore();
        $this->audit($request, 'page.restored', $page);

        return redirect()->route('admin.pages.edit', $page)->with('status', 'Page restored with its previous status.');
    }

    public function forceDestroy(Request $request, int $page)
    {
        $request->validate(['confirm' => ['accepted']]);
        $page = Page::onlyTrashed()->findOrFail($page);
        DB::transaction(function () use ($request, $page) {
            MenuItem::where('type', 'custom_page')->where('target', (string) $page->id)->delete();
            $this->audit($request, 'page.deleted', $page);
            $page->forceDelete();
        });

        return back()->with('status', "\"{$page->title}\" deleted permanently. Earlier versions remain in Content history.");
    }

    /** Admin-only preview, including drafts and scheduled pages. */
    public function preview(Page $page, PageContent $content)
    {
        return app(PublicPageController::class)->render($page, $content, preview: true);
    }

    private function validated(Request $request, ?Page $page = null): array
    {
        $request->merge(['slug' => Str::slug((string) ($request->input('slug') ?: $request->input('title')))]);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'slug' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::notIn(Page::RESERVED_SLUGS),
                Rule::unique('pages', 'slug')->ignore($page?->id)],
            'summary' => ['nullable', 'string', 'max:320'],
            'body' => ['required', 'string', 'max:100000'],
            'status' => ['required', Rule::in(array_keys(Page::STATUSES))],
            'publish_at' => ['nullable', 'date'],
            'seo_title' => ['nullable', 'string', 'max:120'],
            'seo_description' => ['nullable', 'string', 'max:320'],
            'media_asset_id' => app(MediaLibrary::class)->selectionRules(),
        ], ['slug.not_in' => 'That address is used by another part of the site. Choose a different one.']);
        $data['noindex'] = $request->boolean('noindex');
        $data['publish_at'] = $data['status'] === 'published' && ! empty($data['publish_at']) ? $data['publish_at'] : null;

        return $data;
    }

    /** Adds or removes the page from the footer and (custom) header menus. */
    private function syncMenus(Request $request, Page $page): void
    {
        foreach (['header', 'footer'] as $menu) {
            $wanted = $request->boolean('menu_'.$menu);
            $existing = MenuItem::where('menu', $menu)->where('type', 'custom_page')->where('target', (string) $page->id);
            if ($menu === 'header' && ! app(MenuBuilder::class)->isHeaderCustom()) {
                continue;
            }
            if ($wanted && ! $existing->exists()) {
                MenuItem::create(['menu' => $menu, 'type' => 'custom_page', 'target' => (string) $page->id, 'is_enabled' => true,
                    'sort_order' => (int) MenuItem::where('menu', $menu)->max('sort_order') + 10]);
            } elseif (! $wanted && $request->has('menus_present')) {
                $existing->delete();
            }
        }
    }

    private function audit(Request $request, string $action, Page $page): void
    {
        AuditLog::create(['user_id' => $request->user()->id, 'action' => $action, 'subject_type' => Page::class,
            'subject_id' => $page->id, 'details' => ['name' => $page->title]]);
    }
}
