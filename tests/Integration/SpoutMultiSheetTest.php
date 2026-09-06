<?php

namespace Simsoft\DataFlow\Tests\Integration;

use Generator;
use OpenSpout\Reader\XLSX\Options as XLSXReaderOptions;
use OpenSpout\Reader\XLSX\Reader as XLSXReader;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use Simsoft\DataFlow\DataFlow;
use Simsoft\DataFlow\Loaders\SpoutLoader;
use Simsoft\DataFlow\Tests\TestCase;

/**
 * SpoutMultiSheetTest class.
 *
 * End-to-end coverage for the multi-sheet example in 02-USEFUL_PROCESSORS:
 * a source that yields rows keyed by worksheet name writes one worksheet per key.
 *
 * This needed two fixes. The executor discarded row keys, so every row arrived
 * under the same key; and SpoutIO tested the writer against
 * OpenSpout\Writer\WriterMultiSheetsAbstract, a class OpenSpout 4 renamed, so
 * the sheet-switching branch was unreachable and failed silently.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
#[CoversNothing]
class SpoutMultiSheetTest extends TestCase
{
    /** @var string Temp directory for output files. */
    private string $tempDir;

    /** @var string[] Glob patterns to clean up. */
    private array $tempFiles = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'tmp';
        if (!is_dir($this->tempDir)) {
            mkdir($this->tempDir, 0777, true);
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $pattern) {
            foreach (glob($pattern) ?: [] as $file) {
                if (is_file($file)) {
                    @unlink($file);
                }
            }
        }

        parent::tearDown();
    }

    /**
     * Read every sheet of a written workbook into a name => rows map.
     *
     * @param string $pattern Glob pattern matching the timestamped output file.
     * @return array<string, array<int, array<int, mixed>>>
     */
    private function readWorkbook(string $pattern): array
    {
        $files = glob($pattern) ?: [];
        $this->assertCount(1, $files, 'Expected exactly one output file.');

        $reader = new XLSXReader(new XLSXReaderOptions());
        $reader->open($files[0]);

        $sheets = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            $rows = [];
            foreach ($sheet->getRowIterator() as $row) {
                $rows[] = $row->toArray();
            }
            $sheets[$sheet->getName()] = $rows;
        }

        $reader->close();

        return $sheets;
    }

    /**
     * Source data keyed by worksheet name, as documented in 02-USEFUL_PROCESSORS.
     *
     * @return Generator<string, array<string, mixed>>
     */
    private function sheetedSource(): Generator
    {
        yield 'Profile' => ['name' => 'John Doe', 'age' => 20, 'gender' => 'male'];
        yield 'Profile' => ['name' => 'Jane Doe', 'age' => 21, 'gender' => 'female'];
        yield 'Address' => ['street' => '123 Main St', 'city' => 'Anytown'];
        yield 'Address' => ['street' => '456 Main St', 'city' => 'Anytown'];
    }

    #[Test]
    public function keyedRowsAreWrittenToSeparateWorksheets(): void
    {
        $base = $this->tempDir . DIRECTORY_SEPARATOR . 'multi_sheet';
        $this->tempFiles[] = $base . '*';

        (new DataFlow())
            ->from($this->sheetedSource())
            ->load(new SpoutLoader($base . '.xlsx'))
            ->run();

        $sheets = $this->readWorkbook($base . '*.xlsx');

        // No stray default sheet: exactly the two sheets the keys named.
        $this->assertSame(['Profile', 'Address'], array_keys($sheets));

        $this->assertSame([
            ['name', 'age', 'gender'],
            ['John Doe', 20, 'male'],
            ['Jane Doe', 21, 'female'],
        ], $sheets['Profile']);

        // Address rows keep their own columns rather than being padded to
        // align with the Profile header.
        $this->assertSame([
            ['street', 'city'],
            ['123 Main St', 'Anytown'],
            ['456 Main St', 'Anytown'],
        ], $sheets['Address']);
    }

    #[Test]
    public function unkeyedRowsFallBackToASingleDefaultSheet(): void
    {
        $base = $this->tempDir . DIRECTORY_SEPARATOR . 'single_sheet';
        $this->tempFiles[] = $base . '*';

        (new DataFlow())
            ->from([
                ['name' => 'John Doe', 'age' => 20],
                ['name' => 'Jane Doe', 'age' => 21],
            ])
            ->load(new SpoutLoader($base . '.xlsx'))
            ->run();

        $sheets = $this->readWorkbook($base . '*.xlsx');

        $this->assertSame(['Sheet1'], array_keys($sheets));
        $this->assertSame([
            ['name', 'age'],
            ['John Doe', 20],
            ['Jane Doe', 21],
        ], $sheets['Sheet1']);
    }

    #[Test]
    public function customHeadersApplyToTheNamedSheet(): void
    {
        $base = $this->tempDir . DIRECTORY_SEPARATOR . 'custom_headers';
        $this->tempFiles[] = $base . '*';

        $source = function (): Generator {
            yield 'Profile' => ['name' => 'John Doe', 'age' => 20, 'gender' => 'male'];
            yield 'Profile' => ['name' => 'Jane Doe', 'age' => 21, 'gender' => 'female'];
        };

        (new DataFlow())
            ->from($source())
            ->load(
                (new SpoutLoader($base . '.xlsx'))
                    ->withHeaders(['gender' => 'Member Gender', 'name' => 'Full Name', 'age'], 'Profile')
            )
            ->run();

        $sheets = $this->readWorkbook($base . '*.xlsx');

        $this->assertSame(['Profile'], array_keys($sheets));
        $this->assertSame(['Member Gender', 'Full Name', 'age'], $sheets['Profile'][0]);
    }
}
