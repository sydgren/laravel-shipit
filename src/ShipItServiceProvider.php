<?php

namespace Sydgren\ShipIt;

use Sydgren\ShipIt\Client\ShipItClient;
use Sydgren\ShipIt\Commands\DeployCommand;
use Sydgren\ShipIt\Commands\DeploymentsCommand;
use Sydgren\ShipIt\Commands\LogsCommand;
use Sydgren\ShipIt\Commands\RollbackCommand;
use Sydgren\ShipIt\Commands\ScriptCommand;
use Sydgren\ShipIt\Commands\SitesCommand;
use Sydgren\ShipIt\Commands\StatusCommand;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\ServiceProvider;

class ShipItServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/shipit.php', 'shipit');

        $this->app->singleton(ShipItClient::class, function ($app): ShipItClient {
            $config = $app['config']->get('shipit');

            return new ShipItClient(
                $app->make(Factory::class),
                $config['url'],
                $config['token'] ?? null,
                (int) ($config['timeout'] ?? 30),
            );
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/shipit.php' => $this->app->configPath('shipit.php'),
            ], 'shipit-config');

            $this->commands([
                DeployCommand::class,
                StatusCommand::class,
                LogsCommand::class,
                RollbackCommand::class,
                DeploymentsCommand::class,
                SitesCommand::class,
                ScriptCommand::class,
            ]);
        }
    }
}
