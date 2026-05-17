<?php

declare(strict_types=1);

namespace Simsoft\DataFlow;

/**
 * Handles checkpoint file I/O with atomic writes and resume logic.
 */
final class CheckpointManager
{
    public function __construct(
        private readonly string $filePath,
        private readonly int    $interval = 100,
    )
    {
    }

    /**
     * Determine if a checkpoint should be written at the given row index.
     *
     * Returns true when rowIndex is a positive multiple of the configured interval.
     */
    public function shouldWrite(int $rowIndex): bool
    {
        return $rowIndex > 0 && $rowIndex % $this->interval === 0;
    }

    /**
     * Write checkpoint data atomically using temp file + rename pattern.
     *
     * @throws \Simsoft\DataFlow\Exceptions\DataFlowException If the write fails.
     */
    public function write(string $pipelineId, int $lastRowIndex, string $stageName): void
    {
        $checkpoint = new CheckpointData(
            pipelineId: $pipelineId,
            lastRowIndex: $lastRowIndex,
            timestamp: time(),
            stageName: $stageName,
        );

        $json = $checkpoint->toJson();
        $tempPath = $this->filePath . '.tmp';

        $dir = dirname($this->filePath);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $result = file_put_contents($tempPath, $json);

        if ($result === false) {
            throw new Exceptions\DataFlowException(
                "Failed to write checkpoint temp file: {$tempPath}"
            );
        }

        $renamed = rename($tempPath, $this->filePath);

        if (!$renamed) {
            // Clean up temp file on failure
            @unlink($tempPath);
            throw new Exceptions\DataFlowException(
                "Failed to rename checkpoint temp file to: {$this->filePath}"
            );
        }
    }

    /**
     * Read the checkpoint file and return CheckpointData, or null if file doesn't exist.
     */
    public function read(): ?CheckpointData
    {
        if (!file_exists($this->filePath)) {
            return null;
        }

        $contents = file_get_contents($this->filePath);

        if ($contents === false) {
            return null;
        }

        return CheckpointData::fromJson($contents);
    }

    /**
     * Delete the checkpoint file if it exists.
     */
    public function delete(): void
    {
        if (file_exists($this->filePath)) {
            @unlink($this->filePath);
        }
    }

    /**
     * Generate a deterministic pipeline ID based on stage configuration.
     *
     * @param array<int, string> $stages Array of stage names.
     */
    public static function generatePipelineId(array $stages): string
    {
        return hash('sha256', json_encode($stages, JSON_THROW_ON_ERROR));
    }
}
