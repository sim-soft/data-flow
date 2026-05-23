<?php

declare(strict_types=1);

namespace Simsoft\DataFlow;

use Closure;
use Exception;
use InvalidArgumentException;
use Iterator;
use Psr\Log\LoggerInterface;
use Simsoft\DataFlow\Enums\Signal;
use Simsoft\DataFlow\Exceptions\DataFlowException;
use Simsoft\DataFlow\Extractors\IterableExtractor;
use Simsoft\DataFlow\Interfaces\MetricsExporter;
use Simsoft\DataFlow\Loaders\Preview;
use Simsoft\DataFlow\Loaders\Visualize;
use Simsoft\DataFlow\Logging\NullLogger;
use Simsoft\DataFlow\Metrics\NullMetricsExporter;
use Simsoft\DataFlow\Traits\DataFrame;
use Simsoft\DataFlow\Traits\Macroable;
use Simsoft\DataFlow\Transformers\Filter;
use Simsoft\DataFlow\Transformers\Chunk;
use Simsoft\DataFlow\Transformers\Mapping;
use Simsoft\DataFlow\Transformers\SchemaValidator;

/**
 * DataFlow class.
 */
class DataFlow
{
    use DataFrame, Macroable;

    /** @var LoggerInterface PSR-3 logger instance. */
    private LoggerInterface $logger;

    /** @var callable|null Global error callback. */
    private mixed $onError = null;

    /** @var callable|null Progress callback. */
    private mixed $onProgress = null;

    /** @var int Rows between progress callback invocations. */
    private int $progressInterval = 100;

    /** @var bool Whether to run in dry-run mode. */
    private bool $dryRun = false;

    /** @var Processor[] Registered stages for the pipeline executor. */
    private array $stages = [];

    /** @var CheckpointManager|null Checkpoint manager for crash recovery. */
    protected ?CheckpointManager $checkpointManager = null;

    /** @var bool Whether to resume from last checkpoint. */
    protected bool $shouldResume = false;

    /** @var array<string, string|array<mixed>|\Closure>|null Validation schema for row data. */
    protected ?array $validationSchema = null;

    /** @var MetricsExporter Metrics exporter for observability. */
    protected MetricsExporter $metricsExporter;

    /**
     * Constructor
     *
     */
    final public function __construct()
    {
        $this->logger = new NullLogger();
        $this->metricsExporter = new NullMetricsExporter();
        $this->init();
    }

    /**
     * Initialization.
     *
     * @return void
     */
    protected function init(): void
    {

    }

    /**
     * Set a PSR-3 logger for pipeline operations.
     *
     * @param LoggerInterface $logger The logger instance.
     * @return static
     */
    public function withLogger(LoggerInterface $logger): static
    {
        $this->logger = $logger;
        return $this;
    }

    /**
     * Register a global error callback.
     *
     * Invoked when a stage encounters an exception and the error strategy
     * does not propagate the exception.
     *
     * @param callable $callback The error callback: fn(Throwable, mixed, string): void
     * @return static
     */
    public function onError(callable $callback): static
    {
        $this->onError = $callback;
        return $this;
    }

    /**
     * Register a progress callback with a configurable interval.
     *
     * @param callable $callback The progress callback: fn(int $rowCount, float $elapsedMs): void
     * @param int $interval Rows between invocations (default: 100, must be >= 1).
     * @return static
     *
     * @throws InvalidArgumentException When interval is less than 1.
     */
    public function onProgress(callable $callback, int $interval = 100): static
    {
        if ($interval < 1) {
            throw new InvalidArgumentException('Progress interval must be >= 1');
        }

        $this->onProgress = $callback;
        $this->progressInterval = $interval;
        return $this;
    }

    /**
     * Enable or disable dry-run mode.
     *
     * In dry-run mode, loaders receive rows but skip actual write operations.
     *
     * @param bool $enabled Whether to enable dry-run mode.
     * @return static
     */
    public function dryRun(bool $enabled = true): static
    {
        $this->dryRun = $enabled;
        return $this;
    }

    /**
     * Configure checkpoint for crash recovery.
     *
     * @param string $path File path for the checkpoint JSON file.
     * @param int $interval Rows between checkpoint writes (default: 100).
     * @return static
     */
    public function withCheckpoint(string $path, int $interval = 100): static
    {
        $this->checkpointManager = new CheckpointManager($path, $interval);
        return $this;
    }

    /**
     * Enable checkpoint-based resumption.
     *
     * When called with withCheckpoint(), the pipeline will skip rows
     * up to the last checkpointed position.
     *
     * @return static
     */
    public function resume(): static
    {
        $this->shouldResume = true;
        return $this;
    }

    /**
     * Set a validation schema for row data.
     *
     * The schema will be used to insert a SchemaValidator stage during execution.
     *
     * @param array<string, string|array<mixed>|Closure> $schema Field-to-rules mapping.
     * @return static
     */
    public function validate(array $schema): static
    {
        $this->validationSchema = $schema;
        return $this;
    }

    /**
     * Set a metrics exporter for pipeline observability.
     *
     * @param MetricsExporter $exporter The metrics exporter instance.
     * @return static
     */
    public function withMetricsExporter(MetricsExporter $exporter): static
    {
        $this->metricsExporter = $exporter;
        return $this;
    }

    /**
     * Get the registered pipeline stages.
     *
     * @return Processor[]
     */
    public function getStages(): array
    {
        return $this->stages;
    }

