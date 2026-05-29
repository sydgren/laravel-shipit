<?php

namespace Sydgren\ShipIt\Commands;

class SitesCommand extends Command
{
    protected $signature = 'shipit:sites';

    protected $description = 'List sites available on your ShipIt account';

    protected function run2(): int
    {
        $sites = $this->shipit->sites();

        if ($sites->isEmpty()) {
            $this->components->warn('No sites found on this account.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Domain', 'Name', 'Branch', 'Deploy on', 'Status'],
            $sites->map(fn (array $s): array => [
                $s['id'],
                $s['domain'] ?? '—',
                $s['name'] ?? '—',
                $s['branch'] ?? '—',
                $s['deploy_on'] ?? '—',
                $s['status'] ?? '—',
            ])->all(),
        );

        return self::SUCCESS;
    }
}
