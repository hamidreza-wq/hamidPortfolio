<?php

namespace App\Services\Platform;

use App\Models\ReleaseCandidate;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class Deployment
{
    private const API = 'https://api.github.com/repos/hamidreza-wq/hamidPortfolio';

    public function configured(): bool
    {
        return config('platform.deployment.enabled')
            && ctype_digit((string) config('platform.deployment.app_id'))
            && ctype_digit((string) config('platform.deployment.installation_id'))
            && is_readable((string) config('platform.deployment.private_key_path'));
    }

    public function dispatch(ReleaseCandidate $candidate): array
    {
        if (! $this->configured()) {
            throw new IntegrationFailure('Update deployment is not configured. Complete the protected GitHub App and production environment setup first.');
        }
        if (($candidate->manifest['schema_from'] ?? null) !== config('platform.schema_version')
            || ($candidate->manifest['schema_to'] ?? null) !== config('platform.schema_version')) {
            throw new IntegrationFailure('One-click updates support schema-preserving releases only. Database migrations require a separately reviewed deployment.');
        }
        $token = $this->installationToken();
        $environment = $this->request($token, '/environments/production')->json();
        $protected = false;
        foreach ($environment['protection_rules'] ?? [] as $rule) {
            if (($rule['type'] ?? '') === 'required_reviewers'
                && ! empty($rule['reviewers']) && ($rule['prevent_self_review'] ?? false)) {
                $protected = true;
            }
        }
        if (! $protected || ($environment['can_admins_bypass'] ?? true)) {
            throw new IntegrationFailure('The production environment must require independent reviewer approval and disable administrator bypass.');
        }
        $requestId = $candidate->inspection['deployment_request']['request_id'] ?? (string) Str::uuid();
        if (! Str::isUuid($requestId)) {
            throw new IntegrationFailure('Invalid deployment request identity.');
        }
        $response = $this->request($token, '/actions/workflows/deploy.yml/dispatches', [
            'ref' => 'main',
            'inputs' => [
                'tag' => $candidate->tag,
                'artifact_sha256' => $candidate->artifact_sha256,
                'installed_version' => app(Releases::class)->installedVersion(),
                'request_id' => $requestId,
            ],
        ]);
        if ($response->status() !== 204) {
            throw new IntegrationFailure('GitHub did not accept the deployment request.');
        }

        return ['request_id' => $requestId, 'status' => 'requested', 'requested_at' => now()->toIso8601String()];
    }

    public function refresh(array $deployment): array
    {
        if (! isset($deployment['request_id']) || ! Str::isUuid($deployment['request_id'])) {
            throw new IntegrationFailure('Deployment request identity is missing.');
        }
        $runId = $deployment['run_id'] ?? null;
        if ($runId !== null && (! is_int($runId) || $runId < 1)) {
            throw new IntegrationFailure('Invalid saved deployment run identity.');
        }
        $response = $this->request($this->installationToken(), $runId === null
            ? '/actions/workflows/deploy.yml/runs?event=workflow_dispatch&per_page=100'
            : '/actions/runs/'.$runId);
        $runs = $runId === null ? $response->json('workflow_runs', []) : [$response->json()];
        foreach ($runs as $run) {
            if (($run['display_title'] ?? '') !== 'Portfolio update '.$deployment['request_id']) {
                if ($runId !== null) {
                    throw new IntegrationFailure('Saved deployment run does not match this update request.');
                }

                continue;
            }
            if (! is_int($run['id'] ?? null)
                || ($run['html_url'] ?? '') !== 'https://github.com/hamidreza-wq/hamidPortfolio/actions/runs/'.$run['id']) {
                throw new IntegrationFailure('GitHub returned an invalid deployment run identity.');
            }

            return [...$deployment, 'run_id' => $run['id'], 'url' => $run['html_url'],
                'status' => $run['status'], 'conclusion' => $run['conclusion'] ?? null];
        }

        return $deployment;
    }

    private function installationToken(): string
    {
        if (! $this->configured()) {
            throw new IntegrationFailure('The deployment GitHub App is not configured.');
        }
        $encode = static fn (string $value): string => rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
        $header = $encode('{"alg":"RS256","typ":"JWT"}');
        $claims = $encode(json_encode(['iat' => time() - 60, 'exp' => time() + 300,
            'iss' => (string) config('platform.deployment.app_id')], JSON_THROW_ON_ERROR));
        $key = openssl_pkey_get_private(file_get_contents(config('platform.deployment.private_key_path')));
        if ($key === false || ! openssl_sign($header.'.'.$claims, $signature, $key, OPENSSL_ALGO_SHA256)) {
            throw new IntegrationFailure('The private deployment App key cannot sign authentication requests.');
        }
        try {
            $response = Http::withToken($header.'.'.$claims.'.'.$encode($signature))
                ->acceptJson()->withHeaders(['X-GitHub-Api-Version' => '2022-11-28'])
                ->connectTimeout(5)->timeout(15)->withoutRedirecting()
                ->post('https://api.github.com/app/installations/'.config('platform.deployment.installation_id').'/access_tokens', [
                    'repositories' => ['hamidPortfolio'],
                    'permissions' => ['actions' => 'write', 'contents' => 'read'],
                ]);
        } catch (ConnectionException) {
            throw new IntegrationFailure('Deployment App authentication could not reach GitHub.');
        }
        if ($response->status() !== 201 || ! is_string($response->json('token')) || $response->json('token') === '') {
            throw new IntegrationFailure('GitHub rejected the deployment App authentication. Check its installation and repository permissions.');
        }

        return $response->json('token');
    }

    private function request(string $token, string $path, ?array $body = null): Response
    {
        try {
            $request = Http::withToken($token)->acceptJson()
                ->withHeaders(['X-GitHub-Api-Version' => '2022-11-28'])
                ->connectTimeout(5)->timeout(20)->withoutRedirecting();
            $response = $body === null ? $request->get(self::API.$path) : $request->post(self::API.$path, $body);
        } catch (ConnectionException) {
            throw new IntegrationFailure('Deployment request could not reach GitHub. Review Actions before retrying; a timed-out dispatch may have been accepted.');
        }
        if (! $response->successful()) {
            throw new IntegrationFailure('GitHub deployment API returned HTTP '.$response->status().'. Check App permissions and the protected workflow configuration.');
        }

        return $response;
    }
}
