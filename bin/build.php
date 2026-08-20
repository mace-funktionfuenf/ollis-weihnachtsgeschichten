<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Build;

$root = dirname(__DIR__);

Build::run([
    'contentDir' => $root . '/content',
    'assetsDir' => $root . '/assets',
    'outDir' => $root . '/public',
    'templatesDir' => $root . '/templates',
    'siteUrl' => 'https://www.ollis-weihnachtsgeschichten.de',
    'legacyUrlsFile' => $root . '/data/legacy-urls.txt',
], echoProgress: true);
