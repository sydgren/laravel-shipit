<?php

namespace Sydgren\ShipIt\Facades;

use Sydgren\ShipIt\Client\ShipItClient;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Facade;

/**
 * @method static int resolveSiteId(int|string $identifier)
 * @method static Collection servers()
 * @method static Collection sites()
 * @method static array site(int $siteId)
 * @method static array deploy(int $siteId, ?string $branch = null)
 * @method static array deployment(int $deploymentId)
 * @method static array output(int $deploymentId)
 * @method static Collection deployments(int $siteId)
 * @method static array rollback(int $deploymentId)
 * @method static bool isRunning(string $status)
 * @method static bool isSuccessful(string $status)
 *
 * @see \Sydgren\ShipIt\Client\ShipItClient
 */
class ShipIt extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return ShipItClient::class;
    }
}
