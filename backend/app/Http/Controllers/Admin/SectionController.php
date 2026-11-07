<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\PublicPortfolioController;
use App\Models\AuditLog;
use App\Models\PortfolioSection;
use App\Models\PortfolioSetting;
use App\Services\ContentHistory;
use App\Services\MediaLibrary;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SectionController extends Controller
{
    public function index()
    {
        return view('admin.sections', [
            'sections' => PortfolioSection::query()->orderBy('sort_order')->orderBy('id')->get(),
            'previewPayload' => app(PublicPortfolioController::class)->previewPayloadForAdmin(),
        ]);
    }

    public function store(Request $request)
    {
        $values = $this->validated($request);
        DB::transaction(function () use ($values, $request) {
            PortfolioSetting::query()->lockForUpdate()->findOrFail(1);
            abort_if(PortfolioSection::query()->count() >= 36, 422, 'A maximum of 30 additional sections is supported.');
            app(ContentHistory::class)->site('Before adding section', $request->user()->id);
            $key = 'section-'.Str::lower((string) Str::ulid());
            $section = PortfolioSection::create([...$values, 'key' => $key]);
            $this->audit($request, $section, 'section.created');
        });

        return redirect()->route('admin.sections.index')->with('status', 'Section saved. Only visible sections appear publicly.');
    }

    public function update(Request $request, PortfolioSection $portfolioSection)
    {
        abort_if(in_array($portfolioSection->key, PortfolioSection::BUILT_IN_KEYS, true), 422, 'Edit built-in sections in the site editor.');
        $values = $this->validated($request);
        DB::transaction(function () use ($values, $portfolioSection, $request) {
            PortfolioSetting::query()->lockForUpdate()->findOrFail(1);
            app(ContentHistory::class)->site('Before updating section: '.$portfolioSection->label, $request->user()->id);
            $portfolioSection->update($values);
            $this->audit($request, $portfolioSection, 'section.updated');
        });

        return redirect()->route('admin.sections.index')->with('status', 'Section saved.');
    }

    public function destroy(Request $request, PortfolioSection $portfolioSection)
    {
        abort_if(in_array($portfolioSection->key, PortfolioSection::BUILT_IN_KEYS, true), 422, 'Built-in sections can be hidden, not deleted.');
        DB::transaction(function () use ($portfolioSection, $request) {
            PortfolioSetting::query()->lockForUpdate()->findOrFail(1);
            app(ContentHistory::class)->site('Before deleting section: '.$portfolioSection->label, $request->user()->id);
            $this->audit($request, $portfolioSection, 'section.deleted');
            $portfolioSection->delete();
        });

        return redirect()->route('admin.sections.index')->with('status', 'Section removed. Media and history are preserved.');
    }

    private function validated(Request $request): array
    {
        try {
            $items = json_decode($request->input('items_json', '[]'), true, 8, JSON_THROW_ON_ERROR);
        } catch (\JsonException|\TypeError) {
            throw ValidationException::withMessages(['items_json' => 'Enter a valid JSON array of plain-text items.']);
        }
        if (! is_array($items) || ! array_is_list($items)) {
            throw ValidationException::withMessages(['items_json' => 'Items must be a JSON array, not an object or scalar.']);
        }
        $request->merge(['items' => $items]);

        return $request->validate([
            'type' => ['required', Rule::in(PortfolioSection::CUSTOM_TYPES)],
            'label' => ['required', 'string', 'max:60'],
            'title' => ['required', 'string', 'max:180'],
            'subtitle' => ['nullable', 'string', 'max:180'],
            'body' => ['nullable', 'string', 'max:3000'],
            'sort_order' => ['required', 'integer', 'between:0,100'],
            'is_visible' => ['required', 'boolean'],
            'show_in_navigation' => ['required', 'boolean'],
            'spacing_top' => ['required', 'integer', 'between:0,240'],
            'spacing_bottom' => ['required', 'integer', 'between:0,240'],
            'background_effect' => ['required', 'in:cosmic,none'],
            'image_media_id' => app(MediaLibrary::class)->selectionRules(),
            'background_media_id' => app(MediaLibrary::class)->selectionRules(),
            'items' => ['present', 'array', 'max:20'],
            'items.*' => ['required', 'array:title,subtitle,body'],
            'items.*.title' => ['required', 'string', 'max:180'],
            'items.*.subtitle' => ['nullable', 'string', 'max:180'],
            'items.*.body' => ['nullable', 'string', 'max:2000'],
        ]);
    }

    private function audit(Request $request, PortfolioSection $section, string $action): void
    {
        AuditLog::create([
            'user_id' => $request->user()->id,
            'action' => $action,
            'subject_type' => 'section',
            'subject_id' => $section->id,
            'ip_address' => $request->ip(),
            'details' => ['key' => $section->key],
        ]);
    }
}
