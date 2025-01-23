<?php

namespace Simsoft\DataFlow;

use Closure;
use Exception;
use Simsoft\DataFlow\Enums\Signal;
use Simsoft\DataFlow\Extractors\IterableExtractor;
use Simsoft\DataFlow\Loaders\Preview;
use Simsoft\DataFlow\Loaders\Visualize;
use Simsoft\DataFlow\Traits\DataFrame;
use Simsoft\DataFlow\Traits\Macroable;
use Simsoft\DataFlow\Transformers\Filter;
use Simsoft\DataFlow\Transformers\Chunk;
use Simsoft\DataFlow\Transformers\Mapping;

/**
 * DataFlow class.
 */
class DataFlow
{
    use DataFrame, Macroable;

    /**
     * Constructor
     *
     */
    final public function __construct()
    {
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
                $this->setDataFrame($extractor->getDataFrame());
                continue;
            }

            $extractor->setFlow($this);
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

            $transformer->setFlow($this);
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
     * Chunk data into fixed size array.
     *
     * @param int $chunkSize Maximum data per group. Default: 20
     * @return $this
     * @throws Exception
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
        foreach ($loaders as $loader) {
            if ($loader instanceof Closure) {
                $loader = new CallableProcessor($loader);
            }

            $loader->setFlow($this);
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
        if ($max <= 0) {
            throw new Exception('Max number of previews must be greater than 0');
        }

        return $this->limit($max)->load(new Preview());
    }

    /**
     * Visualize data in the pipeline.
     *
     * @param string $format Output format. Default: json.
     * @return $this
     * @throws Exception
     */
    public function visualize(string $format = Visualize::FORMAT_JSON): static
    {
        return $this->load(new Visualize($format));
    }

    /**
     * Run flow
     *
     * @return void
     */
    public function run(): void
    {
        if ($iterable = $this->getDataFrame()) {
            iterator_apply($iterable, fn() => true, array($iterable));
        }
    }
}
