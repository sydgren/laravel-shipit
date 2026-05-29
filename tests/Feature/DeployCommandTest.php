<?php

use Illuminate\Support\Facades\Http;

function deploymentPayload(array $overrides = []): array
{
    return ['deployment' => array_merge([
        'id' => 10,
        'site_id' => 5,
        'branch' => 'main',
        'status' => 'pending',
        'trigger' => 'manual',
    ], $overrides)];
}

it('deploys a site by id and returns immediately with --no-wait', function () {
    Http::fake([
        'shipit.test/api/sites/5/deploy' => Http::response(deploymentPayload(), 202),
    ]);

    $this->artisan('shipit:deploy', ['site' => '5', '--no-wait' => true])
        ->expectsOutputToContain('Deployment #10 queued')
        ->assertSuccessful();

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/api/sites/5/deploy'));
});

it('resolves a site by domain before deploying', function () {
    Http::fake([
        'shipit.test/api/servers/*/sites*' => Http::response([
            'data' => [['id' => 5, 'domain' => 'example.com', 'name' => 'Example']],
            'meta' => ['last_page' => 1],
        ]),
        'shipit.test/api/servers*' => Http::response([
            'data' => [['id' => 1]],
            'meta' => ['last_page' => 1],
        ]),
        'shipit.test/api/sites/5/deploy' => Http::response(deploymentPayload(), 202),
    ]);

    $this->artisan('shipit:deploy', ['site' => 'example.com', '--no-wait' => true])
        ->assertSuccessful();
});

it('falls back to the configured site when none is given', function () {
    config()->set('shipit.site', '7');

    Http::fake([
        'shipit.test/api/sites/7/deploy' => Http::response(deploymentPayload(['site_id' => 7]), 202),
    ]);

    $this->artisan('shipit:deploy', ['--no-wait' => true])->assertSuccessful();
});

it('streams the log and exits successfully with --watch', function () {
    Http::fake([
        'shipit.test/api/sites/5/deploy' => Http::response(deploymentPayload(), 202),
        'shipit.test/api/deployments/10/output' => Http::sequence()
            ->push(['output' => "Cloning...\n", 'status' => 'running'])
            ->push(['output' => "Cloning...\nBuilding...\nDone.\n", 'status' => 'success']),
    ]);

    $this->artisan('shipit:deploy', ['site' => '5', '--watch' => true])
        ->expectsOutputToContain('Done.')
        ->expectsOutputToContain('Deployment succeeded.')
        ->assertSuccessful();
});

it('fails the command when a watched deployment fails', function () {
    Http::fake([
        'shipit.test/api/sites/5/deploy' => Http::response(deploymentPayload(), 202),
        'shipit.test/api/deployments/10/output' => Http::sequence()
            ->push(['output' => "Building...\n", 'status' => 'running'])
            ->push(['output' => "Building...\nfatal error\n", 'status' => 'failed']),
    ]);

    $this->artisan('shipit:deploy', ['site' => '5', '--watch' => true])
        ->assertFailed();
});

it('warns instead of starting a second deployment on conflict', function () {
    Http::fake([
        'shipit.test/api/sites/5/deploy' => Http::response(
            deploymentPayload(['status' => 'running']),
            409
        ),
    ]);

    $this->artisan('shipit:deploy', ['site' => '5', '--no-wait' => true])
        ->expectsOutputToContain('already running')
        ->assertSuccessful();
});

it('errors cleanly when no token is configured', function () {
    config()->set('shipit.token', null);

    $this->artisan('shipit:deploy', ['site' => '5', '--no-wait' => true])
        ->expectsOutputToContain('not configured')
        ->assertFailed();
});

it('errors cleanly when no site can be resolved', function () {
    $this->artisan('shipit:deploy', ['--no-wait' => true])
        ->expectsOutputToContain('No site given')
        ->assertFailed();
});
