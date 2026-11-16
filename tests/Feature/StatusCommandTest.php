<?php

use Illuminate\Support\Facades\Http;

it('reads only the newest page of deployments and treats a queued one as in progress', function () {
    Http::fake([
        'shipit.test/api/sites/5/deployments*' => Http::response([
            'data' => [
                ['id' => 30, 'status' => 'queued', 'branch' => 'main', 'commit_hash' => 'abcdef1234', 'trigger' => 'push', 'finished_at' => null],
                ['id' => 29, 'status' => 'success', 'branch' => 'main', 'commit_hash' => '99887766', 'trigger' => 'push', 'finished_at' => '2026-11-16T09:00:00Z'],
            ],
            'meta' => ['last_page' => 40, 'current_page' => 1],
        ]),
    ]);

    $this->artisan('shipit:status', ['site' => '5'])
        ->expectsOutputToContain('#30')
        ->expectsOutputToContain('queued')
        ->assertSuccessful();

    Http::assertSentCount(1);
});
