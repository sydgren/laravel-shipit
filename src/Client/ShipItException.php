<?php

namespace Sydgren\ShipIt\Client;

use RuntimeException;

class ShipItException extends RuntimeException
{
    public static function notConfigured(): self
    {
        return new self(
            'ShipIt is not configured. Set SHIPIT_TOKEN (and optionally SHIPIT_URL) in your environment.'
        );
    }

    public static function noSite(): self
    {
        return new self(
            'No site given and SHIPIT_SITE is not set. Pass a site id or domain, or set SHIPIT_SITE.'
        );
    }

    public static function siteNotFound(string $identifier): self
    {
        return new self("No site matching \"{$identifier}\" was found on this ShipIt account.");
    }

    public static function fromResponse(int $status, ?string $message): self
    {
        return new self(trim(($message ?: 'Request failed').' (HTTP '.$status.')'));
    }
}
