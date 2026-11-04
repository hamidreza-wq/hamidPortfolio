<?php

namespace Tests\Database;

use App\Services\TestDatabaseSafety;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MigrationUpgradeTest extends TestCase
{
    public static function supportedBaselines(): array
    {
        return [
            'initial-cms-schema' => ['2026_11_03'],
            'previous-hardening-schema' => ['2026_11_04_020000'],
        ];
    }

    #[DataProvider('supportedBaselines')]
    public function test_fresh_install_and_additive_upgrade_preserve_existing_content(string $ceiling): void
    {
        $this->verifyTarget();
        $this->artisan('migrate:fresh', ['--seed' => true, '--force' => true])->assertSuccessful();
        $this->assertSame(5, DB::table('projects')->count());
        $this->assertSame(0, DB::table('migrations')->whereNull('batch')->count());
        $this->assertTrue(Schema::hasTable('google_search_console_connections'));
        $this->assertTrue(Schema::hasColumn('projects', 'live_url_verified'));

        $baseline = collect(glob(database_path('migrations/*.php')))
            ->filter(fn (string $file) => basename($file) < $ceiling)
            ->map(fn (string $file) => 'database/migrations/'.basename($file))->values()->all();
        $this->verifyTarget();
        $this->artisan('migrate:fresh', ['--path' => $baseline, '--force' => true])->assertSuccessful();
        DB::table('users')->insert(['id' => 1, 'name' => 'Upgrade fixture', 'email' => 'upgrade@example.test', 'password' => 'not-a-login-hash']);
        DB::table('portfolio_settings')->insert(['id' => 1, 'name' => 'Existing editorial name']);
        DB::table('portfolio_sections')->insert(['key' => 'about', 'label' => 'About', 'title' => 'Existing section', 'body' => 'Keep original content']);
        DB::table('projects')->insert([
            'name' => 'Existing project', 'slug' => 'existing-project', 'category_label' => 'Existing category',
            'repository_url' => 'https://github.com/hamidreza-wq/Bible', 'live_url' => null,
            'description' => 'Keep description', 'details' => 'Keep details', 'technologies' => '["PHP"]',
            'features' => '["Existing feature"]', 'visual_label' => 'Existing artwork', 'created_by' => 1,
        ]);
        DB::table('contact_messages')->insert(['name' => 'Fixture visitor', 'email' => 'visitor@example.test', 'subject' => 'Keep subject', 'message' => 'Keep private synthetic message']);
        $tables = ['users', 'portfolio_settings', 'portfolio_sections', 'projects', 'contact_messages'];
        $before = [];
        foreach ($tables as $table) {
            $before[$table] = (array) DB::table($table)->first();
        }
        $this->verifyTarget();
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
        foreach ($before as $table => $row) {
            $this->assertSame($row, array_intersect_key((array) DB::table($table)->first(), $row), $table.' lost baseline content');
            $this->assertSame(1, DB::table($table)->count());
        }
        $this->assertSame(0, DB::table('projects')->value('repository_is_public'));
        $this->assertSame(0, DB::table('projects')->value('live_url_verified'));
        $this->verifyTarget();
        $migration = require database_path('migrations/2026_11_04_020000_add_project_link_visibility.php');
        $migration->up();
        $this->assertSame('Keep details', DB::table('projects')->value('details'));
    }

    private function verifyTarget(): void
    {
        $this->assertNotEmpty(app(TestDatabaseSafety::class)->verify()['effective_database']);
        $this->artisan('portfolio:test-database:verify')->assertSuccessful();
    }
}
