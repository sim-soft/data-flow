<?php

declare(strict_types=1);

namespace Simsoft\DataFlow\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Simsoft\DataFlow\CheckpointManager;
use Simsoft\DataFlow\CheckpointData;

class CheckpointManagerTest extends TestCase
{
    private string $checkpointPath;

    protected function setUp(): void
    {
        $this->checkpointPath = sys_get_temp_dir() . '/test_checkpoint_' . uniqid() . '.json';
    }

    protected function tearDown(): void
    {
        @unlink($this->checkpointPath);
        @unlink($this->checkpointPath . '.tmp');
    }

    public function testShouldWriteReturnsTrueAtInterval(): void
    {
        $manager = new CheckpointManager($this->checkpointPath, 100);

        $this->assertTrue($manager->shouldWrite(100));
        $this->assertTrue($manager->shouldWrite(200));
        $this->assertTrue($manager->shouldWrite(300));
    }

    public function testShouldWriteReturnsFalseAtNonInterval(): void
    {
        $manager = new CheckpointManager($this->checkpointPath, 100);

        $this->assertFalse($manager->shouldWrite(0));
        $this->assertFalse($manager->shouldWrite(1));
        $this->assertFalse($manager->shouldWrite(50));
        $this->assertFalse($manager->shouldWrite(99));
        $this->assertFalse($manager->shouldWrite(101));
    }

    public function testShouldWriteReturnsFalseForZero(): void
    {
        $manager = new CheckpointManager($this->checkpointPath, 1);

        $this->assertFalse($manager->shouldWrite(0));
    }

    public function testShouldWriteWithIntervalOne(): void
    {
        $manager = new CheckpointManager($this->checkpointPath, 1);

        $this->assertTrue($manager->shouldWrite(1));
        $this->assertTrue($manager->shouldWrite(2));
        $this->assertTrue($manager->shouldWrite(100));
    }

    public function testWriteAndRead(): void
    {
        $manager = new CheckpointManager($this->checkpointPath, 100);

        $manager->write('pipeline-123', 500, 'transform');

        $data = $manager->read();

        $this->assertInstanceOf(CheckpointData::class, $data);
        $this->assertSame('pipeline-123', $data->pipelineId);
        $this->assertSame(500, $data->lastRowIndex);
        $this->assertSame('transform', $data->stageName);
        $this->assertIsInt($data->timestamp);
    }

    public function testReadReturnsNullWhenFileDoesNotExist(): void
    {
        $manager = new CheckpointManager($this->checkpointPath, 100);

        $this->assertNull($manager->read());
    }

    public function testDelete(): void
    {
        $manager = new CheckpointManager($this->checkpointPath, 100);

        $manager->write('pipeline-123', 500, 'transform');
        $this->assertFileExists($this->checkpointPath);

        $manager->delete();
        $this->assertFileDoesNotExist($this->checkpointPath);
    }

    public function testDeleteWhenFileDoesNotExist(): void
    {
        $manager = new CheckpointManager($this->checkpointPath, 100);

        // Should not throw
        $manager->delete();
        $this->assertFileDoesNotExist($this->checkpointPath);
    }

    public function testGeneratePipelineIdIsDeterministic(): void
    {
        $stages = ['extract', 'transform', 'load'];
        $id1 = CheckpointManager::generatePipelineId($stages);
        $id2 = CheckpointManager::generatePipelineId($stages);

        $this->assertSame($id1, $id2);
    }

    public function testGeneratePipelineIdDiffersForDifferentStages(): void
    {
        $id1 = CheckpointManager::generatePipelineId(['extract', 'transform', 'load']);
        $id2 = CheckpointManager::generatePipelineId(['extract', 'load']);

        $this->assertNotSame($id1, $id2);
    }

    public function testGeneratePipelineIdIsSha256(): void
    {
        $id = CheckpointManager::generatePipelineId(['extract', 'transform']);

        // SHA-256 produces a 64-character hex string
        $this->assertSame(64, strlen($id));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $id);
    }

    public function testAtomicWriteDoesNotLeaveTemporaryFile(): void
    {
        $manager = new CheckpointManager($this->checkpointPath, 100);

        $manager->write('pipeline-123', 100, 'extract');

        $this->assertFileExists($this->checkpointPath);
        $this->assertFileDoesNotExist($this->checkpointPath . '.tmp');
    }

    public function testWriteCreatesDirectoryIfNeeded(): void
    {
        $nestedPath = sys_get_temp_dir() . '/checkpoint_test_' . uniqid() . '/sub/checkpoint.json';
        $manager = new CheckpointManager($nestedPath, 100);

        $manager->write('pipeline-123', 100, 'extract');

        $this->assertFileExists($nestedPath);

        // Cleanup
        @unlink($nestedPath);
        @rmdir(dirname($nestedPath));
        @rmdir(dirname($nestedPath, 2));
    }
}
