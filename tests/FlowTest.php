<?php declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Simsoft\DataFlow\DataFlow;

/**
 * FlowTest
 */
class FlowTest extends TestCase
{
    public static function dataProvider(): array
    {
        return [
            'Case 1' => [
                [1, 2, 3, 4, 5, 6, 7, 8, 9, 10],
                fn($data) => ++$data,
                [2, 3, 4, 5, 6, 7, 8, 9, 10, 11],
            ],
            'Case 2' => [
                [1, 2, 3, 4, 5, 6, 7, 8, 9, 10],
                fn($data) => $data * 2,
                [2, 4, 6, 8, 10, 12, 14, 16, 18, 20],
            ],
            'Case 3' => [
                ['John', 'Jane', 'Peter', 'Philip'],
                fn($name) => "Hi, $name",
                ['Hi, John', 'Hi, Jane', 'Hi, Peter', 'Hi, Philip'],
            ],
        ];
    }

    /**
     * @throws Exception
     */
    #[DataProvider('dataProvider')]
    public function testInput(array $from, callable $callback, array $outputs)
    {
        $result = [];
        (new DataFlow())
            ->from($from)
            ->transform($callback)
            ->load(function ($data) use (&$result) {
                $result[] = $data;
            })->run();

        $this->assertJsonStringEqualsJsonString(json_encode($outputs), json_encode($result));
    }
}
