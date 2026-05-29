<?php

namespace Sydgren\ShipIt\Commands;

class StatusCommand extends Command
{
    protected $signature = 'shipit:status
        {site? : Site id or domain (defaults to SHIPIT_SITE)}';

    protected $description = 'Show the latest deployment status for a site';

    protected function run2(): int
    {
        $siteId = $this->resolveSiteId($this->argument('site'));
        $deployment = $this->shipit->deployments($siteId)->first();

        if ($deployment === null) {
            $this->components->warn('This site has no deployments yet.');

            return self::SUCCESS;
        }

        $this->components->twoColumnDetail('Deployment', '#'.$deployment['id']);
        $this->components->twoColumnDetail('Status', $deployment['status']);
        $this->components->twoColumnDetail('Branch', $deployment['branch'] ?? '—');
        $this->components->twoColumnDetail('Commit', $this->shortHash($deployment['commit_hash'] ?? null));
        $this->components->twoColumnDetail('Trigger', $deployment['trigger'] ?? '—');
        $this->components->twoColumnDetail('Finished', $deployment['finished_at'] ?? '—');

        if ($duration = $deployment['duration'] ?? null) {
            $this->components->twoColumnDetail('Duration', $duration.'s');
        }

        return $this->shipit->isSuccessful((string) $deployment['status']) || $this->shipit->isRunning((string) $deployment['status'])
            ? self::SUCCESS
            : self::FAILURE;
    }
}
