<?php

namespace Tests\Feature;

use App\Models\ContactMessage;
use App\Models\PlatformNotice;
use App\Models\Project;
use App\Models\User;
use App\Services\Backups\BackupManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class BackupTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'Long-test-password-2026!';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
        $this->seed();
    }

    private function manager(): BackupManager
    {
        return app(BackupManager::class);
    }

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true, 'password' => self::PASSWORD]);
    }

    public function test_backup_contains_every_table_and_upload_and_verifies(): void
    {
        Storage::disk('public')->put('media/cover.webp', 'image-bytes');
        $backup = $this->manager()->create('command');

        $this->assertTrue($backup['verified']);
        $this->assertSame(1, $backup['files']);
        $zip = new ZipArchive;
        $zip->open($this->manager()->path($backup['name']));
        $manifest = json_decode($zip->getFromName('manifest.json'), true);
        $this->assertArrayHasKey('projects', $manifest['tables']);
        $this->assertArrayHasKey('users', $manifest['tables']);
        $this->assertArrayNotHasKey('sessions', $manifest['tables']);
        $this->assertSame(Project::count(), $manifest['tables']['projects']['rows']);
        $this->assertSame('image-bytes', $zip->getFromName('files/public/media/cover.webp'));
        $this->assertSame(config('platform.schema_version'), $manifest['schema_version']);
        $zip->close();
    }

    public function test_tampering_is_detected(): void
    {
        $backup = $this->manager()->create('command');
        $zip = new ZipArchive;
        $zip->open($this->manager()->path($backup['name']));
        $zip->addFromString('database/projects.jsonl', '{"id":1}'."\n");
        $zip->close();

        $check = $this->manager()->verify($backup['name']);
        $this->assertFalse($check['verified']);
        $this->assertStringContainsString('projects', $check['error']);
    }

    public function test_restore_brings_back_rows_and_files_and_removes_later_changes(): void
    {
        Storage::disk('public')->put('media/original.webp', 'original');
        $before = Project::count();
        $backup = $this->manager()->create('command');

        Project::query()->first()->delete();
        ContactMessage::create(['name' => 'Later', 'email' => 'later@example.test', 'subject' => 'After backup', 'message' => 'x', 'status' => 'unread']);
        Storage::disk('public')->put('media/later.webp', 'later');
        Storage::disk('public')->delete('media/original.webp');

        $result = $this->manager()->restore($backup['name']);

        $this->assertSame($before, Project::count());
        $this->assertSame(0, ContactMessage::where('subject', 'After backup')->count());
        $this->assertTrue(Storage::disk('public')->exists('media/original.webp'));
        $this->assertFalse(Storage::disk('public')->exists('media/later.webp'));
        $this->assertSame(1, $result['files']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'backup.restored']);
    }

    public function test_restore_refuses_a_different_schema_version(): void
    {
        $backup = $this->manager()->create('command');
        config(['platform.schema_version' => config('platform.schema_version') + 1]);

        $this->expectExceptionMessage('schema');
        $this->manager()->restore($backup['name']);
    }

    public function test_restore_refuses_unsafe_manifest_paths(): void
    {
        $backup = $this->manager()->create('command');
        $path = $this->manager()->path($backup['name']);
        $zip = new ZipArchive;
        $zip->open($path);
        $manifest = json_decode($zip->getFromName('manifest.json'), true);
        $manifest['files']['../../escape.php'] = ['bytes' => 1, 'sha256' => hash('sha256', 'x')];
        $zip->addFromString('manifest.json', json_encode($manifest));
        $zip->addFromString('files/public/../../escape.php', 'x');
        $zip->close();

        $this->assertFalse($this->manager()->verify($backup['name'])['verified']);
        $this->expectExceptionMessage('Refusing to restore');
        $this->manager()->restore($backup['name']);
    }

    public function test_restore_command_takes_a_safety_backup_first(): void
    {
        $backup = $this->manager()->create('command');

        $this->artisan('portfolio:backup:restore', ['name' => $backup['name']])
            ->expectsConfirmation('Restore this backup?', 'yes')
            ->assertSuccessful();

        $triggers = array_column($this->manager()->list(), 'trigger');
        $this->assertContains('before-restore', $triggers);
        $this->assertFalse(app()->isDownForMaintenance());
    }

    public function test_old_backups_are_pruned_but_the_newest_verified_is_kept(): void
    {
        config(['portfolio.backups.keep' => 2]);
        foreach (range(1, 4) as $i) {
            $this->manager()->create('command');
            $this->travel(1)->seconds();
        }

        $this->assertCount(2, $this->manager()->list());
    }

    public function test_failure_raises_a_notification(): void
    {
        config(['portfolio.backups.min_free_bytes' => PHP_INT_MAX]);

        try {
            $this->manager()->create('scheduled');
            $this->fail('Expected the backup to fail.');
        } catch (\RuntimeException) {
        }

        $this->assertTrue(PlatformNotice::where('title', 'Backup failed')->whereNull('resolved_at')->exists());
        $this->assertDatabaseHas('audit_logs', ['action' => 'backup.failed']);

        config(['portfolio.backups.min_free_bytes' => 0]);
        $this->manager()->create('scheduled');
        $this->assertFalse(PlatformNotice::where('title', 'Backup failed')->whereNull('resolved_at')->exists());
    }

    public function test_dashboard_creates_lists_verifies_downloads_and_deletes(): void
    {
        $admin = $this->admin();
        $this->get('/admin/backups')->assertRedirect('/admin/login');

        $this->actingAs($admin)->post('/admin/backups')->assertSessionHas('status');
        $this->travel(1)->seconds();
        $this->actingAs($admin)->post('/admin/backups');
        [$newest, $older] = array_column($this->manager()->list(), 'name');

        $this->actingAs($admin)->get('/admin/backups')->assertOk()->assertSee($newest)->assertSee('Verified')
            ->assertSee('portfolio:backup:restore '.$newest);
        $this->actingAs($admin)->post("/admin/backups/{$older}/verify")->assertSessionHas('status');

        $this->actingAs($admin)->post("/admin/backups/{$older}/download", ['password' => 'wrong'])->assertSessionHasErrors('password');
        $this->actingAs($admin)->post("/admin/backups/{$older}/download", ['password' => self::PASSWORD])
            ->assertOk()->assertDownload($older);
        $this->assertDatabaseHas('audit_logs', ['action' => 'backup.downloaded']);

        // Throttled routes share one per-user counter; move past the window.
        $this->travel(2)->minutes();
        $this->actingAs($admin)->delete("/admin/backups/{$newest}", ['password' => self::PASSWORD])->assertSessionHasErrors('backup');
        $this->actingAs($admin)->delete("/admin/backups/{$older}", ['password' => self::PASSWORD])->assertSessionHas('status');
        $this->assertCount(1, $this->manager()->list());

        $this->actingAs($admin)->post('/admin/backups/..%2F..%2F.env/verify')->assertNotFound();
        $this->actingAs($admin)->post('/admin/backups/backup-20260101-000000-abcdef.zip/download', ['password' => self::PASSWORD])->assertNotFound();
    }
}
