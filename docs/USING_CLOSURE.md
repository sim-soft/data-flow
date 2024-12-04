# Using Closure

Closure is a convenience way to handling data in the DataFlow pipeline.

## Closure Arguments

Closure passed to the DataFlow's processor will be automatically injected with
the following arguments.

1. **mixed $data** - The data content.
2. **int|string $key** - The key index.
3. **closure $exception** - The exception handler.

The closure is expected to **return the data or enum Signal**.

```php
require "vendor/autoload.php";

use Simsoft\DataFlow\DataFlow;
use Throwable;

try {
    (new DataFlow())
        ->from(range(1, 20))
        ->load(function(int $data, $key, $exception){
            if ($data >= 5) {
                return $exception('Throw exception at 5');
            }

            return $data * 2;
        })
        ->load(function($data, $key, $exception) {
            print "Index: $key, Data: $data" . PHP_EOL;
        })
        ->run();

} catch (Throwable $throwable) {
    echo 'Exception: ' . $throwable->getMessage();
}

// Output: Notice the pipeline will be stopped by the exception.
// Index: 0, Data: 2
// Index: 1, Data: 4
// Index: 2, Data: 6
// Index: 3, Data: 8
// Index: 4, Data: 10
// Exception: Throw exception at 5
```

## Control Pipeline with Signal

Using "Next" signal.

```php
require "vendor/autoload.php";

use Simsoft\DataFlow\DataFlow;
use Simsoft\DataFlow\Enums\Signal;

(new DataFlow())
        ->from(range(1, 10))
        ->transform(function(int $data, $key){
            if (in_array($key, [2, 8])) {
                return Signal::Next;
            }

            return $data;
        })
        ->load(function($data, $key, $exception) {
            print "Index: $key, Data: $data" . PHP_EOL;
        })
        ->run();

// Output: Notice index 2 and 8 will be skipped.
// Index: 0, Data: 1
// Index: 1, Data: 2
// Index: 3, Data: 4
// Index: 4, Data: 5
// Index: 5, Data: 6
// Index: 6, Data: 7
// Index: 7, Data: 8
// Index: 9, Data: 10
```

Using "Stop" Signal

```php
require "vendor/autoload.php";

use Simsoft\DataFlow\DataFlow;
use Simsoft\DataFlow\Enums\Signal;

(new DataFlow())
        ->from(range(1, 10))
        ->transform(function(int $data, $key){
            if ($key == 5)) {
                return Signal::Stop;
            }

            return $data;
        })
        ->load(function($data, $key, $exception) {
            print "Index: $key, Data: $data" . PHP_EOL;
        })
        ->run();

// Output: Notice the pipeline will be stopped at index 5.
// Index: 0, Data: 1
// Index: 1, Data: 2
// Index: 2, Data: 3
// Index: 3, Data: 4
// Index: 4, Data: 5
```
