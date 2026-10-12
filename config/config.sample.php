<?php
/**
 * iERA App configuration.
 * Copy this file to config/config.php on the server and fill in the values.
 * config/config.php is ignored by git and must never be committed.
 */
return [
    'app' => [
        'name'     => 'iERA App',
        // Public URL of the application, no trailing slash
        'url'      => 'https://app.example.org',
        // production | staging | local
        'env'      => 'production',
        'debug'    => false,
        'timezone' => 'America/Toronto',
        // 64 random hex characters: php -r "echo bin2hex(random_bytes(32));"
        'key'      => 'CHANGE-ME-TO-64-RANDOM-HEX-CHARACTERS',
        // Idle session lifetime in minutes
        'session_minutes' => 120,
    ],

    'db' => [
        // mysql (production) | sqlite (local smoke tests only)
        'driver'  => 'mysql',
        'host'    => 'localhost',
        'port'    => 3306,
        'name'    => 'cpuser_iera',
        'user'    => 'cpuser_iera',
        'pass'    => '',
        'charset' => 'utf8mb4',
        // Only for driver=sqlite
        'path'    => __DIR__ . '/../storage/iera.sqlite',
    ],

    'mail' => [
        // sendgrid | smtp | mail | log
        'driver'     => 'log',
        'from_email' => 'no-reply@example.org',
        'from_name'  => 'iERA',
        'sendgrid_api_key' => '',
        'smtp' => [
            'host' => 'localhost',
            'port' => 587,
            'user' => '',
            'pass' => '',
            // tls | ssl | none
            'encryption' => 'tls',
        ],
    ],

    'stripe' => [
        'enabled'        => false,
        'secret_key'     => '',
        'webhook_secret' => '',
    ],

    'sharepoint' => [
        'enabled'       => false,
        'tenant_id'     => '',
        'client_id'     => '',
        'client_secret' => '',
        'street_dawah_site_id' => '',
        'street_dawah_list_id' => '',
        'nm_tracker_site_id'   => '',
        'nm_tracker_list_id'   => '',
    ],

    'cron' => [
        // Optional secret allowing GET /cron/run?key=... as a fallback for hosts without cron
        'secret' => '',
    ],
];
