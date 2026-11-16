<?php

namespace Sydgren\ShipIt\Commands;

class RollbackCommand extends Command
{
    protected $signature = 'shipit:rollback
        {deployment : Deployment id to roll back to}';

    protected $description = 'Roll a site back to a previous successful deployment';

    protected function run2(): int
    {
        $deploymentId = (int) $this->argument('deployment');

        if (! $this->confirm("Roll back to deployment #{$deploymentId}?", true)) {
            $this->components->warn('Aborted.');

            return self::SUCCESS;
        }

        // Synchronous on ShipIt's side: a failed rollback comes back as an
        // error, and a completed one carries the status `rolled_back`.
        $result = $this->shipit->rollback($deploymentId);

        $this->components->info("Rolled back to deployment #{$deploymentId}. Recorded as deployment #{$result['id']}.");

        return self::SUCCESS;
    }
}
