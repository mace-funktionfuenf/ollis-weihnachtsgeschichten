<?php

declare(strict_types=1);

/**
 * Copy this file to config.php (gitignored) and fill in real values.
 *
 * Local dev: leave 'driver' => 'sqlite' — no MySQL install needed.
 * Production (Hetzner): set 'driver' => 'mysql' and fill in the credentials
 * from the hosting control panel.
 */
return [
    'db' => [
        'driver' => 'sqlite',
        'sqlite_path' => __DIR__ . '/var/admin.sqlite',

        // Only used when driver = 'mysql':
        'mysql_host' => 'localhost',
        'mysql_name' => '',
        'mysql_user' => '',
        'mysql_pass' => '',
    ],

    'session_name' => 'ollis_admin',

    'paths' => [
        'contentDir' => __DIR__ . '/content',
        'assetsDir' => __DIR__ . '/assets',
        'outDir' => __DIR__ . '/public',
        'templatesDir' => __DIR__ . '/templates',
        'legacyUrlsFile' => __DIR__ . '/data/legacy-urls.txt',
    ],

    'siteUrl' => 'https://www.ollis-weihnachtsgeschichten.de',
];
