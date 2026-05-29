<?php

namespace Sydgren\ShipIt\Commands;

use Sydgren\ShipIt\Client\ShipItClient;
use Sydgren\ShipIt\Client\ShipItException;
use Illuminate\Console\Command as BaseCommand;

abstract class Command extends BaseCommand
{
    public function __construct(protected ShipItClient $shipit)
    {
        parent::__construct();
    }

    /**
     * Run the command, turning API/config failures into clean error output.
     */
    public function handle(): int
    {
        try {
            return $this->run2();
        } catch (ShipItException $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }
    }

    abstract protected function run2(): int;

    /**
     * Resolve the target site id from the given identifier or the configured default.
     */
    protected function resolveSiteId(?string $identifier): int
    {
        $identifier ??= config('shipit.site');

        if (empty($identifier)) {
            throw ShipItException::noSite();
        }

        return $this->shipit->resolveSiteId($identifier);
    }

    /**
     * Stream a deployment's output until it finishes. Returns an exit code.
     */
    protected function watchDeployment(int $deploymentId): int
    {
        $printed = 0;
        $interval = max(0, (int) config('shipit.poll_interval', 2));

        while (true) {
            $result = $this->shipit->output($deploymentId);
            $output = (string) ($result['output'] ?? '');
            $status = (string) ($result['status'] ?? '');

            if (strlen($output) > $printed) {
                $this->output->write(substr($output, $printed));
                $printed = strlen($output);
            }

            if (! $this->shipit->isRunning($status)) {
                $this->newLine();

                if ($this->shipit->isSuccessful($status)) {
                    $this->components->info('Deployment succeeded.');

                    return self::SUCCESS;
                }

                $this->components->error("Deployment finished with status: {$status}.");

                return self::FAILURE;
            }

            if ($interval > 0) {
                sleep($interval);
            }
        }
    }

    protected function shortHash(?string $hash): string
    {
        return $hash ? substr($hash, 0, 8) : '—';
    }
}
