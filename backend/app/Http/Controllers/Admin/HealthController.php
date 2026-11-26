<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Health\HealthMonitor;

class HealthController extends Controller
{
    public function index(HealthMonitor $health)
    {
        $site = $health->cachedSite();
        $groups = $health->local() + $site['groups'];

        return view('admin.health', [
            'groups' => $groups,
            'overall' => HealthMonitor::overall($groups),
            'site' => $site,
        ]);
    }

    /** Re-runs the public-site checks now instead of waiting for the hourly run. */
    public function check(HealthMonitor $health)
    {
        $site = $health->checkSite();

        return back()->with('status', $site['error'] ? 'Site checks could not finish: '.$site['error'] : 'Public site checked just now.');
    }
}
