<?php

namespace Sydgren\ShipIt\Commands;

class LogsCommand extends Command
{
    protected $signature = 'shipit:logs
        {deployment? : Deployment id (defaults to the latest for the site)}
        {--site= : Site id or domain when resolving the latest deployment}
        {--watch : Follow the log until the deployment finishes}';

    protected $description = 'Show the output of a ShipIt deployment';

    protected function run2(): int
    {
        $deploymentId = $this->deploymentId();

        if ($this->option('watch')) {
            return $this->watchDeployment($deploymentId);
        }

        $result = $this->shipit->output($deploymentId);
        $output = (string) ($result['output'] ?? '');

        $this->output->write($output === '' ? "(no output yet)\n" : $output);
        $this->newLine();
        $this->components->info('Status: '.($result['status'] ?? 'unknown'));

        return $this->shipit->isSuccessful((string) ($result['status'] ?? '')) || $this->shipit->isRunning((string) ($result['status'] ?? ''))
            ? self::SUCCESS
            : self::FAILURE;
    }

    private function deploymentId(): int
    {
        if ($id = $this->argument('deployment')) {
            return (int) $id;
        }

        $siteId = $this->resolveSiteId($this->option('site'));
        $latest = $this->shipit->deployments($siteId)->first();

        if ($latest === null) {
            $this->components->warn('This site has no deployments yet.');

            exit(self::SUCCESS);
        }

        return (int) $latest['id'];
    }
}
