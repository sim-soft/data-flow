# Macro and Mixin

Add additional methods to Dataflow and processors.

## Macro

Inject additional method into DataFlow, Extractor, Transformer and Loader.

### Example:

```php
<?php
require_once 'vendor/autoload.php';

use Simsoft\DataFlow\DataFlow;
use Simsoft\DataFlow\Transformer;
use Simsoft\DataFlow\Loader;
use Throwable;

try {

    // Add title method to DataFlow
    DataFlow::macro('title', function(string $title) {
        print "TITLE: " . strtoupper($title) . PHP_EOL;
        return $this;
    });

    // Add info method to Loader class.
    Loader::macro('info', function (string $message) {
        print "info: $message" . PHP_EOL;
    });

    // Add multiply method to Transformer class.
    Transformer::macro('multiply', function (int $number1, int $number2) {
        return $number1 * $number2;
    });

    (new DataFlow())
        ->title('Number list') // call macro method 'title' from DataFlow class.
        ->from([1, 2, 3, 4, 5, 6, 7, 8, 9, 10])
        ->transform(function(int $number, int $key) {
            return $this->multiply($number, $key); // call macro method 'multiply' from Transformer class.
        })
        ->load(function(int $number) {
            $this->info("Number: $number");  // call macro method 'info' from Loader class.
        })
        ->run();

} catch (Throwable $throwable) {
    error_log($throwable->getMessage());
}

\\ Output:
\\ TITLE: NUMBER LIST
\\ info: Number = 0
\\ info: Number = 2
\\ info: Number = 6
\\ info: Number = 12
\\ info: Number = 20
\\ info: Number = 30
\\ info: Number = 42
\\ info: Number = 56
\\ info: Number = 72
\\ info: Number = 90
```

## Mixin

Mixin is a class that provides additional functionalities for use by DataFlow,
Extractor, Transformer and Loader.

### Example:

```php
<?php
require_once 'vendor/autoload.php';

use Simsoft\DataFlow\DataFlow;
use Simsoft\DataFlow\Transformer;
use Throwable;

try {

    class CustomMixin
    {
        public function forwardMsg(int $number): void
        {
            print "$number is forwarded" . PHP_EOL;
        }

        public function isEven(int $number): bool
        {
            return $number % 2 === 0;
        }

        // Or, return Closure method.
        public function info(): Closure
        {
            return fn (string $message) => print $message . PHP_EOL;
        }
    }

    Transformer::mixin(new CustomMixin()); // Add methods from CustomMixin class to Transformer class.

    (new DataFlow())
        ->from([1, 2, 3, 4, 5, 6, 7, 8, 9, 10])
        ->transform(function(int $number) {

            if (!$this->isEven($number)) {   // Call method 'isEven' from CustomMixin class.
                $this->info("$number is omitted"); // Call method 'info' from CustomMixin class.
                return Signal::Next;
            }

            $this->forwardMsg($number);    // Call method 'message' from CustomMixin class.
            return $number;
        })
        ->load(function(int $number){
            print "Catch: $number" . PHP_EOL;
        })
        ->run();

} catch (Throwable $throwable) {
    error_log($throwable->getMessage());
}

\\ Output:
\\ 1 is omitted
\\ 2 is forwarded
\\ Catch: 2
\\ 3 is omitted
\\ 4 is forwarded
\\ Catch: 4
\\ 5 is omitted
\\ 6 is forwarded
\\ Catch: 6
\\ 7 is omitted
\\ 8 is forwarded
\\ Catch: 8
\\ 9 is omitted
\\ 10 is forwarded
\\ Catch: 10
```
