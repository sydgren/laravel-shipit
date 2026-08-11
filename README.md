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
| `shipit:script {site?} {--check} {--force} {--print}` | Scaffold or validate `.shipit/deploy.yml` |

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

### Deployment steps in the repository

By default a site's deployment steps are configured in ShipIt. Commit a
`.shipit/deploy.yml` and the repository takes over instead — the steps are then
versioned and reviewed with the code they deploy.

```bash
# Write the file from what the site runs today, so you start from a known-good script
php artisan shipit:script

# Check it before pushing (no API token needed — safe in CI)
php artisan shipit:script --check
```

```yaml
# .shipit/deploy.yml
steps:
  - name: Install Composer Dependencies
    script: composer install --no-dev --optimize-autoloader --no-interaction

  - name: Migrate and warm up
    script: |
      php artisan migrate --force
      php artisan optimize

  - name: Build assets
    critical: false
    script: |
      npm ci
      npm run build
```

Steps run in order, in the new release directory, before the symlink switches.
`critical` defaults to **true** here — a failing step stops the deployment
unless you say otherwise. `enabled: false` keeps a step in the file without
running it. The same `{RELEASE_PATH}`, `{SHARED_PATH}`, `{BASE_PATH}`,
`{PREVIOUS_RELEASE}`, `{BRANCH}`, `{COMMIT}`, `{COMMIT_SHORT}`, `{DOMAIN}` and
`{PHP_VERSION}` variables are available as in the UI.

ShipIt validates the file again when it deploys, and a malformed file fails the
deployment rather than silently falling back to the site's own steps.

### In CI (GitHub Actions)

```yaml
- name: Deploy
  run: php artisan shipit:deploy --watch
  env:
    SHIPIT_TOKEN: ${{ secrets.SHIPIT_TOKEN }}
    SHIPIT_SITE: ${{ vars.SHIPIT_SITE }}
```

Validate a committed deployment script on every pull request:

```yaml
- name: Check deployment script
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
