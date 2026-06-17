#!/usr/bin/env php
<?php

require dirname(__DIR__).'/vendor/autoload.php';

use GCWorld\ObjectManager\DocblockMigrator;

$arguments = $argv;
array_shift($arguments);

$dryRun = false;
$paths = [];
foreach ($arguments as $argument) {
    if ($argument === '--dry-run') {
        $dryRun = true;
        continue;
    }

    $paths[] = $argument;
}

if ($paths === []) {
    fwrite(STDERR, "Usage: php bin/migrate-docblocks.php [--dry-run] <path> [<path> ...]\n");
    exit(1);
}

$migrator = new DocblockMigrator();

try {
    $result = $migrator->migratePaths($paths, $dryRun);
} catch (Throwable $throwable) {
    fwrite(STDERR, $throwable->getMessage()."\n");
    exit(1);
}

foreach ($result['warnings'] as $warning) {
    fwrite(STDERR, "Warning: ".$warning."\n");
}

$message = sprintf(
    "%s %d PHP files. Updated %d, skipped %d.\n",
    $dryRun ? 'Scanned' : 'Processed',
    $result['processed'],
    $result['updated'],
    $result['skipped']
);
fwrite(STDOUT, $message);
