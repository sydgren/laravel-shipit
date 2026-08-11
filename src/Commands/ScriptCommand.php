<?php

namespace Sydgren\ShipIt\Commands;

use Sydgren\ShipIt\Client\ShipItClient;
use Sydgren\ShipIt\DeployFile;

class ScriptCommand extends Command
{
    protected $signature = 'shipit:script
        {site? : Site id or domain (defaults to SHIPIT_SITE)}
        {--check : Validate the existing file instead of writing one}
        {--force : Overwrite the file if it already exists}
        {--print : Write the scaffold to stdout instead of to disk}';

    protected $description = 'Scaffold or validate this repository\'s .shipit/deploy.yml';

    public function __construct(ShipItClient $shipit, protected DeployFile $deployFile)
    {
        parent::__construct($shipit);
    }

    protected function run2(): int
    {
        return $this->option('check') ? $this->check() : $this->scaffold();
    }

    /**
     * Validate the file already in the repository. No API call, so this works
     * in CI without a token.
     */
    protected function check(): int
    {
        $path = base_path(DeployFile::PATH);

        if (! is_file($path)) {
            $this->components->error(DeployFile::PATH.' does not exist. Create it with: php artisan shipit:script');

            return self::FAILURE;
        }

        $contents = (string) file_get_contents($path);
        $problems = $this->deployFile->problems($contents);

        if ($problems !== []) {
            $this->components->error(DeployFile::PATH.' would fail a deployment:');

            foreach ($problems as $problem) {
                $this->line('  <fg=red>•</> '.$problem);
            }

            return self::FAILURE;
        }

        $names = $this->deployFile->stepNames($contents);

        $this->components->info(DeployFile::PATH.' looks good — '.count($names).' step(s).');

        foreach ($names as $index => $name) {
            $this->line('  <fg=gray>'.($index + 1).'.</> '.$name);
        }

        return self::SUCCESS;
    }

    /**
     * Write the site's current steps into the repository, so the file starts
     * out as exactly what ShipIt runs today rather than a guess.
     */
    protected function scaffold(): int
    {
        $siteId = $this->resolveSiteId($this->argument('site'));
        ['path' => $relativePath, 'contents' => $contents] = $this->shipit->deploymentScriptScaffold($siteId);

        if ($this->option('print')) {
            $this->output->write($contents);

            return self::SUCCESS;
        }

        $path = base_path($relativePath);

        if (is_file($path) && ! $this->option('force')) {
            $this->components->error("{$relativePath} already exists. Pass --force to overwrite it.");

            return self::FAILURE;
        }

        if (! is_dir($directory = dirname($path))) {
            mkdir($directory, 0755, true);
        }

        file_put_contents($path, $contents);

        $this->components->info("Wrote {$relativePath} from the site's current deployment steps.");
        $this->line('  Commit it and this repository controls its own deployment.');
        $this->line("  Check it any time with: <info>php artisan shipit:script --check</info>");

        return self::SUCCESS;
    }
}
