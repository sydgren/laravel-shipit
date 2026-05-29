<?php

namespace Sydgren\ShipIt\Commands;

class DeployCommand extends Command
{
    protected $signature = 'shipit:deploy
        {site? : Site id or domain (defaults to SHIPIT_SITE)}
        {--branch= : Branch to deploy (defaults to the site\'s branch)}
        {--watch : Stream the deployment log and exit with its status}
        {--no-wait : Return immediately after queueing the deployment}';

    protected $description = 'Trigger a deployment for a ShipIt site';

    protected function run2(): int
    {
        $siteId = $this->resolveSiteId($this->argument('site'));
        $branch = $this->option('branch') ?: null;

        ['deployment' => $deployment, 'alreadyRunning' => $alreadyRunning] = $this->shipit->deploy($siteId, $branch);

        if ($alreadyRunning) {
            $this->components->warn(
                "A deployment is already running (#{$deployment['id']}). Attaching instead of starting a new one."
            );
        } else {
            $this->components->info("Deployment #{$deployment['id']} queued on branch {$deployment['branch']}.");
        }

        if ($this->option('no-wait')) {
            return self::SUCCESS;
        }

        if ($this->option('watch')) {
            return $this->watchDeployment((int) $deployment['id']);
        }

        $this->line("  Follow it with: <info>php artisan shipit:logs {$deployment['id']} --watch</info>");

        return self::SUCCESS;
    }
}
