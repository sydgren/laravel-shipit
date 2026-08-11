<?php

namespace Sydgren\ShipIt\Client;

use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;

/**
 * Thin client over the ShipIt REST API.
 *
 * Every method returns plain arrays/collections mirroring the API's JSON,
 * so commands stay free of HTTP concerns.
 */
class ShipItClient
{
    public function __construct(
        private readonly Factory $http,
        private readonly string $baseUrl,
        private readonly ?string $token,
        private readonly int $timeout = 30,
    ) {}

    /**
     * Resolve a site identifier (numeric id or domain/name) to its id.
     */
    public function resolveSiteId(int|string $identifier): int
    {
        if (is_int($identifier) || ctype_digit((string) $identifier)) {
            return (int) $identifier;
        }

        $needle = strtolower((string) $identifier);

        $match = $this->sites()->first(function (array $site) use ($needle): bool {
            return strtolower((string) ($site['domain'] ?? '')) === $needle
                || strtolower((string) ($site['name'] ?? '')) === $needle;
        });

        if ($match === null) {
            throw ShipItException::siteNotFound((string) $identifier);
        }

        return (int) $match['id'];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function servers(): Collection
    {
        return $this->paginate('servers');
    }

    /**
     * Every site across every server the account can see.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function sites(): Collection
    {
        return $this->servers()->flatMap(
            fn (array $server) => $this->paginate("servers/{$server['id']}/sites")->all()
        )->values();
    }

    /**
     * @return array<string, mixed>
     */
    public function site(int $siteId): array
    {
        return $this->get("sites/{$siteId}")['data'];
    }

    /**
     * Trigger a deployment.
     *
     * @return array{deployment: array<string, mixed>, alreadyRunning: bool}
     */
    public function deploy(int $siteId, ?string $branch = null): array
    {
        $response = $this->request()->post("sites/{$siteId}/deploy", array_filter([
            'branch' => $branch,
        ]));

        // 409 means a deployment is already running; surface that deployment.
        if ($response->status() === 409) {
            return ['deployment' => $this->unwrap($response, 'deployment'), 'alreadyRunning' => true];
        }

        $this->throwUnlessOk($response);

        return ['deployment' => $this->unwrap($response, 'deployment'), 'alreadyRunning' => false];
    }

    /**
     * The site's effective deployment steps, plus the script variables they
     * may use.
     *
     * @return array{steps: list<array<string, mixed>>, variables: list<array{name: string, description: string}>}
     */
    public function deploymentSteps(int $siteId): array
    {
        $body = $this->get("sites/{$siteId}/deployment-steps");

        return [
            'steps' => $body['steps'] ?? [],
            'variables' => $body['variables'] ?? [],
        ];
    }

    /**
     * The site's steps rendered as a committable `.shipit/deploy.yml`.
     *
     * @return array{path: string, contents: string}
     */
    public function deploymentScriptScaffold(int $siteId): array
    {
        $body = $this->get("sites/{$siteId}/deployment-steps/scaffold");

        return [
            'path' => (string) ($body['path'] ?? '.shipit/deploy.yml'),
            'contents' => (string) ($body['contents'] ?? ''),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function deployment(int $deploymentId): array
    {
        return $this->get("deployments/{$deploymentId}")['data'];
    }

    /**
     * @return array{output: ?string, status: string}
     */
    public function output(int $deploymentId): array
    {
        return $this->get("deployments/{$deploymentId}/output");
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function deployments(int $siteId): Collection
    {
        return $this->paginate("sites/{$siteId}/deployments");
    }

    /**
     * @return array<string, mixed>
     */
    public function rollback(int $deploymentId): array
    {
        $response = $this->request()->post("deployments/{$deploymentId}/rollback");

        $this->throwUnlessOk($response);

        return $this->unwrap($response, 'deployment');
    }

    public function isRunning(string $status): bool
    {
        return in_array($status, ['pending', 'running'], true);
    }

    public function isSuccessful(string $status): bool
    {
        return $status === 'success';
    }

    private function request(): PendingRequest
    {
        if (empty($this->token)) {
            throw ShipItException::notConfigured();
        }

        return $this->http
            ->baseUrl(rtrim($this->baseUrl, '/').'/api')
            ->withToken($this->token)
            ->acceptJson()
            ->withHeaders(['User-Agent' => 'shipit-deployer'])
            ->timeout($this->timeout);
    }

    /**
     * @return array<string, mixed>
     */
    private function get(string $path): array
    {
        $response = $this->request()->get($path);

        $this->throwUnlessOk($response);

        return $response->json();
    }

    /**
     * Walk Laravel pagination, returning every "data" row.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function paginate(string $path): Collection
    {
        $rows = collect();
        $page = 1;

        do {
            $response = $this->request()->get($path, ['page' => $page]);
            $this->throwUnlessOk($response);

            $body = $response->json();
            $rows = $rows->concat($body['data'] ?? []);

            $lastPage = (int) ($body['meta']['last_page'] ?? 1);
            $page++;
        } while ($page <= $lastPage);

        return $rows->values();
    }

    /**
     * @return array<string, mixed>
     */
    private function unwrap(Response $response, string $key): array
    {
        return $response->json($key, []);
    }

    private function throwUnlessOk(Response $response): void
    {
        if ($response->successful()) {
            return;
        }

        $message = $response->json('message')
            ?? $response->json('errors')
            ?? $response->body();

        if (is_array($message)) {
            $message = collect($message)->flatten()->implode(' ');
        }

        throw ShipItException::fromResponse($response->status(), $message);
    }
}
