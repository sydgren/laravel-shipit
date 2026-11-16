<?php

namespace Sydgren\ShipIt\Commands;

use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class ScriptCommand extends Command
{
    /**
     * Where ShipIt looks for a deploy script in the repository.
     */
    public const PATH = '.shipit/deploy.sh';

    /**
     * Put above a scaffolded script, so whoever opens the file later knows how
     * ShipIt runs it.
     */
    private const HEADER = <<<'BASH'
        #!/usr/bin/env bash
        #
        # ShipIt runs this script on every deployment of this repository, in place
        # of the site's script in ShipIt. It runs with bash under `set -eo pipefail`,
        # in the new release directory, before that release goes live, with these
        # variables exported: RELEASE_PATH, SHARED_PATH, BASE_PATH, PREVIOUS_RELEASE,
        # BRANCH, COMMIT, COMMIT_SHORT, DOMAIN, PHP_VERSION.
        BASH;

    protected $signature = 'shipit:script
        {site? : Site id or domain (defaults to SHIPIT_SITE)}
        {--check : Show which script the next deployment runs, without writing anything}
        {--force : Overwrite an existing .shipit/deploy.sh}
        {--print : Print the site\'s script from ShipIt instead of writing it}';

    protected $description = 'Scaffold .shipit/deploy.sh from the site\'s deploy script, or check which script ShipIt runs';

    protected function run2(): int
    {
        if ($this->option('check')) {
            return $this->check();
        }

        return $this->option('print') ? $this->printScript() : $this->scaffold();
    }

    /**
     * Write the site's current script into the repository, so the file starts
     * out as exactly what ShipIt runs today rather than a guess.
     */
    protected function scaffold(): int
    {
        $path = base_path(self::PATH);

        if (is_file($path) && ! $this->option('force')) {
            $this->components->error(self::PATH.' already exists. Pass --force to overwrite it.');

            return self::FAILURE;
        }

        ['script' => $script, 'is_default' => $isDefault] = $this->siteScript();

        if (! is_dir($directory = dirname($path))) {
            mkdir($directory, 0755, true);
        }

        file_put_contents($path, str_starts_with($script, '#!') ? $script : self::HEADER."\n\n".$script);
        chmod($path, 0755);

        $this->components->info('Wrote '.self::PATH.' from '.($isDefault
            ? "ShipIt's default script for the site's type."
            : "the site's own script in ShipIt."));
        $this->explainPrecedence();
        $this->line('  Commit it, and every deployment from then on runs it.');

        return self::SUCCESS;
    }

    protected function printScript(): int
    {
        $this->output->write($this->siteScript()['script']);

        return self::SUCCESS;
    }

    /**
     * Say which script the next deployment runs. A repository script decides
     * that on its own, so with one present no request is made and no token is
     * needed, which keeps this usable in CI.
     */
    protected function check(): int
    {
        $path = base_path(self::PATH);

        if (is_file($path)) {
            return $this->checkRepositoryScript($path);
        }

        $isDefault = $this->siteScript()['is_default'];

        $this->components->info($isDefault
            ? "Deployments run ShipIt's default script for the site's type: there is no ".self::PATH.' in this repository and the site has no script of its own.'
            : "Deployments run the site's own script in ShipIt: there is no ".self::PATH.' in this repository.');
        $this->explainPrecedence();
        $this->line('  See the script with: <info>php artisan shipit:script --print</info>');
        $this->line('  Move it into the repository with: <info>php artisan shipit:script</info>');

        return self::SUCCESS;
    }

    private function checkRepositoryScript(string $path): int
    {
        if (($syntaxError = $this->syntaxError($path)) !== null) {
            $this->components->error(self::PATH.' does not parse, so a deployment would fail before going live:');
            $this->line('  '.$syntaxError);

            return self::FAILURE;
        }

        $this->components->info('Deployments run '.self::PATH.' from this repository.');
        $this->explainPrecedence();
        $this->line("  It applies once committed on the branch ShipIt deploys; the site's script in ShipIt is then ignored.");

        return self::SUCCESS;
    }

    /**
     * bash's own complaint about the script, or null when it parses. Skipped
     * where bash is not installed.
     */
    private function syntaxError(string $path): ?string
    {
        $bash = (new ExecutableFinder)->find('bash');

        if ($bash === null) {
            $this->components->warn('bash is not installed here, so '.self::PATH.' was not syntax-checked.');

            return null;
        }

        $process = new Process([$bash, '-n', $path]);
        $process->run();

        return $process->isSuccessful()
            ? null
            : str_replace($path, self::PATH, trim($process->getErrorOutput()));
    }

    /**
     * @return array{script: string, is_default: bool}
     */
    private function siteScript(): array
    {
        return $this->shipit->deploymentScript($this->resolveSiteId($this->argument('site')));
    }

    private function explainPrecedence(): void
    {
        $this->line('  ShipIt picks the script for each deployment in this order:');
        $this->line('    1. '.self::PATH.' committed in the repository');
        $this->line("    2. the site's own script in ShipIt");
        $this->line("    3. ShipIt's default script for the site's type");
    }
}
