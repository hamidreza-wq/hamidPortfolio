<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\SelfTest\SelfTest;
use Illuminate\Http\Request;

class SelfTestController extends Controller
{
    public const STATUSES = ['fail' => 'Failed', 'warn' => 'Warnings', 'skip' => 'Not tested', 'pass' => 'Passed'];

    public function index(Request $request, SelfTest $selfTest)
    {
        $show = $request->validate(['show' => ['nullable', 'in:all,'.implode(',', array_keys(self::STATUSES))]])['show'] ?? 'problems';
        $report = $selfTest->lastReport();
        $results = collect($report['results'] ?? [])
            ->when($show === 'problems', fn ($r) => $r->whereIn('status', ['fail', 'warn', 'skip']))
            ->when(isset(self::STATUSES[$show]), fn ($r) => $r->where('status', $show))
            ->sortBy(fn ($r) => array_search($r['status'], array_keys(self::STATUSES)).($r['severity'] === 'critical' ? 0 : ($r['severity'] === 'high' ? 1 : 2)))
            ->values();

        return view('admin.self-test', [
            'report' => $report,
            'results' => $results,
            'show' => $show,
            'statuses' => self::STATUSES,
            'ranBy' => isset($report['user_id']) ? User::find($report['user_id'])?->name : null,
        ]);
    }

    public function run(Request $request, SelfTest $selfTest)
    {
        set_time_limit(180);
        $report = $selfTest->run($request->user()->id);
        AuditLog::create(['user_id' => $request->user()->id, 'action' => 'self-test.ran', 'details' => $report['counts']]);

        return redirect()->route('admin.self-test')->with('status',
            "Self-test finished in {$report['seconds']} s: {$report['counts']['fail']} failed, {$report['counts']['warn']} warning(s), {$report['counts']['pass']} passed.");
    }
}
