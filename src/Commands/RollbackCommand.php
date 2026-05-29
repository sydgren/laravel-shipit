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

        $result = $this->shipit->rollback($deploymentId);

        $this->components->info("Rolled back. New deployment #{$result['id']} ({$result['status']}).");

        return $this->shipit->isSuccessful((string) $result['status']) ? self::SUCCESS : self::FAILURE;
    }
}
