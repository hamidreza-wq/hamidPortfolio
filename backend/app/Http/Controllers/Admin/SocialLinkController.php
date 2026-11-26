<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\SocialLink;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SocialLinkController extends Controller
{
    private const MAX_LINKS = 20;

    public function index()
    {
        return view('admin.social', [
            'links' => SocialLink::orderBy('sort_order')->orderBy('id')->get(),
            'platforms' => SocialLink::PLATFORMS,
            'max' => self::MAX_LINKS,
        ]);
    }

    public function store(Request $request)
    {
        if (SocialLink::count() >= self::MAX_LINKS) {
            return back()->withErrors(['url' => 'Up to '.self::MAX_LINKS.' social links are supported.']);
        }
        $link = SocialLink::create($this->validated($request) + ['sort_order' => (int) SocialLink::max('sort_order') + 10]);
        $this->audit($request, 'social.created', $link);

        return back()->with('status', $link->displayLabel().' added.');
    }

    public function update(Request $request, SocialLink $socialLink)
    {
        $socialLink->update($this->validated($request));
        $this->audit($request, 'social.updated', $socialLink);

        return back()->with('status', $socialLink->displayLabel().' saved.');
    }

    public function move(Request $request, SocialLink $socialLink)
    {
        $direction = $request->validate(['direction' => ['required', 'in:up,down']])['direction'];
        DB::transaction(function () use ($socialLink, $direction) {
            $links = SocialLink::orderBy('sort_order')->orderBy('id')->lockForUpdate()->get()->values();
            $index = $links->search(fn ($link) => $link->id === $socialLink->id);
            $swap = $direction === 'up' ? $index - 1 : $index + 1;
            if ($swap < 0 || $swap >= $links->count()) {
                return;
            }
            $ordered = $links->all();
            [$ordered[$index], $ordered[$swap]] = [$ordered[$swap], $ordered[$index]];
            foreach ($ordered as $position => $link) {
                $link->forceFill(['sort_order' => ($position + 1) * 10])->save();
            }
        });

        return back();
    }

    public function destroy(Request $request, SocialLink $socialLink)
    {
        $this->audit($request, 'social.deleted', $socialLink);
        $socialLink->delete();

        return back()->with('status', $socialLink->displayLabel().' removed.');
    }

    private function validated(Request $request): array
    {
        $data = validator($request->all(), [
            'platform' => ['required', Rule::in(array_keys(SocialLink::PLATFORMS))],
            'label' => ['nullable', 'string', 'max:60'],
            'url' => ['required', 'url:https', 'max:500'],
        ])->after(function (Validator $validator) use ($request) {
            $platform = (string) $request->input('platform');
            if (! $validator->errors()->has('url') && isset(SocialLink::PLATFORMS[$platform])
                && ! SocialLink::urlMatchesPlatform((string) $request->input('url'), $platform)) {
                $hosts = SocialLink::PLATFORMS[$platform][1];
                $validator->errors()->add('url', 'That address is not a '.SocialLink::PLATFORMS[$platform][0].' address'
                    .($hosts ? ' (expected '.implode(' or ', $hosts).')' : '').'.');
            }
        })->validate();

        return [
            'platform' => $data['platform'],
            'label' => $data['label'] ?? null,
            'url' => $data['url'],
            'is_enabled' => $request->boolean('is_enabled'),
            'show_in_header' => $request->boolean('show_in_header'),
            'show_in_footer' => $request->boolean('show_in_footer'),
            'show_in_contact' => $request->boolean('show_in_contact'),
        ];
    }

    private function audit(Request $request, string $action, SocialLink $link): void
    {
        AuditLog::create([
            'user_id' => $request->user()->id, 'action' => $action,
            'subject_type' => SocialLink::class, 'subject_id' => $link->id,
            'details' => ['name' => $link->displayLabel()],
        ]);
    }
}
