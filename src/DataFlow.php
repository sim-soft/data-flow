<?php

namespace Simsoft\DataFlow;

use Simsoft\DataFlow\Enums\Signal;
use Simsoft\DataFlow\Extractors\IterableExtractor;
use Simsoft\DataFlow\Loaders\VisualLoader;
use Simsoft\DataFlow\Traits\DataFrame;
use Simsoft\DataFlow\Traits\PayloadHandling;
use Simsoft\DataFlow\Transformers\Filter;
use Simsoft\DataFlow\Transformers\Mapping;
use Simsoft\DataFlow\Transformers\Preview;
use Closure;
use Exception;

/**
 * DataFlow class.
 */
class DataFlow
{
    use DataFrame, PayloadHandling;

    /** @var bool Preview mode. */
    protected bool $previewMode = false;

    /**
     * Constructor
     *
     * @param Payload|null $payload Payload.
     */
    final public function __construct(?Payload $payload = null)
    {
        $this->setPayload($payload);

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
     * Set extractor (from source).
     *
     * @param Closure[]|DataFlow[]|Processor[]|iterable<string|int, mixed> ...$extractors
     * @return $this
     * @throws Exception
     */
    public function from(Processor|DataFlow|Closure|iterable ...$extractors): static
    {
        foreach ($extractors as $extractor) {
            if ($extractor instanceof Closure) {
                $extractor = new CallableProcessor($extractor);
            } elseif (is_iterable($extractor)) {
                $extractor = new IterableExtractor($extractor);
            } elseif ($extractor instanceof DataFlow) {
                $this->setPayload($extractor->getPayload());
                $this->setDataFrame($extractor->getDataFrame());
                continue;
            }

            $extractor->setPayload($this->getPayload());
            $this->setDataFrame($extractor($this->getDataFrame()));
        }
        return $this;
    }

    /**
     * Set transformer.
     *
     * @param Processor|Closure ...$transformers
     * @return $this
     * @throws Exception
     */
    public function transform(Processor|Closure ...$transformers): static
    {
        foreach ($transformers as $transformer) {
            if ($transformer instanceof Closure) {
                $transformer = new CallableProcessor($transformer);
            }

            $transformer->setPayload($this->getPayload());
            $this->setDataFrame($transformer($this->getDataFrame()));
        }

        return $this;
    }

    /**
     * Perform mapping.
     *
     * @param string[]|callable[] $mappings The mapping configs.
     * @return $this
     * @throws Exception
     */
    public function map(array $mappings): static
    {
        return $this->transform(new Mapping($mappings));
    }

    /**
     * Create new dataframe with mapping.
     *
     * @param string[]|callable[] $mappings The mapping configs.
     * @return $this
     * @throws Exception
     */
    public function setNewMap(array $mappings): static
    {
        return $this->transform((new Mapping($mappings))->newDataFrame());
    }

    /**
     * Perform filter by callback.
     *
     * @throws Exception
     */
    public function filter(Closure $callback): static
    {
        return $this->transform(new Filter($callback));
    }

    /**
     * Limit data
     *
     * @param int $limit
     * @return $this
     * @throws Exception
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
     * @throws Exception
     */
    public function load(Processor|Closure ...$loaders): static
    {
        if ($this->previewMode) {
            return $this;
        }

        foreach ($loaders as $loader) {
            if ($loader instanceof Closure) {
                $loader = new CallableProcessor($loader);
            }

            $loader->setPayload($this->getPayload());
            $this->setDataFrame($loader($this->getDataFrame()));
        }
        return $this;
    }

    /**
     * Preview data in the pipeline.
     *
     * @param int $max Maximum number of rows to preview. Default: 1.
     * @return $this
     * @throws Exception
     */
    public function preview(int $max = 1): static
    {
        $this->previewMode = true;
        return $this->transform(new Preview($max));
    }

    /**
     * Visualize data in the pipeline.
     *
     * @param int $max Maximum number of rows to preview.
     * @param string $format Output format. Default: json.
     * @return $this
     * @throws Exception
     */
    public function visualize(int $max = -1, string $format = 'json'): static
    {
        return $this->load(new VisualLoader($max, $format));
    }

    /**
     * Print info line.
     *
     * @param string $message
     * @return void
     */
    protected function info(string $message): void
    {
        print $message . PHP_EOL;
    }

    /**
     * Run flow
     *
     * @param Payload|null $payload If provided, the payload will be captured.
     * @return void
     */
    public function run(?Payload &$payload = null): void
    {
        $payload = $this->getPayload();
        if ($iterable = $this->getDataFrame()) {
            iterator_apply($iterable, fn() => true, array($iterable));
        }
    }
}
