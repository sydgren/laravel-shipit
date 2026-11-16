<?php

use Illuminate\Support\Facades\Http;

it('reports a completed rollback as a success', function () {
    Http::fake([
        'shipit.test/api/deployments/12/rollback' => Http::response([
            'message' => 'Rollback completed successfully',
            'deployment' => ['id' => 31, 'status' => 'rolled_back'],
        ]),
    ]);

    $this->artisan('shipit:rollback', ['deployment' => '12'])
        ->expectsConfirmation('Roll back to deployment #12?', 'yes')
        ->expectsOutputToContain('Rolled back to deployment #12. Recorded as deployment #31.')
        ->assertSuccessful();
});

it('fails with ShipIt\'s reason when the rollback cannot run', function () {
    Http::fake([
        'shipit.test/api/deployments/11/rollback' => Http::response([
            'message' => 'You can only roll back to a deployment that succeeded.',
        ], 422),
    ]);

    $this->artisan('shipit:rollback', ['deployment' => '11'])
        ->expectsConfirmation('Roll back to deployment #11?', 'yes')
        ->expectsOutputToContain('You can only roll back to a deployment that succeeded.')
        ->assertFailed();
});
