<?php

/**
 * Fails the build if line coverage drops below a floor.
 *
 * PHPUnit can report coverage but cannot fail on a minimum, so this reads the
 * clover report `composer coverage` produces.
 *
 * The floor is a ratchet, not a target: it is set at the coverage that existed
 * when it was introduced, so it catches regressions without demanding new tests
 * for untouched code. Raise it when coverage genuinely improves; do not lower it
 * to make a build pass.
 *
 * Usage:
 *   php scripts/coverage-check.php [clover.xml] [--min=90.0]
 *
 * @see RELEASING.md
 */

declare(strict_types=1);

$file = 'coverage.xml';

// Measured at 90.20% when this was added. Note that a missing extension (zip,
// gd) makes spreadsheet tests error out and drags the figure down by ~2.5
// points, so a local run below this usually means a driver or extension is
// missing rather than a real regression.
$min = 90.0;

foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--min=')) {
        $min = (float) substr($arg, 6);
        continue;
    }

    if (in_array($arg, ['-h', '--help'], true)) {
        fwrite(STDERR, "Usage: php scripts/coverage-check.php [clover.xml] [--min=90.0]\n");
        exit(0);
    }

    $file = $arg;
}

if (!is_file($file)) {
    fwrite(STDERR, "Coverage report not found: {$file}\n");
    fwrite(STDERR, "Run `composer coverage` first (needs the pcov or xdebug extension).\n");
    exit(1);
}

$xml = @simplexml_load_file($file);

if ($xml === false) {
    fwrite(STDERR, "Could not parse coverage report: {$file}\n");
    exit(1);
}

$metrics = $xml->project->metrics ?? null;

if ($metrics === null) {
    fwrite(STDERR, "No project metrics in {$file}\n");
    exit(1);
}

$statements = (int) $metrics['statements'];
$covered = (int) $metrics['coveredstatements'];
$methods = (int) $metrics['methods'];
$coveredMethods = (int) $metrics['coveredmethods'];

if ($statements === 0) {
    fwrite(STDERR, "Coverage report contains no statements — did the run collect coverage?\n");
    exit(1);
}

$lineCoverage = $covered / $statements * 100;
$methodCoverage = $methods > 0 ? $coveredMethods / $methods * 100 : 0.0;

printf("Lines:   %6.2f%% (%d/%d)\n", $lineCoverage, $covered, $statements);
printf("Methods: %6.2f%% (%d/%d)\n", $methodCoverage, $coveredMethods, $methods);
printf("Minimum: %6.2f%%\n\n", $min);

if ($lineCoverage + 0.005 < $min) {
    printf("FAIL: line coverage %.2f%% is below the %.2f%% floor.\n", $lineCoverage, $min);
    echo "Add tests for the new code, or justify lowering the floor in the PR.\n";
    exit(1);
}

printf("OK: line coverage %.2f%% meets the %.2f%% floor.\n", $lineCoverage, $min);
