<?php

namespace App\Console\Commands;

use App\Models\PlatformRun;
use App\Services\Platform\Notices;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

class AuditPlatformDependencies extends Command
{
    protected $signature = 'platform:audit';

    protected $description = 'Run fixed Composer/npm checks from trusted CLI only; never installs or updates dependencies';

    public function handle(): int
    {
        $lock = Cache::lock('platform:dependencies', 600);
        if (! $lock->get()) {
            $this->error('Dependency audit already running.');

            return self::FAILURE;
        }
        $run = PlatformRun::create(['kind' => 'dependencies', 'status' => 'running']);
        try {
            $results = [];
            foreach ([
                ['composer', 'validate', '--strict', '--no-interaction'],
                ['composer', 'audit', '--format=json', '--no-interaction'],
                ['npm', 'audit', '--json', '--ignore-scripts'],
                ['composer', 'outdated', '--direct', '--format=json', '--no-interaction'],
                ['npm', 'outdated', '--json', '--ignore-scripts'],
            ] as $argv) {
                $process = new Process($argv, base_path(), timeout: 90);
                try {
                    $exit = $process->run();
                    $output = $process->getOutput();
                    $decoded = json_decode($output, true);
                    $results[] = [
                        'command' => implode(' ', $argv), 'exit_code' => $exit,
                        'result' => is_array($decoded) ? $decoded : mb_substr($output.$process->getErrorOutput(), 0, 16000),
                    ];
                    $this->line(implode(' ', $argv).' => exit '.$exit);
                } catch (ProcessTimedOutException) {
                    $results[] = ['command' => implode(' ', $argv), 'exit_code' => -1, 'result' => 'Timed out; audit status unknown.'];
                    $this->error(implode(' ', $argv).' => timed out');
                }
            }
            $ok = collect($results)->every(fn (array $result) => $result['exit_code'] === 0);
            $run->update([
                'status' => $ok ? 'successful' : 'needs_attention',
                'message' => $ok ? 'Dependency checks completed with zero exit codes. Review full advisory/version output; no packages were changed.' : 'One or more audits/version checks failed, found advisories or reported available upgrades. Review each actual exit code; no dependencies were changed.',
                'summary' => $results,
            ]);
            if ($ok) {
                app(Notices::class)->resolve('dependencies');
            } else {
                app(Notices::class)->put('dependencies', 'updates', 'high', 'Dependency checks need review',
                    $run->message, 'admin.platform.updates');
            }

            return $ok ? self::SUCCESS : self::FAILURE;
        } finally {
            if ($run->status === 'running') {
                $run->update(['status' => 'failed', 'message' => 'Audit could not complete. Inspect CLI logs. Do not interpret this as an advisory-free result.']);
            }
            $lock->release();
        }
    }
}
