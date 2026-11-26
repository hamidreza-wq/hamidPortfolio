<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\Backups\BackupManager;
use Illuminate\Http\Request;
use Throwable;

class BackupController extends Controller
{
    public function index(BackupManager $backups)
    {
        $list = $backups->list();

        return view('admin.backups', [
            'backups' => $list,
            'latest' => $backups->latestVerified(),
            'totalBytes' => array_sum(array_column($list, 'bytes')),
            'keep' => config('portfolio.backups.keep'),
            'schedule' => config('portfolio.backups.schedule'),
            'freeBytes' => @disk_free_space($backups->directory()) ?: null,
        ]);
    }

    public function store(Request $request, BackupManager $backups)
    {
        try {
            $backup = $backups->create('dashboard', $request->user()->id);
        } catch (Throwable $exception) {
            return back()->withErrors(['backup' => 'Backup failed: '.$exception->getMessage()]);
        }

        return back()->with('status', "Backup created and verified: {$backup['tables']} tables, {$backup['rows']} rows, {$backup['files']} files.");
    }

    public function verify(Request $request, BackupManager $backups, string $name)
    {
        $this->assertBackup($backups, $name);
        $check = $backups->recordVerification($name, $request->user()->id);

        return $check['verified']
            ? back()->with('status', "{$name} verified: every table and file matches its checksum.")
            : back()->withErrors(['backup' => "{$name} failed verification: {$check['error']}"]);
    }

    /** Downloads contain password hashes and private messages, so they need the password. */
    public function download(Request $request, BackupManager $backups, string $name)
    {
        $request->validate(['password' => ['required', 'current_password']]);
        $path = $this->assertBackup($backups, $name);
        AuditLog::create(['user_id' => $request->user()->id, 'action' => 'backup.downloaded', 'ip_address' => $request->ip(), 'details' => ['name' => $name]]);

        return response()->download($path, $name, ['Content-Type' => 'application/zip']);
    }

    public function destroy(Request $request, BackupManager $backups, string $name)
    {
        $request->validate(['password' => ['required', 'current_password']]);
        $this->assertBackup($backups, $name);
        $latest = $backups->latestVerified();
        if ($latest && $latest['name'] === $name) {
            return back()->withErrors(['backup' => 'This is the newest verified backup, so it cannot be deleted. Create a newer one first.']);
        }
        $backups->delete($name, $request->user()->id);

        return back()->with('status', "{$name} deleted.");
    }

    private function assertBackup(BackupManager $backups, string $name): string
    {
        try {
            return $backups->path($name);
        } catch (Throwable) {
            abort(404);
        }
    }
}
