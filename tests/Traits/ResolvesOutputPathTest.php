<?php

namespace Simsoft\DataFlow\Tests\Traits;

use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Simsoft\DataFlow\Tests\TestCase;
use Simsoft\DataFlow\Traits\ResolvesOutputPath;

/**
 * ResolvesOutputPathTest class.
 *
 * Regression coverage for destination paths whose directory names contain dots.
 * Splitting on the first dot truncated the directory and wrote to the wrong
 * location — including any Windows profile path such as C:\Users\jane.doe\out.
 */
#[CoversTrait(ResolvesOutputPath::class)]
class ResolvesOutputPathTest extends TestCase
{
    /**
     * Expose the protected trait method for testing.
     *
     * @return object
     */
    private function subject(): object
    {
        return new class {
            use ResolvesOutputPath;

            /**
             * @param string $path The destination path.
             * @param string $defaultExtension Extension to use when the path has none.
             * @return array{0: string, 1: string}
             */
            public function split(string $path, string $defaultExtension): array
            {
                return $this->splitOutputPath($path, $defaultExtension);
            }
        };
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function pathProvider(): array
    {
        return [
            'simple path with extension' => ['/tmp/output.xlsx', '/tmp/output', 'xlsx'],
            'no extension keeps default' => ['/tmp/output', '/tmp/output', 'xlsx'],
            'csv extension' => ['/tmp/report.csv', '/tmp/report', 'csv'],
            'dot in directory name' => ['/srv/v1.2/report.xlsx', '/srv/v1.2/report', 'xlsx'],
            'dot in windows profile' => ['C:\\Users\\jane.doe\\out.xlsx', 'C:\\Users\\jane.doe\\out', 'xlsx'],
            'dotted directory without extension' => ['/srv/v1.2/report', '/srv/v1.2/report', 'xlsx'],
            'multiple dots in filename' => ['/tmp/report.2026.xlsx', '/tmp/report.2026', 'xlsx'],
            'relative dotted directory' => ['./output/report.xlsx', './output/report', 'xlsx'],
        ];
    }

    #[Test]
    #[DataProvider('pathProvider')]
    public function splitsOnFinalExtensionOnly(
        string $path,
        string $expectedBase,
        string $expectedExtension,
    ): void {
        [$base, $extension] = $this->subject()->split($path, 'xlsx');

        $this->assertSame($expectedBase, $base);
        $this->assertSame($expectedExtension, $extension);
    }

    #[Test]
    public function recombiningTheSplitPreservesTheOriginalPath(): void
    {
        $path = 'C:\\Users\\jane.doe\\releases\\v1.2\\report.xlsx';

        [$base, $extension] = $this->subject()->split($path, 'xlsx');

        $this->assertSame($path, $base . '.' . $extension);
    }
}
