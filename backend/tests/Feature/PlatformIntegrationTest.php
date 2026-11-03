<?php

namespace Tests\Feature;

use App\Mail\PlatformDigest;
use App\Models\GithubRepository;
use App\Models\NotificationPreference;
use App\Models\PlatformNotice;
use App\Models\PortfolioSetting;
use App\Models\Project;
use App\Models\ReleaseCandidate;
use App\Models\SeoFinding;
use App\Models\User;
use App\Services\DestructiveDatabaseGuard;
use App\Services\Platform\GithubDiscovery;
use App\Services\Platform\IntegrationFailure;
use App\Services\Platform\Monitor;
use App\Services\Platform\Notices;
use App\Services\Platform\PublicDns;
use App\Services\Platform\Releases;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;
use ZipArchive;

class PlatformIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        Http::preventStrayRequests();
        Storage::fake('local');
    }

    public function test_new_pages_and_actions_require_an_administrator(): void
    {
        foreach (['/admin/updates', '/admin/github-projects', '/admin/seo-monitor', '/admin/notifications'] as $path) {
            $this->get($path)->assertRedirect('/admin/login');
        }
        $this->post('/admin/platform/check/github', ['confirm' => 1])->assertRedirect('/admin/login');
        $this->actingAs(User::factory()->create(['is_admin' => false]));
        foreach (['/admin/updates', '/admin/github-projects', '/admin/seo-monitor', '/admin/notifications'] as $path) {
            $this->get($path)->assertForbidden();
        }
    }

    public function test_platform_pages_render_honest_disconnected_states(): void
    {
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $this->get('/admin/updates')->assertOk()->assertSee('Activation disabled')->assertSee('0.2.0-dev');
        $this->get('/admin/github-projects')->assertOk()->assertSee('Private repositories are disabled');
        $this->get('/admin/seo-monitor')->assertOk()->assertSee('Search Console: disconnected');
        $this->get('/admin/notifications')->assertOk()->assertSee('No notifications recorded');
    }

    public function test_no_official_release_records_a_failure_not_a_fake_update(): void
    {
        Http::fake(['api.github.com/*' => Http::response([], 404)]);
        $run = app(Monitor::class)->run('releases');
        $this->assertSame('failed', $run->status);
        $this->assertStringContainsString('No published official GitHub release', $run->message);
        $this->assertSame(0, ReleaseCandidate::count());
        $this->assertSame(1, PlatformNotice::count());
    }

    public function test_new_release_remains_unverified_until_artifact_verification(): void
    {
        Http::fake(['api.github.com/*' => Http::response($this->release())]);
        $this->assertSame('successful', app(Monitor::class)->run('releases')->status);
        $candidate = ReleaseCandidate::firstOrFail();
        $this->assertSame('available', $candidate->status);
        $this->assertNull($candidate->verified_at);
        $this->assertNull($candidate->approved_at);
    }

    public function test_current_release_does_not_create_an_update(): void
    {
        Http::fake(['api.github.com/*' => Http::response($this->release('v0.1.0'))]);
        $run = app(Monitor::class)->run('releases');
        $this->assertSame('successful', $run->status);
        $this->assertSame(0, ReleaseCandidate::count());
        $this->assertStringContainsString('No newer stable', $run->message);
    }

    #[DataProvider('badReleaseMetadata')]
    public function test_wrong_source_and_invalid_versions_are_rejected(string $field, mixed $value): void
    {
        Http::fake(['api.github.com/*' => Http::response([...$this->release(), $field => $value])]);
        $this->assertSame('failed', app(Monitor::class)->run('releases')->status);
        $this->assertSame(0, ReleaseCandidate::count());
    }

    public static function badReleaseMetadata(): array
    {
        return [
            ['html_url', 'https://github.com/attacker/project/releases/tag/v1.0.0'],
            ['tag_name', 'main'], ['tag_name', 'v01.0.0'], ['draft', true], ['prerelease', true],
        ];
    }

    public function test_signed_release_hash_archive_and_migrations_are_verified_but_not_activated(): void
    {
        $candidate = $this->signedCandidate();
        app(Releases::class)->prepare($candidate);
        $candidate->refresh();
        $this->assertSame('awaiting_approval', $candidate->status);
        $this->assertNotNull($candidate->verified_at);
        $this->assertNull($candidate->approved_at);
        $this->assertSame('disabled', $candidate->inspection['deployment']);
        $this->assertTrue($candidate->inspection['migrations'][0]['potentially_destructive']);
        Storage::disk('local')->assertExists($candidate->artifact_path);
        $this->assertSame($candidate->artifact_sha256, hash_file('sha256', Storage::disk('local')->path($candidate->artifact_path)));
    }

    #[DataProvider('badSignedManifest')]
    public function test_invalid_signed_fields_fail_closed(array $overrides, string $reason): void
    {
        $candidate = $this->signedCandidate($overrides);
        try {
            app(Releases::class)->prepare($candidate);
            $this->fail('Invalid signed manifest was accepted.');
        } catch (IntegrationFailure $exception) {
            $this->assertStringContainsString($reason, $exception->getMessage());
        }
        $this->assertSame('failed', $candidate->fresh()->status);
        $this->assertNull($candidate->fresh()->verified_at);
    }

    public static function badSignedManifest(): array
    {
        return [
            [['repository' => 'attacker/project'], 'manifest'],
            [['version' => '9.0.0'], 'manifest'],
            [['minimum_php' => '99.0.0'], 'newer PHP'],
            [['required_extensions' => ['nonexistent_extension_xyz']], 'extension'],
            [['schema_from' => 999], 'schema'],
            [['mysql_min' => '99.0.0'], 'MySQL'],
        ];
    }

    public function test_invalid_signature_is_rejected(): void
    {
        $candidate = $this->signedCandidate(invalidSignature: true);
        $this->expectException(IntegrationFailure::class);
        $this->expectExceptionMessage('signature');
        app(Releases::class)->prepare($candidate);
    }

    public function test_invalid_downloaded_checksum_is_rejected(): void
    {
        $candidate = $this->signedCandidate(corruptArtifact: true);
        $this->expectException(IntegrationFailure::class);
        $this->expectExceptionMessage('SHA-256');
        app(Releases::class)->prepare($candidate);
    }

    public function test_missing_github_digest_is_rejected_even_with_a_trusted_signature(): void
    {
        $candidate = $this->signedCandidate();
        $assets = $candidate->release_assets;
        $assets['portfolio.zip']['digest'] = null;
        $candidate->update(['release_assets' => $assets]);
        $this->expectException(IntegrationFailure::class);
        $this->expectExceptionMessage('GitHub asset SHA-256');
        app(Releases::class)->prepare($candidate);
    }

    public function test_unknown_trust_key_is_rejected(): void
    {
        $candidate = $this->signedCandidate();
        config(['platform.release_public_key' => null]);
        $this->expectException(IntegrationFailure::class);
        $this->expectExceptionMessage('RELEASE_PUBLIC_KEY');
        app(Releases::class)->prepare($candidate);
    }

    #[DataProvider('unsafePaths')]
    public function test_unsafe_archive_paths_are_rejected(string $name): void
    {
        $path = Storage::disk('local')->path('unsafe.zip');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString($name, 'untrusted');
        $zip->close();
        $this->expectException(IntegrationFailure::class);
        $this->expectExceptionMessage('unsafe paths');
        app(Releases::class)->inspectArchive($path);
    }

    public static function unsafePaths(): array
    {
        return [['../outside.php'], ['/absolute.php'], ['C:/root.php'], ['dir\\file.php'], ['dir/./file.php'], ['dir//file.php'], ['file.'], ['file ']];
    }

    public function test_symlink_and_malformed_archives_are_rejected(): void
    {
        $path = Storage::disk('local')->path('link.zip');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE);
        $zip->addFromString('link', '../../outside');
        $zip->setExternalAttributesName('link', ZipArchive::OPSYS_UNIX, 0120777 << 16);
        $zip->close();
        try {
            app(Releases::class)->inspectArchive($path);
            $this->fail('Symlink accepted.');
        } catch (IntegrationFailure $exception) {
            $this->assertStringContainsString('symlinks', $exception->getMessage());
        }
        Storage::disk('local')->put('broken.zip', 'not a zip');
        $this->expectException(IntegrationFailure::class);
        $this->expectExceptionMessage('malformed');
        app(Releases::class)->inspectArchive(Storage::disk('local')->path('broken.zip'));
    }

    public function test_review_requires_reauthentication_and_never_activates(): void
    {
        $candidate = $this->signedCandidate();
        $this->actingAs(User::factory()->create(['is_admin' => true, 'password' => 'A-test-password-2026!']));
        $this->post('/admin/updates/'.$candidate->id.'/verify', ['password' => 'wrong', 'confirm' => 1])->assertSessionHasErrors('password');
        app(Releases::class)->prepare($candidate);
        $this->post('/admin/updates/'.$candidate->id.'/approve', ['password' => 'A-test-password-2026!', 'confirm' => 1])->assertRedirect();
        $this->assertSame('manual_action_required', $candidate->fresh()->status);
        $this->assertNotNull($candidate->fresh()->approved_at);
        $this->assertDatabaseMissing('platform_runs', ['status' => 'applied']);
    }

    public function test_tampered_stored_artifact_cannot_be_approved(): void
    {
        $candidate = $this->signedCandidate();
        app(Releases::class)->prepare($candidate);
        Storage::disk('local')->put($candidate->fresh()->artifact_path, 'tampered');
        $this->actingAs(User::factory()->create(['is_admin' => true, 'password' => 'A-test-password-2026!']));
        $this->post('/admin/updates/'.$candidate->id.'/approve', ['password' => 'A-test-password-2026!', 'confirm' => 1])->assertSessionHasErrors('release');
        $this->assertNull($candidate->fresh()->approved_at);
    }

    public function test_key_rotation_invalidates_previous_review_eligibility(): void
    {
        $candidate = $this->signedCandidate();
        app(Releases::class)->prepare($candidate);
        config(['platform.release_public_key' => base64_encode(random_bytes(32))]);
        $this->actingAs(User::factory()->create(['is_admin' => true, 'password' => 'A-test-password-2026!']));
        $this->post('/admin/updates/'.$candidate->id.'/approve', ['password' => 'A-test-password-2026!', 'confirm' => 1])->assertSessionHasErrors('release');
        $this->assertNull($candidate->fresh()->approved_at);
    }

    public function test_failed_verification_is_explicit_in_history_and_notifications(): void
    {
        $candidate = $this->signedCandidate(invalidSignature: true);
        $this->actingAs(User::factory()->create(['is_admin' => true, 'password' => 'A-test-password-2026!']));
        $this->post('/admin/updates/'.$candidate->id.'/verify', ['password' => 'A-test-password-2026!', 'confirm' => 1])->assertSessionHasErrors('release');
        $this->assertDatabaseHas('platform_runs', ['kind' => 'prepare', 'status' => 'failed']);
        $this->assertSame('failed', $candidate->fresh()->status);
        $this->assertSame(1, PlatformNotice::where('title', 'Release verification failed')->count());
    }

    public function test_duplicate_checks_are_locked(): void
    {
        $lock = Cache::lock('platform:github', 180);
        $this->assertTrue($lock->get());
        try {
            app(Monitor::class)->run('github');
            $this->fail('Duplicate check was accepted.');
        } catch (IntegrationFailure $exception) {
            $this->assertStringContainsString('already running', $exception->getMessage());
        } finally {
            $lock->release();
        }
        Http::assertNothingSent();
    }

    public function test_discovery_creates_unpublished_drafts_and_excludes_forks_and_archives(): void
    {
        $this->fakeRepositories([$this->repository(101), $this->repository(102, ['fork' => true]), $this->repository(103, ['archived' => true])]);
        $run = app(Monitor::class)->run('github');
        $this->assertSame('successful', $run->status);
        $this->assertSame(3, GithubRepository::count());
        $project = Project::findOrFail(GithubRepository::findOrFail(101)->project_id);
        $this->assertFalse($project->is_published);
        $this->assertSame('MIT', $project->license_name);
        $this->assertNull($project->author_name);
        $this->assertNull($project->rights_confirmed_at);
        $this->assertSame([], $project->features);
        $this->assertNull(GithubRepository::findOrFail(102)->project_id);
        $this->get('/projects/'.$project->slug)->assertNotFound();
        $this->get('/sitemap.xml')->assertDontSee($project->slug);
    }

    public function test_rename_and_repeat_scans_preserve_manual_edits_and_repository_identity(): void
    {
        $this->fakeRepositories([$this->repository(101)]);
        app(GithubDiscovery::class)->scan();
        $repo = GithubRepository::findOrFail(101);
        $project = Project::findOrFail($repo->project_id);
        $project->update(['name' => 'Hand-edited name', 'description' => 'Hand-written description']);
        $repo->update(['state' => 'approved']);
        $this->fakeRepositories([$this->repository(101, ['name' => 'renamed', 'full_name' => 'hamidreza-wq/renamed', 'html_url' => 'https://github.com/hamidreza-wq/renamed'])]);
        app(GithubDiscovery::class)->scan();
        app(GithubDiscovery::class)->scan();
        $this->assertSame(1, GithubRepository::count());
        $this->assertSame('changes', $repo->fresh()->state);
        $this->assertSame('Hand-edited name', $project->fresh()->name);
        $this->assertSame('Hand-written description', $project->fresh()->description);
        $this->assertSame('sample-101', $repo->fresh()->previous_metadata['name']);
    }

    public function test_existing_repository_url_is_linked_not_duplicated_and_disappearance_never_deletes_project(): void
    {
        $project = Project::firstOrFail();
        $name = basename($project->repository_url);
        $this->fakeRepositories([$this->repository(101, ['name' => $name, 'full_name' => 'hamidreza-wq/'.$name, 'html_url' => $project->repository_url])]);
        app(GithubDiscovery::class)->scan();
        $this->assertSame('linked', GithubRepository::findOrFail(101)->state);
        $this->assertSame(5, Project::count());
        $this->fakeRepositories([]);
        app(GithubDiscovery::class)->scan();
        $this->assertSame('unavailable', GithubRepository::findOrFail(101)->state);
        $this->assertNotNull($project->fresh());
        $this->assertSame(5, Project::count());
    }

    public function test_pagination_is_bounded_and_all_pages_are_loaded_before_writes(): void
    {
        Http::fake(function ($request) {
            if ($request->url() === 'https://api.github.com/users/hamidreza-wq') {
                return Http::response(['login' => 'hamidreza-wq', 'id' => 77]);
            }

            return Http::response((int) $request['page'] === 1
                ? array_map(fn ($id) => $this->repository($id), range(1001, 1100))
                : [$this->repository(1101)]);
        });
        $result = app(GithubDiscovery::class)->scan();
        $this->assertSame(101, $result['discovered']);
        $this->assertSame(101, $result['new_drafts']);
        Http::assertSentCount(3);
    }

    public function test_api_failure_wrong_owner_and_private_data_produce_no_partial_import(): void
    {
        foreach ([
            $this->repository(101, ['owner' => ['login' => 'attacker', 'id' => 88]]),
            $this->repository(101, ['private' => true]),
        ] as $invalid) {
            $this->fakeRepositories([$this->repository(102), $invalid]);
            $this->assertSame('failed', app(Monitor::class)->run('github')->status);
            $this->assertSame(0, GithubRepository::count());
            $this->assertSame(5, Project::count());
        }
        $this->resetHttp();
        Http::fake(['api.github.com/*' => Http::response([], 429)]);
        $this->assertSame('failed', app(Monitor::class)->run('github')->status);
        $this->assertSame(0, GithubRepository::count());
    }

    public function test_wrong_account_identity_is_rejected(): void
    {
        Http::fake(['api.github.com/*' => Http::response(['login' => 'attacker', 'id' => 88])]);
        $this->assertSame('failed', app(Monitor::class)->run('github')->status);
        $this->assertSame(0, GithubRepository::count());
    }

    public function test_public_discovery_sends_no_credentials_and_never_fetches_readmes(): void
    {
        $this->fakeRepositories([$this->repository(101, ['description' => '<script>alert(1)</script> Useful text'])]);
        app(GithubDiscovery::class)->scan();
        Http::assertSentCount(2);
        Http::assertSent(fn ($request) => ! $request->hasHeader('Authorization'));
        $this->assertStringNotContainsString('<script>', GithubRepository::findOrFail(101)->metadata['description']);
    }

    public function test_import_review_does_not_publish_and_rejection_is_persistent(): void
    {
        $this->fakeRepositories([$this->repository(101)]);
        app(GithubDiscovery::class)->scan();
        $repo = GithubRepository::findOrFail(101);
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $this->post('/admin/github-projects/101/review', ['decision' => 'approve', 'confirm' => 1])->assertRedirect();
        $this->assertSame('approved', $repo->fresh()->state);
        $this->assertFalse(Project::findOrFail($repo->project_id)->is_published);
        $this->post('/admin/github-projects/101/review', ['decision' => 'reject', 'confirm' => 1])->assertRedirect();
        app(GithubDiscovery::class)->scan();
        $this->assertSame('rejected', $repo->fresh()->state);
    }

    public function test_unreviewed_import_cannot_bypass_review_in_project_editor(): void
    {
        $this->fakeRepositories([$this->repository(101)]);
        app(GithubDiscovery::class)->scan();
        $project = Project::findOrFail(GithubRepository::findOrFail(101)->project_id);
        $data = $this->editorData($project);
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $this->put('/admin/projects/'.$project->id, $data)->assertSessionHasErrors('is_published');
        $this->assertFalse($project->fresh()->is_published);
        $this->post('/admin/github-projects/101/review', ['decision' => 'approve', 'confirm' => 1])->assertRedirect();
        $this->put('/admin/projects/'.$project->id, $data)->assertSessionHasNoErrors();
        $this->assertTrue($project->fresh()->is_published);
    }

    public function test_disappearing_and_returning_sources_cannot_bypass_initial_publication_review(): void
    {
        $this->fakeRepositories([$this->repository(101)]);
        app(GithubDiscovery::class)->scan();
        $project = Project::findOrFail(GithubRepository::findOrFail(101)->project_id);
        $this->fakeRepositories([]);
        app(GithubDiscovery::class)->scan();
        $this->fakeRepositories([$this->repository(101)]);
        app(GithubDiscovery::class)->scan();
        $this->assertSame('changes', GithubRepository::findOrFail(101)->state);
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $this->put('/admin/projects/'.$project->id, $this->editorData($project))->assertSessionHasErrors('is_published');
        $this->assertFalse($project->fresh()->is_published);
    }

    public function test_authorship_claims_require_explicit_confirmation_and_preserve_attribution(): void
    {
        $project = Project::firstOrFail();
        $data = [...$this->editorData($project), 'author_name' => 'Verified author', 'copyright_holder' => 'Verified holder', 'rights_confirmed' => 0, 'attribution' => 'Contributors retain their rights.', 'license_name' => 'MIT'];
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $this->put('/admin/projects/'.$project->id, $data)->assertSessionHasErrors('rights_confirmed');
        $this->assertNull($project->fresh()->author_name);
        $data['rights_confirmed'] = 1;
        $this->put('/admin/projects/'.$project->id, $data)->assertSessionHasNoErrors();
        $this->assertNotNull($project->fresh()->rights_confirmed_at);
        $this->get('/projects/'.$project->slug)->assertOk()->assertSee('Verified author')->assertSee('Contributors retain their rights.');
    }

    public function test_unconfirmed_project_authorship_is_not_claimed_in_public_schema(): void
    {
        $project = Project::firstOrFail();
        $project->update(['author_name' => 'Unconfirmed name', 'copyright_holder' => 'Unconfirmed holder']);
        $this->get('/projects/'.$project->slug)->assertOk()->assertDontSee('Unconfirmed name')->assertDontSee('Unconfirmed holder');
        $project->update(['rights_confirmed_at' => now(), 'license_name' => 'MIT', 'attribution' => 'Preserved contributor notice']);
        $this->get('/projects/'.$project->slug)->assertOk()->assertSee('Unconfirmed name')->assertSee('MIT')->assertSee('Preserved contributor notice');
    }

    public function test_missing_monitor_origin_fails_without_any_network_request(): void
    {
        config(['platform.seo_origin' => null]);
        $this->assertSame('failed', app(Monitor::class)->run('seo')->status);
        Http::assertNothingSent();
    }

    #[DataProvider('unsafeOrigins')]
    public function test_ssrf_origins_are_rejected_before_dns_or_http(string $origin): void
    {
        config(['platform.seo_origin' => $origin]);
        $this->assertSame('failed', app(Monitor::class)->run('seo')->status);
        Http::assertNothingSent();
    }

    public static function unsafeOrigins(): array
    {
        return [['http://127.0.0.1'], ['https://127.0.0.1'], ['https://localhost'], ['https://[::1]'], ['https://site.example/admin'], ['https://user:pass@site.example'], ['https://site.example:8443'], ['https://site.example?url=http://localhost']];
    }

    public function test_private_dns_and_mismatched_canonical_are_rejected(): void
    {
        config(['platform.seo_origin' => 'https://site.example']);
        PortfolioSetting::findOrFail(1)->update(['canonical_url' => 'https://different.example']);
        $this->assertSame('failed', app(Monitor::class)->run('seo')->status);
        PortfolioSetting::findOrFail(1)->update(['canonical_url' => 'https://site.example']);
        $this->mock(PublicDns::class)->shouldReceive('ipv4')->once()->andThrow(new IntegrationFailure('Private DNS refused.'));
        $this->assertSame('failed', app(Monitor::class)->run('seo')->status);
        Http::assertNothingSent();
    }

    public function test_invalid_persisted_slugs_cannot_make_the_scanner_request_private_paths(): void
    {
        config(['platform.seo_origin' => 'https://site.example']);
        PortfolioSetting::findOrFail(1)->update(['canonical_url' => 'https://site.example']);
        Project::firstOrFail()->update(['slug' => '../admin']);
        $this->mock(PublicDns::class)->shouldReceive('ipv4')->andReturn('93.184.216.34');
        $run = app(Monitor::class)->run('seo');
        $this->assertSame('failed', $run->status);
        $this->assertStringContainsString('invalid public slug', $run->message);
        Http::assertNothingSent();
    }

    public function test_dns_public_address_policy_rejects_private_loopback_metadata_and_shared_networks(): void
    {
        $policy = new PublicDns;
        foreach (['127.0.0.1', '10.1.2.3', '172.16.0.1', '192.168.1.1', '169.254.169.254', '100.100.100.200', '0.0.0.0', '::1', '::ffff:127.0.0.1'] as $address) {
            $this->assertFalse($policy->isPublic($address), $address);
        }
        $this->assertTrue($policy->isPublic('93.184.216.34'));
    }

    public function test_real_html_checks_deduplicate_resolve_and_exclude_draft_urls(): void
    {
        Project::query()->update(['is_published' => false]);
        config(['platform.seo_origin' => 'https://site.example']);
        PortfolioSetting::findOrFail(1)->update(['canonical_url' => 'https://site.example']);
        $this->mock(PublicDns::class)->shouldReceive('ipv4')->andReturn('93.184.216.34');
        $this->fakeSeo(false);
        $this->assertSame('successful', app(Monitor::class)->run('seo')->status);
        $count = SeoFinding::count();
        $this->assertGreaterThan(0, $count);
        $this->assertDatabaseHas('seo_findings', ['check' => 'canonical', 'resolved_at' => null]);
        app(Monitor::class)->run('seo');
        $this->assertSame($count, SeoFinding::count());
        $noticeCount = PlatformNotice::count();
        $this->fakeSeo(true);
        app(Monitor::class)->run('seo');
        $this->assertSame($noticeCount, PlatformNotice::count());
        $this->assertSame(0, SeoFinding::whereNull('resolved_at')->count());
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/projects/') || str_contains($request->url(), '/admin'));
    }

    public function test_redirects_are_not_followed_and_become_actionable_findings(): void
    {
        Project::query()->update(['is_published' => false]);
        config(['platform.seo_origin' => 'https://site.example']);
        PortfolioSetting::findOrFail(1)->update(['canonical_url' => 'https://site.example']);
        $this->mock(PublicDns::class)->shouldReceive('ipv4')->andReturn('93.184.216.34');
        Http::fake(['site.example/*' => Http::response('', 302, ['Location' => 'http://127.0.0.1/admin'])]);
        app(Monitor::class)->run('seo');
        $this->assertDatabaseHas('seo_findings', ['path' => '/', 'check' => 'http-status']);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '127.0.0.1'));
    }

    public function test_notices_deduplicate_dismiss_snooze_and_reopen_changed_issues(): void
    {
        $notices = app(Notices::class);
        $notices->put('same', 'seo', 'medium', 'Title', 'Message', 'admin.platform.seo');
        $notice = PlatformNotice::firstOrFail();
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $this->patch('/admin/notifications/'.$notice->id, ['action' => 'snooze'])->assertRedirect();
        $this->assertSame(0, PlatformNotice::visible()->count());
        $this->travel(25)->hours();
        $this->assertSame(1, PlatformNotice::visible()->count());
        $this->patch('/admin/notifications/'.$notice->id, ['action' => 'dismiss'])->assertRedirect();
        $notices->put('same', 'seo', 'medium', 'Title', 'Message', 'admin.platform.seo');
        $this->assertSame(0, PlatformNotice::visible()->count());
        $notices->put('same', 'seo', 'high', 'Title', 'Changed message', 'admin.platform.seo');
        $this->assertSame(1, PlatformNotice::count());
        $this->assertSame(1, PlatformNotice::visible()->count());
        $this->assertNull($notice->fresh()->read_at);
        $notices->resolve('same');
        $this->assertSame(0, PlatformNotice::visible()->count());
    }

    public function test_destructive_command_guard_requires_a_test_database_and_environment(): void
    {
        $this->app['env'] = 'local';
        try {
            app(DestructiveDatabaseGuard::class)->handle(new CommandStarting('migrate:fresh', new ArrayInput([]), new BufferedOutput));
            $this->fail('Destructive command was permitted outside testing.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('--env=testing alone does not select a test database', $exception->getMessage());
        } finally {
            $this->app['env'] = 'testing';
        }
        $this->assertSame(5, Project::count());
    }

    public function test_testing_environment_alone_cannot_bypass_the_destructive_guard(): void
    {
        config(['database.connections.protected_fixture' => [
            ...config('database.connections.mysql'),
            'database' => 'protected_non_test_database',
        ]]);
        $input = new ArrayInput(['--database' => 'protected_fixture'], new InputDefinition([
            new InputOption('database', null, InputOption::VALUE_REQUIRED),
        ]));
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('--env=testing alone does not select a test database');
        app(DestructiveDatabaseGuard::class)->handle(new CommandStarting('db:wipe', $input, new BufferedOutput));
    }

    public function test_notification_preferences_scope_badges_and_opt_in_digests_without_losing_history(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin);
        $this->put('/admin/notification-preferences', ['categories' => ['github'], 'digest_frequency' => 'weekly'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $preference = NotificationPreference::forUser($admin->id);
        $this->assertSame(['github'], $preference->categories);
        $this->assertSame('weekly', $preference->digest_frequency);
        app(Notices::class)->put('seo-pref', 'seo', 'medium', 'Retained history notice', 'Message', 'admin.platform.seo');
        $this->get('/admin/notifications')->assertOk()->assertSee('Retained history notice');
        $this->put('/admin/notification-preferences', ['categories' => ['invalid'], 'digest_frequency' => 'hourly'])
            ->assertSessionHasErrors(['categories.0', 'digest_frequency']);
    }

    public function test_email_digests_are_opt_in_bounded_and_do_not_repeat_within_frequency(): void
    {
        Mail::fake();
        $admin = User::factory()->create(['is_admin' => true]);
        app(Notices::class)->put('email-seo', 'seo', 'medium', 'SEO email notice', 'Review content', 'admin.platform.seo');
        app(Notices::class)->put('email-github', 'github', 'medium', 'Excluded email category', 'Review repository', 'admin.platform.github');
        $this->artisan('platform:digest')->assertExitCode(0);
        Mail::assertNothingSent();
        $preference = NotificationPreference::create(['user_id' => $admin->id, 'categories' => ['seo'], 'digest_frequency' => 'daily']);
        $this->artisan('platform:digest')->assertExitCode(0);
        Mail::assertSent(PlatformDigest::class, fn ($mail) => $mail->hasTo($admin->email) && count($mail->items) === 1 && $mail->items[0]['title'] === 'SEO email notice');
        $this->assertNotNull($preference->fresh()->last_sent_at);
        $this->artisan('platform:digest')->assertExitCode(0);
        Mail::assertSentCount(1);
        $this->travel(25)->hours();
        app(Notices::class)->put('email-seo', 'seo', 'medium', 'SEO email notice', 'Updated review reminder', 'admin.platform.seo');
        $this->artisan('platform:digest')->assertExitCode(0);
        Mail::assertSentCount(2);
    }

    public function test_mail_transport_failures_are_reported_without_marking_digest_as_sent(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $preference = NotificationPreference::create(['user_id' => $admin->id, 'categories' => ['seo'], 'digest_frequency' => 'weekly']);
        app(Notices::class)->put('email-error', 'seo', 'medium', 'Review', 'Content review', 'admin.platform.seo');
        Mail::shouldReceive('to')->once()->with($admin->email)
            ->andThrow(new TransportException('Test transport failure'));
        $this->artisan('platform:digest')->assertExitCode(1);
        $this->assertNull($preference->fresh()->last_sent_at);
        $this->assertDatabaseHas('platform_runs', ['kind' => 'digest', 'status' => 'failed']);
    }

    private function repository(int $id, array $overrides = []): array
    {
        return [
            'id' => $id, 'name' => 'sample-'.$id, 'full_name' => 'hamidreza-wq/sample-'.$id,
            'html_url' => 'https://github.com/hamidreza-wq/sample-'.$id,
            'owner' => ['login' => 'hamidreza-wq', 'id' => 77],
            'private' => false, 'fork' => false, 'archived' => false,
            'description' => 'A real repository summary', 'language' => 'PHP',
            'license' => ['spdx_id' => 'MIT'], ...$overrides,
        ];
    }

    private function editorData(Project $project): array
    {
        return [
            'name' => $project->name, 'slug' => $project->slug, 'category' => 'other',
            'category_label' => 'Reviewed project', 'repository_url' => $project->repository_url,
            'description' => 'A reviewed factual description.', 'details' => 'Original project details.',
            'technologies' => 'PHP', 'features' => 'Reviewed actual feature',
            'visual' => 'planet', 'visual_label' => 'PROJECT', 'status' => 'active',
            'is_featured' => 0, 'is_published' => 1, 'sort_order' => 0,
        ];
    }

    private function fakeRepositories(array $repos): void
    {
        $this->resetHttp();
        Http::fake([
            'api.github.com/users/hamidreza-wq' => Http::response(['login' => 'hamidreza-wq', 'id' => 77]),
            'api.github.com/users/hamidreza-wq/repos*' => Http::response($repos),
        ]);
    }

    private function release(string $tag = 'v1.0.0'): array
    {
        return [
            'tag_name' => $tag, 'draft' => false, 'prerelease' => false,
            'html_url' => 'https://github.com/hamidreza-wq/hamidPortfolio/releases/tag/'.$tag,
            'published_at' => '2026-11-01T00:00:00Z', 'body' => 'Release notes', 'assets' => [],
        ];
    }

    private function signedCandidate(array $overrides = [], bool $invalidSignature = false, bool $corruptArtifact = false): ReleaseCandidate
    {
        if (! extension_loaded('sodium')) {
            $this->markTestSkipped('Trusted release fixture requires PHP sodium; enable the installed extension.');
        }
        $path = Storage::disk('local')->path('fixture.zip');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('backend/database/migrations/review.php', '<?php Schema::drop("example");');
        $zip->addFromString('VERSION', '1.0.0');
        $zip->close();
        $artifact = file_get_contents($path);
        $manifest = [
            'repository' => 'hamidreza-wq/hamidPortfolio', 'tag' => 'v1.0.0', 'version' => '1.0.0',
            'notes' => 'Trusted release notes', 'released_at' => '2026-11-01T00:00:00Z',
            'asset' => ['name' => 'portfolio.zip', 'sha256' => hash('sha256', $artifact)],
            'minimum_php' => '8.3.0', 'required_extensions' => ['pdo_mysql'],
            'mysql_min' => '8.0.0', 'mysql_max_exclusive' => '100.0.0', 'schema_from' => 3, 'schema_to' => 4,
            'database_engine' => str_contains(strtolower(DB::selectOne('SELECT VERSION() AS version')->version), 'mariadb') ? 'mariadb' : 'mysql',
            ...$overrides,
        ];
        $bytes = json_encode($manifest, JSON_UNESCAPED_SLASHES);
        $pair = sodium_crypto_sign_keypair();
        config(['platform.release_public_key' => base64_encode(sodium_crypto_sign_publickey($pair))]);
        $signature = sodium_crypto_sign_detached($bytes, sodium_crypto_sign_secretkey($pair));
        if ($invalidSignature) {
            $signature = str_repeat("\0", 64);
        }
        Http::fake([
            'api.github.com/repos/hamidreza-wq/hamidPortfolio/releases/assets/1' => Http::response($bytes),
            'api.github.com/repos/hamidreza-wq/hamidPortfolio/releases/assets/2' => Http::response(base64_encode($signature)),
            'api.github.com/repos/hamidreza-wq/hamidPortfolio/releases/assets/3' => Http::response($corruptArtifact ? 'corrupted' : $artifact),
        ]);

        return ReleaseCandidate::create([
            'tag' => 'v1.0.0', 'version' => '1.0.0', 'released_at' => now(),
            'release_assets' => [
                'portfolio-manifest.json' => ['id' => 1, 'size' => strlen($bytes)],
                'portfolio-manifest.sig' => ['id' => 2, 'size' => 88],
                'portfolio.zip' => ['id' => 3, 'size' => strlen($artifact), 'digest' => 'sha256:'.hash('sha256', $artifact)],
            ],
        ]);
    }

    private function fakeSeo(bool $healthy): void
    {
        $this->resetHttp();
        $words = implode(' ', array_fill(0, 80, 'useful'));
        Http::fake([
            'https://site.example/robots.txt' => Http::response("User-agent: *\nAllow: /\nSitemap: https://site.example/sitemap.xml"),
            'https://site.example/sitemap.xml' => Http::response('<urlset><url><loc>https://site.example/</loc></url></urlset>'),
            'https://site.example/' => Http::response($healthy
                ? '<html><head><title>Specific useful title</title><meta name="description" content="A meaningful original description"><link rel="canonical" href="https://site.example/"><meta property="og:title" content="Title"><meta property="og:description" content="Summary"><meta property="og:image" content="https://site.example/media/1"><meta name="viewport" content="width=device-width, initial-scale=1"><script type="application/ld+json">{"@context":"https://schema.org","@type":"WebSite"}</script></head><body><main><h1>Useful page</h1>'.$words.'</main></body></html>'
                : '<html><head></head><body><main><h1>Short page</h1></main></body></html>'),
        ]);
    }

    private function resetHttp(): void
    {
        Http::swap(new Factory);
        Http::preventStrayRequests();
    }
}
