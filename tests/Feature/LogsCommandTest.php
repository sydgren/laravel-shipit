<?php

use Illuminate\Support\Facades\Http;

it('keeps following a queued deployment until it finishes', function () {
    Http::fake([
        'shipit.test/api/deployments/30/output' => Http::sequence()
            ->push(['output' => null, 'status' => 'queued'])
            ->push(['output' => "Building...\n", 'status' => 'running'])
            ->push(['output' => "Building...\nDone.\n", 'status' => 'success']),
    ]);

    $this->artisan('shipit:logs', ['deployment' => '30', '--watch' => true])
        ->expectsOutputToContain('Done.')
        ->expectsOutputToContain('Deployment succeeded.')
        ->assertSuccessful();

    Http::assertSentCount(3);
});
