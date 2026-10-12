<?php
/**
 * iERA App configuration.
 *
 * You normally do not need to create this file by hand: open the application URL and the
 * setup wizard writes it for you. To prepare it manually, copy it to config/config.php and
 * fill in the database details. config/config.php is ignored by git and must never be committed.
 *
 * Email delivery, Stripe, SharePoint and the scheduler key are configured in
 * System Admin → Settings after installation (stored encrypted in the database).
 */
return [
    'app' => [
        'name'     => 'iERA App',
        // Public URL of the application, no trailing slash (the wizard detects it)
        'url'      => 'https://app.example.org',
        // production | staging | local
        'env'      => 'production',
        'debug'    => false,
        'timezone' => 'America/Toronto',
        // 64 random hex characters; generated automatically by the setup wizard
        'key'      => 'CHANGE-ME-TO-64-RANDOM-HEX-CHARACTERS',
        // Idle session lifetime in minutes
        'session_minutes' => 120,
    ],

    'db' => [
        'driver'  => 'mysql',
        'host'    => 'localhost',
        'port'    => 3306,
        'name'    => 'cpuser_iera',
        'user'    => 'cpuser_iera',
        'pass'    => '',
        'charset' => 'utf8mb4',
    ],
];
