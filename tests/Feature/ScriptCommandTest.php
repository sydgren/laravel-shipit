<?php

use Illuminate\Support\Facades\Http;
use Sydgren\ShipIt\DeployFile;

function deployFilePath(): string
{
    return base_path(DeployFile::PATH);
}

function writeDeployFile(string $contents): void
{
    $path = deployFilePath();

    if (! is_dir($directory = dirname($path))) {
        mkdir($directory, 0755, true);
    }

    file_put_contents($path, $contents);
}

function fakeScaffoldEndpoint(string $contents): void
{
    Http::fake([
        'shipit.test/api/sites/5/deployment-steps/scaffold' => Http::response([
            'path' => DeployFile::PATH,
            'contents' => $contents,
        ]),
    ]);
}

afterEach(function () {
    if (is_file($path = deployFilePath())) {
        unlink($path);
        @rmdir(dirname($path));
    }
});

const VALID_FILE = <<<'YAML'
steps:
  - name: Install Composer Dependencies
    script: |
      composer install --no-dev -o
  - name: Migrate
    critical: false
    script: |
      php artisan migrate --force
YAML;

it('writes the file from the site steps', function () {
    fakeScaffoldEndpoint(VALID_FILE."\n");

    $this->artisan('shipit:script', ['site' => '5'])
        ->expectsOutputToContain(DeployFile::PATH)
        ->assertSuccessful();

    expect(file_get_contents(deployFilePath()))->toContain('Install Composer Dependencies');
});

it('refuses to overwrite an existing file without --force', function () {
    fakeScaffoldEndpoint(VALID_FILE."\n");
    writeDeployFile("steps:\n  - name: Mine\n    script: 'true'\n");

    $this->artisan('shipit:script', ['site' => '5'])
        ->expectsOutputToContain('already exists')
        ->assertFailed();

    expect(file_get_contents(deployFilePath()))->toContain('Mine');
});

it('overwrites with --force', function () {
    fakeScaffoldEndpoint(VALID_FILE."\n");
    writeDeployFile("steps:\n  - name: Mine\n    script: 'true'\n");

    $this->artisan('shipit:script', ['site' => '5', '--force' => true])->assertSuccessful();

    expect(file_get_contents(deployFilePath()))->toContain('Install Composer Dependencies');
});

it('prints the scaffold without touching the filesystem', function () {
    fakeScaffoldEndpoint(VALID_FILE."\n");

    $this->artisan('shipit:script', ['site' => '5', '--print' => true])
        ->expectsOutputToContain('Install Composer Dependencies')
        ->assertSuccessful();

    expect(is_file(deployFilePath()))->toBeFalse();
});

it('passes --check on a valid file, listing its steps', function () {
    writeDeployFile(VALID_FILE."\n");

    $this->artisan('shipit:script', ['--check' => true])
        ->expectsOutputToContain('2 step(s)')
        ->expectsOutputToContain('Migrate')
        ->assertSuccessful();
});

it('fails --check when the file is missing', function () {
    $this->artisan('shipit:script', ['--check' => true])
        ->expectsOutputToContain('does not exist')
        ->assertFailed();
});

it('reports every problem in one pass', function () {
    writeDeployFile(<<<'YAML'
    steps:
      - name: Missing script
      - name: Bad flags
        script: 'true'
        critical: yep
        criticl: true
    YAML);

    $this->artisan('shipit:script', ['--check' => true])
        ->expectsOutputToContain('Step 1: `script` is required')
        ->expectsOutputToContain('Step 2: `critical` must be true or false')
        ->expectsOutputToContain('unknown key(s) criticl')
        ->assertFailed();
});

it('rejects invalid YAML', function () {
    writeDeployFile("steps:\n  - name: X\n   script: bad indent\n");

    $this->artisan('shipit:script', ['--check' => true])
        ->expectsOutputToContain('Not valid YAML')
        ->assertFailed();
});

it('rejects a file with no steps', function () {
    writeDeployFile("steps: []\n");

    $this->artisan('shipit:script', ['--check' => true])
        ->expectsOutputToContain('non-empty list')
        ->assertFailed();
});

it('needs no API token to run --check', function () {
    config()->set('shipit.token', null);
    writeDeployFile(VALID_FILE."\n");

    $this->artisan('shipit:script', ['--check' => true])->assertSuccessful();
});
