<?php

namespace Sydgren\ShipIt\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Sydgren\ShipIt\ShipItServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [ShipItServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('shipit.url', 'https://shipit.test');
        $app['config']->set('shipit.token', 'test-token');
        $app['config']->set('shipit.site', null);
        $app['config']->set('shipit.poll_interval', 0);
    }
}