    /**
     * Get the composed data frame iterator.
     *
     * Lazily composes the iterator chain from registered stages.
     * Each stage is invoked with the output of the previous stage.
     *
     * @return Iterator|null
     */
    public function getDataFrame(): ?Iterator
    {
        if (empty($this->stages)) {
            return $this->dataFrame;
        }

        $iterator = null;
        foreach ($this->stages as $stage) {
            $iterator = $stage($iterator);
        }

        return $iterator;
    }

    /**
     * Set extractor (from source).
     *
     * @param Closure[]|DataFlow[]|Processor[]|iterable<string|int, mixed> ...$extractors
     * @return $this
     * @throws DataFlowException|Exception
     */
    public function from(Processor|DataFlow|Closure|iterable ...$extractors): static
    {
        foreach ($extractors as $extractor) {
            if ($extractor instanceof Closure) {
                $extractor = new CallableProcessor($extractor);
            } elseif (is_iterable($extractor)) {
                $extractor = new IterableExtractor($extractor);
            } elseif ($extractor instanceof DataFlow) {
                // Sub-flow: incorporate its stages into this pipeline
                foreach ($extractor->getStages() as $subStage) {
                    $subStage->setFlow($this);
                    $this->stages[] = $subStage;
                }
                continue;
            }

            $extractor->setFlow($this);
            $this->stages[] = $extractor;
        }
        return $this;
    }

    /**
     * Set transformer.
     *
     * @param Processor|Closure ...$transformers
     * @return $this
     * @throws DataFlowException|Exception
     */
    public function transform(Processor|Closure ...$transformers): static
    {
        foreach ($transformers as $transformer) {
            if ($transformer instanceof Closure) {
                $transformer = new CallableProcessor($transformer);
            }

            $transformer->setFlow($this);
            $this->stages[] = $transformer;
        }

        return $this;
    }

    /**
     * Perform mapping.
     *
     * @param string[]|callable[] $mappings The mapping configs.
     * @return $this
     * @throws DataFlowException|Exception
     */
    public function map(array $mappings): static
    {
        return $this->transform(new Mapping($mappings));
    }

    /**
     * Create a new dataframe with mapping.
     *
     * @param string[]|callable[] $mappings The mapping configs.
     * @return $this
     * @throws DataFlowException|Exception
     */
    public function setNewMap(array $mappings): static
    {
        return $this->transform((new Mapping($mappings))->newDataFrame());
    }

    /**
     * Perform filter by callback.
     *
     * @param Closure $callback
     * @return $this
     * @throws DataFlowException|Exception
     */
    public function filter(Closure $callback): static
    {
        return $this->transform(new Filter($callback));
    }

    /**
     * Chunk data into fixed size array.
     *
     * @param int $chunkSize Maximum data per group. Default: 20
     * @return $this
     * @throws DataFlowException|Exception
     */
    public function chunk(int $chunkSize = 20): static
    {
        return $this->transform(new Chunk($chunkSize));
    }

    /**
     * Limit data
     *
     * @param int $limit
     * @return $this
     * @throws DataFlowException|Exception
     */
    public function limit(int $limit): static
    {
        return $this->transform(function (mixed $data) use (&$limit) {
            return --$limit < 0 ? Signal::Stop : $data;
        });
    }

    /**
     * Set loader.
     *
     * @param Processor|Closure ...$loaders
     * @return $this
     * @throws DataFlowException|Exception
     */
    public function load(Processor|Closure ...$loaders): static
    {
        foreach ($loaders as $loader) {
            if ($loader instanceof Closure) {
                $loader = new CallableProcessor($loader);
            }

            $loader->setFlow($this);
            $this->stages[] = $loader;
        }
        return $this;
    }

    /**
     * Preview data in the pipeline.
     *
     * @param int $max Maximum number of rows to preview. Default: 1.
     * @return $this
     * @throws DataFlowException|Exception
     */
    public function preview(int $max = 1): static
    {
        if ($max <= 0) {
            throw new DataFlowException('Max number of previews must be greater than 0');
        }

        return $this->limit($max)->load(new Preview());
    }

    /**
     * Visualize data in the pipeline.
     *
     * @param string $format Output format. Default: json.
     * @return $this
     * @throws DataFlowException|Exception
     */
    public function visualize(string $format = Visualize::FORMAT_JSON): static
    {
        return $this->load(new Visualize($format));
    }

    /**
     * Run flow and return result.
     *
     * Executes the pipeline using PipelineExecutor which invokes each stage
     * through StageRunner for per-stage error handling and metrics collection.
     *
     * @return PipelineResult
     */
    public function run(): PipelineResult
    {
        $stages = $this->prepareStages();

        $executor = new PipelineExecutor(
            logger: $this->logger,
            deadLetters: new DeadLetterCollection(),
            onError: $this->onError,
            onProgress: $this->onProgress,
            progressInterval: $this->progressInterval,
            dryRun: $this->dryRun,
            checkpointManager: $this->checkpointManager,
            shouldResume: $this->shouldResume,
            metricsExporter: $this->metricsExporter,
        );

        return $executor->execute($stages);
    }

    /**
     * Prepare the stages array for execution.
     *
     * Inserts a SchemaValidator stage after extractors when a validation schema is configured.
     *
     * @return Processor[]
     */
    private function prepareStages(): array
    {
        $stages = $this->stages;

        if ($this->validationSchema !== null) {
            $validator = new SchemaValidator($this->validationSchema);
            $validator->setFlow($this);

            // Find the insertion point: after the last extractor
            $insertIndex = 0;
            foreach ($stages as $index => $stage) {
                if ($stage instanceof Extractor) {
                    $insertIndex = $index + 1;
                }
            }

            array_splice($stages, $insertIndex, 0, [$validator]);
        }

        return $stages;
    }
}
