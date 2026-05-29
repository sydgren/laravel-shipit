<?php

use Illuminate\Support\Facades\Http;

it('lists sites across servers', function () {
    Http::fake([
        'shipit.test/api/servers/*/sites*' => Http::response([
            'data' => [
                ['id' => 5, 'domain' => 'example.com', 'name' => 'Example', 'branch' => 'main', 'deploy_on' => 'push', 'status' => 'active'],
            ],
            'meta' => ['last_page' => 1],
        ]),
        'shipit.test/api/servers*' => Http::response([
            'data' => [['id' => 1]],
            'meta' => ['last_page' => 1],
        ]),
    ]);

    $this->artisan('shipit:sites')
        ->expectsOutputToContain('example.com')
        ->assertSuccessful();
});

it('walks pagination when listing deployments', function () {
    Http::fake([
        'shipit.test/api/sites/5/deployments*' => Http::sequence()
            ->push(['data' => [['id' => 12, 'status' => 'success', 'branch' => 'main', 'commit_hash' => 'abcdef1234', 'trigger' => 'manual', 'finished_at' => '2026-05-29T10:00:00Z']], 'meta' => ['last_page' => 2, 'current_page' => 1]])
            ->push(['data' => [['id' => 11, 'status' => 'failed', 'branch' => 'main', 'commit_hash' => '99887766', 'trigger' => 'push', 'finished_at' => '2026-05-28T10:00:00Z']], 'meta' => ['last_page' => 2, 'current_page' => 2]]),
    ]);

    $this->artisan('shipit:deployments', ['site' => '5'])
        ->expectsOutputToContain('12')
        ->expectsOutputToContain('11')
        ->assertSuccessful();
});
