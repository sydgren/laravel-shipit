<?php

use Illuminate\Support\Facades\Http;
use Sydgren\ShipIt\Commands\ScriptCommand;

const SITE_SCRIPT = <<<'BASH'
# --- Install Composer Dependencies ---
composer install --no-dev --optimize-autoloader --no-interaction

# --- Run Migrations ---
php artisan migrate --force

BASH;

function deployScriptPath(): string
{
    return base_path(ScriptCommand::PATH);
}

function writeDeployScript(string $contents): void
{
    $path = deployScriptPath();

    if (! is_dir($directory = dirname($path))) {
        mkdir($directory, 0755, true);
    }

    file_put_contents($path, $contents);
}

function fakeSiteScript(string $script = SITE_SCRIPT, bool $isDefault = false): void
{
    Http::fake([
        'shipit.test/api/sites/5/deployment-script' => Http::response([
            'script' => $script,
            'is_default' => $isDefault,
        ]),
    ]);
}

afterEach(function () {
    if (is_file($path = deployScriptPath())) {
        unlink($path);
        @rmdir(dirname($path));
    }
});

it('writes an executable .shipit/deploy.sh from the site script and explains the precedence', function () {
    fakeSiteScript();

    $this->artisan('shipit:script', ['site' => '5'])
        ->expectsOutputToContain("Wrote .shipit/deploy.sh from the site's own script in ShipIt.")
        ->expectsOutputToContain('1. .shipit/deploy.sh committed in the repository')
        ->expectsOutputToContain("2. the site's own script in ShipIt")
        ->expectsOutputToContain("3. ShipIt's default script for the site's type")
        ->assertSuccessful();

    $contents = file_get_contents(deployScriptPath());

    expect($contents)->toStartWith("#!/usr/bin/env bash\n")
        ->and($contents)->toEndWith("\n\n".SITE_SCRIPT)
        ->and(is_executable(deployScriptPath()))->toBeTrue();

    Http::assertSent(fn ($request) => $request->method() === 'GET'
        && str_ends_with($request->url(), '/api/sites/5/deployment-script'));
});

it('says when the scaffold is ShipIt\'s default script', function () {
    fakeSiteScript(isDefault: true);

    $this->artisan('shipit:script', ['site' => '5'])
        ->expectsOutputToContain("from ShipIt's default script for the site's type")
        ->assertSuccessful();
});

it('keeps a script that brings its own shebang as it is', function () {
    fakeSiteScript("#!/bin/bash\nmake deploy\n");

    $this->artisan('shipit:script', ['site' => '5'])->assertSuccessful();

    expect(file_get_contents(deployScriptPath()))->toBe("#!/bin/bash\nmake deploy\n");
});

it('refuses to overwrite an existing script without --force', function () {
    Http::fake();
    writeDeployScript("echo mine\n");

    $this->artisan('shipit:script', ['site' => '5'])
        ->expectsOutputToContain('already exists')
        ->assertFailed();

    expect(file_get_contents(deployScriptPath()))->toBe("echo mine\n");
    Http::assertNothingSent();
});

it('overwrites with --force', function () {
    fakeSiteScript();
    writeDeployScript("echo mine\n");

    $this->artisan('shipit:script', ['site' => '5', '--force' => true])->assertSuccessful();

    expect(file_get_contents(deployScriptPath()))->toContain('composer install');
});

it('prints the site script without touching the filesystem', function () {
    fakeSiteScript();

    $this->artisan('shipit:script', ['site' => '5', '--print' => true])
        ->expectsOutputToContain('php artisan migrate --force')
        ->assertSuccessful();

    expect(is_file(deployScriptPath()))->toBeFalse();
});

it('checks that the repository script wins, without a token or a request', function () {
    config()->set('shipit.token', null);
    Http::fake();
    writeDeployScript("#!/usr/bin/env bash\ncomposer install\n");

    $this->artisan('shipit:script', ['--check' => true])
        ->expectsOutputToContain('Deployments run .shipit/deploy.sh from this repository.')
        ->assertSuccessful();

    Http::assertNothingSent();
});

it('fails the check when the repository script does not parse', function () {
    writeDeployScript("if true; then\n  echo missing fi\n");

    $this->artisan('shipit:script', ['--check' => true])
        ->expectsOutputToContain('does not parse')
        ->expectsOutputToContain('.shipit/deploy.sh: line')
        ->assertFailed();
});

it('checks that the site\'s own script runs when the repository has none', function () {
    fakeSiteScript();

    $this->artisan('shipit:script', ['site' => '5', '--check' => true])
        ->expectsOutputToContain("Deployments run the site's own script in ShipIt")
        ->assertSuccessful();

    expect(is_file(deployScriptPath()))->toBeFalse();
});

it('checks that ShipIt\'s default runs when neither the repository nor the site has a script', function () {
    fakeSiteScript(isDefault: true);

    $this->artisan('shipit:script', ['site' => '5', '--check' => true])
        ->expectsOutputToContain("Deployments run ShipIt's default script for the site's type")
        ->assertSuccessful();
});
