<?php

declare(strict_types=1);

namespace Simsoft\DataFlow;

/**
 * Immutable value object representing checkpoint file contents.
 */
final readonly class CheckpointData
{
    public function __construct(
        public string $pipelineId,
        public int    $lastRowIndex,
        public int    $timestamp,
        public string $stageName,
    )
    {
    }

    /**
     * Parse a JSON string into a CheckpointData instance.
     *
     * Returns null if the JSON is invalid or missing required fields.
     */
    public static function fromJson(string $json): ?self
    {
        $data = json_decode($json, true);

        if (!is_array($data)) {
            return null;
        }

        if (
            !array_key_exists('pipelineId', $data)
            || !array_key_exists('lastRowIndex', $data)
            || !array_key_exists('timestamp', $data)
            || !array_key_exists('stageName', $data)
        ) {
            return null;
        }

        if (
            !is_string($data['pipelineId'])
            || !is_int($data['lastRowIndex'])
            || !is_int($data['timestamp'])
            || !is_string($data['stageName'])
        ) {
            return null;
        }

        return new self(
            pipelineId: $data['pipelineId'],
            lastRowIndex: $data['lastRowIndex'],
            timestamp: $data['timestamp'],
            stageName: $data['stageName'],
        );
    }

    /**
     * Serialize this checkpoint data to a JSON string.
     */
    public function toJson(): string
    {
        return json_encode([
            'pipelineId' => $this->pipelineId,
            'lastRowIndex' => $this->lastRowIndex,
            'timestamp' => $this->timestamp,
            'stageName' => $this->stageName,
        ], JSON_THROW_ON_ERROR);
    }
}
