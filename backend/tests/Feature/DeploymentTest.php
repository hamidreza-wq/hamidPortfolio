<?php

namespace Tests\Feature;

use App\Models\ReleaseCandidate;
use App\Services\Platform\Deployment;
use App\Services\Platform\IntegrationFailure;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class DeploymentTest extends TestCase
{
    private string $keyPath;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        $this->keyPath = tempnam(sys_get_temp_dir(), 'portfolio-app-test-');
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $pem);
        file_put_contents($this->keyPath, $pem);
        config(['platform.deployment' => [
            'enabled' => true, 'app_id' => '123', 'installation_id' => '456',
            'private_key_path' => $this->keyPath,
        ]]);
    }

    protected function tearDown(): void
    {
        unlink($this->keyPath);
        parent::tearDown();
    }

    public function test_verified_release_dispatch_uses_scoped_app_and_fixed_protected_workflow(): void
    {
        $this->fakeGithub();
        $deployment = app(Deployment::class)->dispatch($this->candidate());
        $this->assertSame('requested', $deployment['status']);
        $this->assertTrue(Str::isUuid($deployment['request_id']));
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/access_tokens')
            && $request['repositories'] === ['hamidPortfolio']
            && $request['permissions'] === ['actions' => 'write', 'contents' => 'read']);
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/actions/workflows/deploy.yml/dispatches')
            && $request['ref'] === 'main' && $request['inputs']['tag'] === 'v1.0.0'
            && $request['inputs']['artifact_sha256'] === str_repeat('a', 64));
    }

    public function test_missing_configuration_refuses_dispatch_without_network(): void
    {
        config(['platform.deployment.enabled' => false]);
        try {
            app(Deployment::class)->dispatch($this->candidate());
            $this->fail('Unconfigured deployment accepted');
        } catch (IntegrationFailure $exception) {
            $this->assertStringContainsString('not configured', $exception->getMessage());
        }
        Http::assertNothingSent();
    }

    public function test_schema_changing_releases_are_not_dispatched(): void
    {
        $candidate = $this->candidate();
        $candidate->manifest = ['schema_from' => 3, 'schema_to' => 4];
        try {
            app(Deployment::class)->dispatch($candidate);
            $this->fail('Schema-changing deployment accepted');
        } catch (IntegrationFailure $exception) {
            $this->assertStringContainsString('schema-preserving', $exception->getMessage());
        }
        Http::assertNothingSent();
    }

    public function test_unprotected_environment_refuses_dispatch(): void
    {
        $this->fakeGithub(false);
        try {
            app(Deployment::class)->dispatch($this->candidate());
            $this->fail('Unprotected production environment accepted');
        } catch (IntegrationFailure $exception) {
            $this->assertStringContainsString('independent reviewer', $exception->getMessage());
        }
        Http::assertNotSent(fn ($request) => str_ends_with($request->url(), '/dispatches'));
    }

    public function test_status_matches_the_exact_request_not_an_unrelated_success(): void
    {
        $id = 'dd32f6b2-e9c2-42d8-921c-cd21f31b61af';
        $this->fakeGithub();
        Http::fake(['*/actions/workflows/deploy.yml/runs*' => Http::response(['workflow_runs' => [
            ['display_title' => 'Portfolio update unrelated', 'status' => 'completed', 'conclusion' => 'success'],
            ['id' => 77, 'display_title' => 'Portfolio update '.$id, 'status' => 'waiting', 'conclusion' => null,
                'html_url' => 'https://github.com/hamidreza-wq/hamidPortfolio/actions/runs/77'],
        ]])]);
        $status = app(Deployment::class)->refresh(['request_id' => $id, 'status' => 'requested']);
        $this->assertSame('waiting', $status['status']);
        $this->assertNull($status['conclusion']);
        $this->assertSame(77, $status['run_id']);
    }

    public function test_health_identifies_the_active_application_version(): void
    {
        $this->get('/up')->assertOk()->assertHeader('X-Portfolio-Version', trim(file_get_contents(base_path('../VERSION'))));
    }

    public function test_known_run_is_refreshed_directly_even_after_newer_workflow_runs(): void
    {
        $id = 'dd32f6b2-e9c2-42d8-921c-cd21f31b61af';
        $this->fakeGithub();
        Http::fake(['*/actions/runs/77' => Http::response([
            'id' => 77, 'display_title' => 'Portfolio update '.$id,
            'status' => 'completed', 'conclusion' => 'success',
            'html_url' => 'https://github.com/hamidreza-wq/hamidPortfolio/actions/runs/77',
        ])]);
        $status = app(Deployment::class)->refresh(['request_id' => $id, 'run_id' => 77]);
        $this->assertSame('success', $status['conclusion']);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/actions/workflows/deploy.yml/runs'));
    }

    public function test_saved_run_cannot_be_replaced_by_an_unrelated_success(): void
    {
        $this->fakeGithub();
        Http::fake(['*/actions/runs/77' => Http::response([
            'id' => 77, 'display_title' => 'Portfolio update unrelated',
            'status' => 'completed', 'conclusion' => 'success',
        ])]);
        $this->expectException(IntegrationFailure::class);
        $this->expectExceptionMessage('does not match');
        app(Deployment::class)->refresh(['request_id' => 'dd32f6b2-e9c2-42d8-921c-cd21f31b61af', 'run_id' => 77]);
    }

    private function candidate(): ReleaseCandidate
    {
        return new ReleaseCandidate(['tag' => 'v1.0.0', 'artifact_sha256' => str_repeat('a', 64),
            'manifest' => ['schema_from' => 3, 'schema_to' => 3]]);
    }

    private function fakeGithub(bool $protected = true): void
    {
        Http::fake([
            'https://api.github.com/app/installations/456/access_tokens' => Http::response(['token' => 'test-installation-token'], 201),
            '*/environments/production' => Http::response(['can_admins_bypass' => false, 'protection_rules' => $protected ? [
                ['type' => 'required_reviewers', 'prevent_self_review' => true, 'reviewers' => [['type' => 'User']]],
            ] : []]),
            '*/actions/workflows/deploy.yml/dispatches' => Http::response('', 204),
        ]);
    }
}
