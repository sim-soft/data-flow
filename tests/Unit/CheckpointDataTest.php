<?php

namespace Simsoft\DataFlow\Tests\Unit;

use Simsoft\DataFlow\CheckpointData;
use Simsoft\DataFlow\Tests\TestCase;

class CheckpointDataTest extends TestCase
{
    public function test_constructor_sets_properties(): void
    {
        $data = new CheckpointData(
            pipelineId: 'abc123',
            lastRowIndex: 42,
            timestamp: 1700000000,
            stageName: 'transform',
        );

        $this->assertSame('abc123', $data->pipelineId);
        $this->assertSame(42, $data->lastRowIndex);
        $this->assertSame(1700000000, $data->timestamp);
        $this->assertSame('transform', $data->stageName);
    }

    public function test_toJson_serializes_all_properties(): void
    {
        $data = new CheckpointData(
            pipelineId: 'pipe-001',
            lastRowIndex: 100,
            timestamp: 1700000000,
            stageName: 'loader',
        );

        $json = $data->toJson();
        $decoded = json_decode($json, true);

        $this->assertSame('pipe-001', $decoded['pipelineId']);
        $this->assertSame(100, $decoded['lastRowIndex']);
        $this->assertSame(1700000000, $decoded['timestamp']);
        $this->assertSame('loader', $decoded['stageName']);
    }

    public function test_fromJson_parses_valid_json(): void
    {
        $json = json_encode([
            'pipelineId' => 'pipe-002',
            'lastRowIndex' => 200,
            'timestamp' => 1700000000,
            'stageName' => 'extractor',
        ]);

        $data = CheckpointData::fromJson($json);

        $this->assertNotNull($data);
        $this->assertSame('pipe-002', $data->pipelineId);
        $this->assertSame(200, $data->lastRowIndex);
        $this->assertSame(1700000000, $data->timestamp);
        $this->assertSame('extractor', $data->stageName);
    }

    public function test_fromJson_returns_null_for_invalid_json(): void
    {
        $this->assertNull(CheckpointData::fromJson('not valid json'));
    }

    public function test_fromJson_returns_null_for_empty_string(): void
    {
        $this->assertNull(CheckpointData::fromJson(''));
    }

    public function test_fromJson_returns_null_for_missing_fields(): void
    {
        $json = json_encode(['pipelineId' => 'abc']);
        $this->assertNull(CheckpointData::fromJson($json));
    }

    public function test_fromJson_returns_null_for_wrong_types(): void
    {
        $json = json_encode([
            'pipelineId' => 123,
            'lastRowIndex' => 'not-int',
            'timestamp' => 'not-int',
            'stageName' => 456,
        ]);

        $this->assertNull(CheckpointData::fromJson($json));
    }

    public function test_round_trip_serialization(): void
    {
        $original = new CheckpointData(
            pipelineId: 'round-trip-test',
            lastRowIndex: 999,
            timestamp: 1700000000,
            stageName: 'stage-3',
        );

        $restored = CheckpointData::fromJson($original->toJson());

        $this->assertNotNull($restored);
        $this->assertSame($original->pipelineId, $restored->pipelineId);
        $this->assertSame($original->lastRowIndex, $restored->lastRowIndex);
        $this->assertSame($original->timestamp, $restored->timestamp);
        $this->assertSame($original->stageName, $restored->stageName);
    }
}
