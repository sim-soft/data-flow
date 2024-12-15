# Macro and Mixin

Add additional methods to Dataflow and processors.

## Macro

Inject additional method into DataFlow, Extractor, Transformer and Loader.

### Example:

```php
<?php
use Simsoft\DataFlow\DataFlow;
use Simsoft\DataFlow\Transformer;
use Throwable;

require_once 'vendor/autoload.php';

try {

    DataFlow::macro('title', function(string $title) {
        print "TITLE: " . strtoupper($title) . PHP_EOL;
        return $this;
    });

    Transformer::macro('info', function (string $message) {
        print "info: $message" . PHP_EOL;
    });

    (new DataFlow())
        >title('Number list')
        ->from([1, 2, 3, 4, 5, 6, 7, 8, 9, 10])
        ->transform(function(int $number) {
            $this->info("Number = $number");
        })
        ->run();

} catch (Throwable $throwable) {
    error_log($throwable->getMessage());
}

\\ Output:
\\ TITLE: NUMBER LIST
\\ info: Number = 1
\\ info: Number = 2
\\ info: Number = 3
\\ info: Number = 4
\\ info: Number = 5
\\ info: Number = 6
\\ info: Number = 7
\\ info: Number = 8
\\ info: Number = 9
\\ info: Number = 10
```

## Mixin

Mixin is a class that provides additional functionalities for use by DataFlow,
Extractor, Transformer and Loader.

### Example:

```php
<?php
use Simsoft\DataFlow\DataFlow;
use Simsoft\DataFlow\Transformer;
use Throwable;

require_once 'vendor/autoload.php';

try {

    class Math
    {
        public function message(int $number): void
        {
            print 'Checking: ' . $number . PHP_EOL;
        }

        public function info(): Closure
        {
            return fn (string $message) => print $message . PHP_EOL;
        }

        public function isEven(int $number): bool
        {
            return $number % 2 === 0;
        }
    }

    Transformer::mixin(new Math());

    (new DataFlow())
        ->from([1, 2, 3, 4, 5, 6, 7, 8, 9, 10])
        ->transform(function(int $number) {

            $this->message($number);

            if ($this->isEven($number)) {
                $this->info("$number is even");
            } else {
                $this->info("$number is odd");
            }
        })
        ->run();

} catch (Throwable $throwable) {
    error_log($throwable->getMessage());
}

\\ Output:
\\ Checking: 1
\\ 1 is odd
\\ Checking: 2
\\ 2 is even
\\ Checking: 3
\\ 3 is odd
\\ Checking: 4
\\ 4 is even
\\ Checking: 5
\\ 5 is odd
\\ Checking: 6
\\ 6 is even
\\ Checking: 7
\\ 7 is odd
\\ Checking: 8
\\ 8 is even
\\ Checking: 9
\\ 9 is odd
\\ Checking: 10
\\ 10 is even
```
