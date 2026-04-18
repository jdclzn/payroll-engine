#!/usr/bin/env php
<?php

declare(strict_types=1);

$root = dirname(__DIR__);

chdir($root);

[,$skipTests] = parseReleaseArguments($argv);

$steps = [
    [
        'label' => 'Validating composer metadata',
        'command' => 'composer validate --no-check-publish',
    ],
    [
        'label' => 'Refreshing optimized autoload files',
        'command' => 'composer dump-autoload -o',
    ],
];

if (! $skipTests) {
    $steps[] = [
        'label' => 'Running the Pest test suite',
        'command' => escapeshellarg(PHP_BINARY).' vendor/bin/pest',
    ];
}

foreach ($steps as $step) {
    fwrite(STDOUT, PHP_EOL.'> '.$step['label'].PHP_EOL);
    passthru($step['command'], $exitCode);

    if ($exitCode !== 0) {
        fwrite(STDERR, PHP_EOL.'Release checks failed while running: '.$step['command'].PHP_EOL);
        exit($exitCode);
    }
}

$changelogWarning = changelogWarning($root.'/CHANGELOG.md');

fwrite(STDOUT, PHP_EOL.'Release preflight checks passed.'.PHP_EOL);

if ($changelogWarning !== null) {
    fwrite(STDOUT, $changelogWarning.PHP_EOL);
}

/**
 * @param  array<int, string>  $argv
 * @return array{0: string|null, 1: bool}
 */
function parseReleaseArguments(array $argv): array
{
    $version = null;
    $skipTests = false;

    foreach (array_slice($argv, 1) as $argument) {
        if ($argument === '--no-tests') {
            $skipTests = true;
            continue;
        }

        if (str_starts_with($argument, '--version=')) {
            $version = normalizeVersion(substr($argument, strlen('--version=')));
            continue;
        }

        if ($argument !== '') {
            $version = normalizeVersion($argument);
        }
    }

    return [$version, $skipTests];
}

function normalizeVersion(string $version): string
{
    $normalized = ltrim(trim($version), 'v');

    if ($normalized === '') {
        fwrite(STDERR, 'Release version cannot be empty.'.PHP_EOL);
        exit(1);
    }

    if (! preg_match('/^\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?(?:\+[0-9A-Za-z.-]+)?$/', $normalized)) {
        fwrite(STDERR, 'Release version must be a semantic version such as 1.0.0 or 1.0.0-rc.1.'.PHP_EOL);
        exit(1);
    }

    return $normalized;
}

function changelogWarning(string $changelogPath): ?string
{
    if (! is_file($changelogPath)) {
        return 'Warning: CHANGELOG.md was not found. Add release notes before tagging.';
    }

    $contents = (string) file_get_contents($changelogPath);

    if (str_contains($contents, '201X-XX-XX')) {
        return 'Warning: CHANGELOG.md still contains the boilerplate placeholder date.';
    }

    if (! str_contains($contents, '## Unreleased') && ! preg_match('/^##\s+\d+\.\d+\.\d+/m', $contents)) {
        return 'Warning: CHANGELOG.md does not appear to contain a release section yet.';
    }

    return null;
}
