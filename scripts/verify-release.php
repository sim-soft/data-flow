<?php

/**
 * Verifies a published release by installing it from Packagist the way a
 * consumer would, then exercising it.
 *
 * The test suite runs against the working tree with dev dependencies pinned, so
 * it cannot see how Composer resolves for a user, what the package archive
 * actually contains, or bugs that only appear in an installed copy. This can.
 *
 * Usage:
 *   php scripts/verify-release.php 3.0.1
 *   php scripts/verify-release.php 3.0.1 --keep    (leave the temp dir behind)
 *
 * Exits non-zero if any check fails.
 *
 * @see RELEASING.md
 */

declare(strict_types=1);

$version = $argv[1] ?? null;

if ($version === null || in_array($version, ['-h', '--help'], true)) {
    fwrite(STDERR, "Usage: php scripts/verify-release.php <version> [--keep]\n");
    exit($version === null ? 1 : 0);
}

$keep = in_array('--keep', $argv, true);
$dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'data-flow-verify-' . bin2hex(random_bytes(4));

$failures = [];
$checks = 0;

/**
 * Record the outcome of a single check.
 */
$check = function (string $label, bool $passed, string $detail = '') use (&$failures, &$checks): void {
    $checks++;
    printf("  [%s] %s%s\n", $passed ? 'ok  ' : 'FAIL', $label, $detail === '' ? '' : " — {$detail}");

    if (!$passed) {
        $failures[] = $label;
    }
};

/**
 * Run a command in the sandbox directory, returning [exitCode, output].
 *
 * @return array{0: int, 1: string}
 */
$run = function (string $command) use ($dir): array {
    $output = [];
    $exit = 0;
    exec('cd ' . escapeshellarg($dir) . ' && ' . $command . ' 2>&1', $output, $exit);

    return [$exit, implode("\n", $output)];
};

echo "Verifying simsoft/data-flow {$version} from Packagist\n";
echo "Sandbox: {$dir}\n\n";

mkdir($dir, 0777, true);

// Require the exact published version, and let the optional packages resolve
// unconstrained — that is precisely what broke in 3.0.0.
file_put_contents($dir . '/composer.json', json_encode([
    'require' => [
        'simsoft/data-flow' => $version,
        'openspout/openspout' => '*',
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

echo "Installing...\n";
[$exit, $installOutput] = $run('composer update --no-interaction --prefer-dist --no-progress');
$check('installs from Packagist', $exit === 0, $exit === 0 ? '' : trim(substr($installOutput, -300)));

if ($exit !== 0) {
    fwrite(STDERR, "\nInstall failed; cannot continue.\n{$installOutput}\n");
    exit(1);
}

// Report what Composer actually resolved. "It installed" is not the same as
// "it installed something compatible".
$lock = json_decode((string) file_get_contents($dir . '/composer.lock'), true);
$resolved = [];

foreach (($lock['packages'] ?? []) as $package) {
    $resolved[$package['name']] = $package['version'];
}

echo "\nResolved:\n";
foreach ($resolved as $name => $resolvedVersion) {
    printf("  %-28s %s\n", $name, $resolvedVersion);
}

echo "\nChecks:\n";

$check(
    'data-flow is the requested version',
    ($resolved['simsoft/data-flow'] ?? null) === $version,
    'got ' . ($resolved['simsoft/data-flow'] ?? 'nothing')
);

// OpenSpout 5 removed the Creator factories SpoutIO calls. The conflict block in
// composer.json should keep resolution on v4.
$spout = $resolved['openspout/openspout'] ?? '';
$check(
    'OpenSpout resolved below v5',
    $spout !== '' && version_compare(ltrim($spout, 'v'), '5.0.0', '<'),
    $spout === '' ? 'not installed' : $spout
);

// Packaging: dev-only directories must not ship.
foreach (['tests', 'docs', '.github'] as $excluded) {
    $check(
        "archive excludes {$excluded}/",
        !is_dir($dir . '/vendor/simsoft/data-flow/' . $excluded)
    );
}

// Behaviour: a dry run must leave the filesystem untouched, and a real run must
// still write. Both regressed in 3.0.0.
file_put_contents($dir . '/exercise.php', <<<'PHP'
<?php
require __DIR__ . '/vendor/autoload.php';

use Simsoft\DataFlow\DataFlow;
use Simsoft\DataFlow\Loaders\SpoutLoader;

$out = __DIR__ . '/out';
is_dir($out) || mkdir($out, 0777, true);
array_map('unlink', (array) glob($out . '/*'));

$row = ['Sheet1' => ['name' => 'Ada', 'age' => 36]];

(new DataFlow())->from($row)->load(new SpoutLoader($out . '/r.xlsx'))->dryRun()->run();
$afterDryRun = count((array) glob($out . '/*'));

(new DataFlow())->from($row)->load(new SpoutLoader($out . '/r.xlsx'))->run();
$written = (array) glob($out . '/*.xlsx');

echo json_encode([
    'dryRunFiles' => $afterDryRun,
    'realRunFiles' => count($written),
    'bytes' => $written ? filesize($written[0]) : 0,
]), "\n";
PHP);

[$exit, $exerciseOutput] = $run('php exercise.php');
$result = json_decode(trim((string) strrchr("\n" . $exerciseOutput, "\n")), true);

if ($exit !== 0 || !is_array($result)) {
    // Surface the first line of the error — a truncated stack trace tail is
    // unreadable, and the message is what identifies the problem.
    $firstLine = trim(strtok(trim($exerciseOutput), "\n") ?: 'no output');
    $check('pipeline runs', false, $firstLine);
    echo "\n--- pipeline output ---\n" . trim($exerciseOutput) . "\n-----------------------\n";
} else {
    $check('pipeline runs', true);
    $check('dry run leaves no file', $result['dryRunFiles'] === 0, "{$result['dryRunFiles']} file(s)");
    $check('real run writes output', $result['realRunFiles'] === 1 && $result['bytes'] > 0, "{$result['bytes']} bytes");
}

if ($keep) {
    echo "\nSandbox kept at {$dir}\n";
} else {
    exec((stripos(PHP_OS_FAMILY, 'Windows') === 0 ? 'rmdir /s /q ' : 'rm -rf ') . escapeshellarg($dir) . ' 2>&1');
}

$passed = $checks - count($failures);
printf("\n%d/%d checks passed.\n", $passed, $checks);

if ($failures !== []) {
    echo "Failed: " . implode(', ', $failures) . "\n";
    echo "Do not delete the published tag — fix forward with a patch release.\n";
    exit(1);
}

echo "Release {$version} verified.\n";
