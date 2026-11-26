<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\PortfolioSection;
use App\Models\Project;
use App\Services\MenuBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MenuController extends Controller
{
    private const MAX_ITEMS = 15;

    public function index(MenuBuilder $menus)
    {
        $items = MenuItem::orderBy('sort_order')->orderBy('id')->get()->groupBy('menu');

        return view('admin.menus', [
            'items' => $items,
            'headerCustom' => $menus->isHeaderCustom(),
            'automatic' => collect($menus->automaticHeaderItems())->map(fn ($item) => PortfolioSection::where('key', $item['target'])->first())->filter(),
            'menus' => $menus,
            'sections' => PortfolioSection::orderBy('sort_order')->orderBy('id')->get(),
            'projects' => Project::orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'is_published']),
            'customPages' => Page::orderBy('title')->get(),
            'max' => self::MAX_ITEMS,
        ]);
    }

    /** Copies the automatic header menu into editable items. */
    public function customise(Request $request, MenuBuilder $menus)
    {
        if ($menus->isHeaderCustom()) {
            return back();
        }
        $items = $menus->automaticHeaderItems();
        if (! $items) {
            $items = [['type' => 'page', 'target' => 'home', 'label' => null]];
        }
        DB::transaction(function () use ($items) {
            foreach ($items as $index => $item) {
                MenuItem::create($item + ['menu' => 'header', 'sort_order' => ($index + 1) * 10]);
            }
        });
        $this->audit($request, 'menu.customised', 'header');

        return back()->with('status', 'The header menu is now custom, starting from what it showed before. Sections no longer add themselves to it.');
    }

    /** Deletes the custom header menu, so it is built from sections again. */
    public function automatic(Request $request)
    {
        MenuItem::where('menu', 'header')->delete();
        $this->audit($request, 'menu.automatic', 'header');

        return back()->with('status', 'The header menu is automatic again: sections marked "Show in navigation" appear in it.');
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        if (MenuItem::where('menu', $data['menu'])->count() >= self::MAX_ITEMS) {
            throw ValidationException::withMessages(['label' => 'A menu can hold up to '.self::MAX_ITEMS.' items.']);
        }
        $item = MenuItem::create($data + ['sort_order' => (int) MenuItem::where('menu', $data['menu'])->max('sort_order') + 10]);
        $this->audit($request, 'menu.item_added', $item->menu);

        return back()->with('status', 'Menu item added.');
    }

    public function update(Request $request, MenuItem $menuItem)
    {
        $data = $this->validated($request, $menuItem->menu);
        $menuItem->update($data);
        $this->audit($request, 'menu.item_updated', $menuItem->menu);

        return back()->with('status', 'Menu item saved.');
    }

    public function move(Request $request, MenuItem $menuItem)
    {
        $direction = $request->validate(['direction' => ['required', 'in:up,down']])['direction'];
        DB::transaction(function () use ($menuItem, $direction) {
            $ordered = MenuItem::where('menu', $menuItem->menu)->orderBy('sort_order')->orderBy('id')->lockForUpdate()->get()->all();
            $index = array_search($menuItem->id, array_map(fn ($item) => $item->id, $ordered), true);
            $swap = $direction === 'up' ? $index - 1 : $index + 1;
            if ($index === false || $swap < 0 || $swap >= count($ordered)) {
                return;
            }
            [$ordered[$index], $ordered[$swap]] = [$ordered[$swap], $ordered[$index]];
            foreach ($ordered as $position => $item) {
                $item->forceFill(['sort_order' => ($position + 1) * 10])->save();
            }
        });

        return back();
    }

    public function destroy(Request $request, MenuItem $menuItem)
    {
        $menu = $menuItem->menu;
        if ($menu === 'header' && MenuItem::where('menu', 'header')->count() === 1) {
            return back()->withErrors(['menu' => 'This is the last item in the custom header menu. Use "Go back to automatic" instead, or add another item first.']);
        }
        $menuItem->delete();
        $this->audit($request, 'menu.item_removed', $menu);

        return back()->with('status', 'Menu item removed.');
    }

    private function validated(Request $request, ?string $menu = null): array
    {
        $data = $request->validate([
            'menu' => [$menu ? 'nullable' : 'required', Rule::in(array_keys(MenuItem::MENUS))],
            'type' => ['required', Rule::in(array_keys(MenuItem::TYPES))],
            'target_section' => ['nullable', 'required_if:type,section', Rule::exists('portfolio_sections', 'key')],
            'target_project' => ['nullable', 'required_if:type,project', 'integer', Rule::exists('projects', 'id')],
            'target_page' => ['nullable', 'required_if:type,page', Rule::in(array_keys(MenuItem::PAGES))],
            'target_custom_page' => ['nullable', 'required_if:type,custom_page', 'integer', Rule::exists('pages', 'id')->whereNull('deleted_at')],
            'target_url' => ['nullable', 'required_if:type,url', 'string', 'max:500'],
            'label' => ['nullable', 'required_if:type,url', 'string', 'max:60'],
        ], [
            'label.required_if' => 'Give a link to another website a label.',
        ]);
        $target = (string) $data['target_'.$data['type']];
        if ($data['type'] === 'url' && ! MenuItem::isAllowedUrl($target)) {
            throw ValidationException::withMessages(['target_url' => 'Use a full https:// address, or mailto: followed by an email address.']);
        }
        $menu ??= $data['menu'];
        if ($menu === 'header' && ! MenuItem::where('menu', 'header')->exists()) {
            throw ValidationException::withMessages(['menu' => 'Switch the header menu to custom before adding items to it.']);
        }

        return [
            'menu' => $menu,
            'type' => $data['type'],
            'target' => $target,
            'label' => $data['label'] ?? null,
            'open_in_new_tab' => $request->boolean('open_in_new_tab'),
            'is_button' => $menu === 'header' && $request->boolean('is_button'),
            'is_enabled' => $request->boolean('is_enabled'),
        ];
    }

    private function audit(Request $request, string $action, string $menu): void
    {
        AuditLog::create(['user_id' => $request->user()->id, 'action' => $action, 'details' => ['name' => MenuItem::MENUS[$menu]]]);
    }
}
