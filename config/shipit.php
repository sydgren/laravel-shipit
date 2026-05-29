<?php

return [

    /*
    |--------------------------------------------------------------------------
    | ShipIt instance URL
    |--------------------------------------------------------------------------
    |
    | The base URL of your ShipIt installation. The package talks to its
    | REST API under "{url}/api".
    |
    */

    'url' => env('SHIPIT_URL', 'https://shipit.henriknordquist.dk'),

    /*
    |--------------------------------------------------------------------------
    | API token
    |--------------------------------------------------------------------------
    |
    | A personal access token created in ShipIt (Settings → API tokens).
    | Used as a Bearer token on every request. Keep this out of version
    | control — set it via the SHIPIT_TOKEN environment variable.
    |
    */

    'token' => env('SHIPIT_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Default site
    |--------------------------------------------------------------------------
    |
    | The site this project deploys to, identified by either its numeric
    | ShipIt id or its domain. Commands fall back to this when no site is
    | passed on the command line.
    |
    */

    'site' => env('SHIPIT_SITE'),

    /*
    |--------------------------------------------------------------------------
    | Request timeout
    |--------------------------------------------------------------------------
    |
    | Seconds to wait on any single API request before giving up.
    |
    */

    'timeout' => (int) env('SHIPIT_TIMEOUT', 30),

    /*
    |--------------------------------------------------------------------------
    | Watch poll interval
    |--------------------------------------------------------------------------
    |
    | Seconds between polls when following a deployment with --watch.
    |
    */

    'poll_interval' => (int) env('SHIPIT_POLL_INTERVAL', 2),

];
