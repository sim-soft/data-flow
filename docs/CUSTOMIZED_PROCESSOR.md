## Create Customized ETL Processor.

Create your own ETL processor.

1) Extractor - Extract data from data source.
2) Transformer - Transform/ modify data in the pipeline to produce cleaned data.
3) Loader - Load the cleaned data into database, csv, text, etc.

### Example Customized Extractor.

```php
use Simsoft\DataFlow\Extractor;
use Iterator;

// Extract your data source
class NameExtractor extends Extractor
{
    /**
    * Constructor.
    *
    * @param array $dataSource
    */
    public function __construct(protected array $dataSource)
    {

    }

    /**
    * {@inheritdoc}
    */
    public function __invoke(?Iterator $dataFrame): Iterator
    {
        yield from $this->dataSource;
    }
}
```

### Example Customized Transformer.

```php
use Simsoft\DataFlow\Transformer;
use Iterator;

// Transform your data
class GreetingTransformer extends Transformer
{
    /**
    * {@inheritdoc}
    */
    public function __invoke(?Iterator $dataFrame): Iterator
    {
        foreach ($dataFrame as $name) {
            // transform data here
            yield "Hi, $name";
        }
    }
}
```

### Example Customized Loader.

```php
use Simsoft\DataFlow\Loader;
use Iterator;

// Load your data to destination
class DisplayLoader extends Loader
{
    /**
    * {@inheritdoc}
    */
    public function __invoke(?Iterator $dataFrame): Iterator
    {
        foreach ($dataFrame as $data) {
            echo $data;
        }
    }
}
```

## Example Usage of Customized ETL Processor.

```php
use Simsoft\DataFlow\DataFlow;

(new DataFlow())
    ->from(new NameExtractor(['John', 'Jane', 'Peter', 'Philip']))            // use your custom extractor.
    ->transform(new GreetingTransformer())      // use your custom transformer.
    ->load(new DisplayLoader())                   // use your custom loader.
    ->run();

// Output:
// Hi, John
// Hi, Jane
// Hi, Peter
// Hi, Philip
```
