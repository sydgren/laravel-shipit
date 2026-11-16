# ShipIt Deployer

Trigger and monitor [ShipIt](https://shipit.henriknordquist.dk) deployments straight from your Laravel app's Artisan console.

```bash
php artisan shipit:deploy --watch
```

Drop it into any Laravel project, point it at a ShipIt site, and deploy from your terminal or CI pipeline — `--watch` streams the live log and exits non-zero if the deployment fails, so it gates a pipeline cleanly.

## Requirements

- PHP 8.2+
- Laravel 11, 12, or 13

## Installation

```bash
composer require sydgren/laravel-shipit
```

The service provider is auto-discovered. Optionally publish the config:

```bash
php artisan vendor:publish --tag=shipit-config
```

## Configuration

Configure via environment variables (no published config needed):

```dotenv
SHIPIT_TOKEN=your-personal-access-token
SHIPIT_SITE=example.com          # site id or domain this project deploys to
SHIPIT_URL=https://shipit.henriknordquist.dk   # only if self-hosted elsewhere
```

Create a token in ShipIt under **Settings → API tokens**. `SHIPIT_SITE` is the
default site every command targets when you don't pass one explicitly — set it
once per project and `php artisan shipit:deploy` just works.

## Commands

| Command | Description |
|---------|-------------|
| `shipit:deploy {site?} {--branch=} {--watch} {--no-wait}` | Trigger a deployment |
| `shipit:status {site?}` | Latest deployment status for a site |
| `shipit:logs {deployment?} {--site=} {--watch}` | Show / follow deployment output |
| `shipit:deployments {site?} {--limit=10}` | List recent deployments |
| `shipit:rollback {deployment}` | Roll back to a previous successful deployment |
| `shipit:sites` | List all sites on your account (handy for finding ids) |
| `shipit:script {site?} {--check} {--force} {--print}` | Scaffold `.shipit/deploy.sh`, or check which deploy script ShipIt runs |

`{site}` accepts either a numeric ShipIt id or a domain/name. When omitted,
commands fall back to `SHIPIT_SITE`.

### Examples

```bash
# Deploy the configured site and watch the log (exits non-zero on failure)
php artisan shipit:deploy --watch

# Deploy a specific branch of a specific site, don't block
php artisan shipit:deploy example.com --branch=staging --no-wait

# Tail the latest deployment's log
php artisan shipit:logs --watch

# Roll back
php artisan shipit:deployments      # find the id of a good release
php artisan shipit:rollback 1234
```

### The deploy script in the repository

A site's deploy is one bash script. On every deployment ShipIt picks it in this
order:

1. `.shipit/deploy.sh` committed in the repository
2. the site's own script, stored in ShipIt
3. ShipIt's default script for the site's type

Commit a `.shipit/deploy.sh` and the script is versioned and reviewed with the
code it deploys.

```bash
# Write .shipit/deploy.sh from the script the site runs today (refuses to overwrite without --force)
php artisan shipit:script

# Which script does the next deployment run? With a .shipit/deploy.sh present this
# also syntax-checks it with `bash -n`, and needs no API token — safe in CI.
php artisan shipit:script --check

# Print the site's script from ShipIt, e.g. to diff it against the committed one
php artisan shipit:script --print | diff - .shipit/deploy.sh
```

ShipIt runs the script with bash under `set -eo pipefail`, in the new release
directory, before the release goes live — a failing command stops the
deployment and the previous release keeps serving. Append `|| true` to a
command that may fail. These variables are exported: `RELEASE_PATH`,
`SHARED_PATH`, `BASE_PATH`, `PREVIOUS_RELEASE`, `BRANCH`, `COMMIT`,
`COMMIT_SHORT`, `DOMAIN` and `PHP_VERSION`.

```bash
#!/usr/bin/env bash
# .shipit/deploy.sh
rm -rf "$RELEASE_PATH/storage"
ln -nfs "$SHARED_PATH/storage" "$RELEASE_PATH/storage"
ln -nfs "$SHARED_PATH/.env" "$RELEASE_PATH/.env"

composer install --no-dev --optimize-autoloader --no-interaction
php artisan migrate --force
php artisan optimize

npm ci
npm run build
```

`.shipit/deploy.yml` is no longer read by ShipIt (removed in ShipIt 2.1.0).
Move its steps into `.shipit/deploy.sh` and delete it.

### In CI (GitHub Actions)

```yaml
- name: Deploy
  run: php artisan shipit:deploy --watch
  env:
    SHIPIT_TOKEN: ${{ secrets.SHIPIT_TOKEN }}
    SHIPIT_SITE: ${{ vars.SHIPIT_SITE }}
```

Syntax-check a committed `.shipit/deploy.sh` on every pull request (no token needed):

```yaml
- name: Check deploy script
  run: php artisan shipit:script --check
```

## Programmatic use

The `ShipIt` facade exposes the same API client the commands use:

```php
use Sydgren\ShipIt\Facades\ShipIt;

$siteId = ShipIt::resolveSiteId('example.com');
$deployment = ShipIt::deploy($siteId, branch: 'main')['deployment'];
$status = ShipIt::output($deployment['id'])['status'];
```

## Testing

```bash
composer install
vendor/bin/pest
```

## License

MIT
