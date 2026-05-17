<?php
/**
 * Generate test fixture files for the unit test suite.
 *
 * Run: php tests/fixtures/generate_fixtures.php
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$fixturesDir = __DIR__;

// --- 1. Generate sample.xlsx with 2 sheets: "Profile" and "Address" ---

$spreadsheet = new Spreadsheet();

// Profile sheet (first sheet, already created)
$profileSheet = $spreadsheet->getActiveSheet();
$profileSheet->setTitle('Profile');
$profileSheet->fromArray(
    [
        ['id', 'name', 'email'],
        [1, 'Alice', 'alice@example.com'],
        [2, 'Bob', 'bob@example.com'],
        [3, 'Charlie', 'charlie@example.com'],
    ],
    null,
    'A1'
);

// Address sheet
$addressSheet = $spreadsheet->createSheet();
$addressSheet->setTitle('Address');
$addressSheet->fromArray(
    [
        ['id', 'city', 'country'],
        [1, 'New York', 'USA'],
        [2, 'London', 'UK'],
        [3, 'Tokyo', 'Japan'],
    ],
    null,
    'A1'
);

$writer = new Xlsx($spreadsheet);
$writer->save($fixturesDir . '/sample.xlsx');
echo "Created: sample.xlsx\n";

// --- 2. Generate sample.csv ---

$csvFile = fopen($fixturesDir . '/sample.csv', 'w');
fputcsv($csvFile, ['id', 'name', 'email', 'age', 'city']);
fputcsv($csvFile, [1, 'Alice', 'alice@example.com', 30, 'New York']);
fputcsv($csvFile, [2, 'Bob', 'bob@example.com', 25, 'London']);
fputcsv($csvFile, [3, 'Charlie', 'charlie@example.com', 35, 'Tokyo']);
fputcsv($csvFile, [4, 'Diana', 'diana@example.com', 28, 'Paris']);
fputcsv($csvFile, [5, 'Eve', 'eve@example.com', 32, 'Berlin']);
fclose($csvFile);
echo "Created: sample.csv\n";

// --- 3. Generate empty.xlsx with a single empty sheet ---

$emptySpreadsheet = new Spreadsheet();
$emptySheet = $emptySpreadsheet->getActiveSheet();
$emptySheet->setTitle('Sheet1');

$emptyWriter = new Xlsx($emptySpreadsheet);
$emptyWriter->save($fixturesDir . '/empty.xlsx');
echo "Created: empty.xlsx\n";

echo "\nAll fixture files generated successfully.\n";
