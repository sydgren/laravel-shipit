<?php

namespace Sydgren\ShipIt\Commands;

class DeploymentsCommand extends Command
{
    protected $signature = 'shipit:deployments
        {site? : Site id or domain (defaults to SHIPIT_SITE)}
        {--limit=10 : Number of recent deployments to show}';

    protected $description = 'List recent deployments for a site';

    protected function run2(): int
    {
        $siteId = $this->resolveSiteId($this->argument('site'));
        $limit = max(1, (int) $this->option('limit'));

        $deployments = $this->shipit->deployments($siteId)->take($limit);

        if ($deployments->isEmpty()) {
            $this->components->warn('This site has no deployments yet.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Status', 'Branch', 'Commit', 'Trigger', 'Finished'],
            $deployments->map(fn (array $d): array => [
                $d['id'],
                $d['status'],
                $d['branch'] ?? '—',
                $this->shortHash($d['commit_hash'] ?? null),
                $d['trigger'] ?? '—',
                $d['finished_at'] ?? '—',
            ])->all(),
        );

        return self::SUCCESS;
    }
}
