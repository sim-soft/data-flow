# Introduction

Simple ETL Pipeline data flow.

## Install

```shell
composer require simsoft/data-flow
```

## Basic Usage

Example using extract, transform and load.

```php
require "vendor/autoload.php";

use Simsoft\DataFlow\DataFlow;

(new DataFlow())
    ->from([1, 2, 3])
    ->transform(function($num) {
        return $num * 2;
    })
    ->load(function($num) {
        echo $num . PHP_EOL;
    })
    ->run();

// Output:
// 2
// 4
// 6
```

## Limit

Limit data output.

```php
require "vendor/autoload.php";

use Simsoft\DataFlow\DataFlow;

(new DataFlow())
    ->from([1, 2, 3, 4, 5, 6, 7, 8, 9, 10])
    ->transform(function($num) {
        return $num * 2;
    })
    ->limit(5)  // output only 5 data.
    ->load(function($num) {
        echo $num . PHP_EOL;
    })
    ->run();

// Output:
// 2
// 4
// 6
// 8
// 10
```

## Filter
Filter method help you to filter the data.
```php
require "vendor/autoload.php";

use Simsoft\DataFlow\DataFlow;

(new DataFlow())
    ->from([1, 2, 3, 4, 5, 6, 7, 8, 9, 10])
    ->filter(function($num) {
        // The call back should return bool.
        // In this case, return even number only.
        return $num % 2 === 0;
    })
    ->load(function($num) {
        echo $num . PHP_EOL;
    })
    ->run();

// Output:
// 2
// 4
// 6
// 8
// 10
```

## Mapping

Mapping method allow you to convey the data to another format.

```php
(new DataFlow())
    ->from([
        ['First Name' => 'John', 'Last Name' => 'Doe', 'age' => 20],
        ['First Name' => 'Jane', 'Last Name' => 'Doe', 'age' => 30],
        ['First Name' => 'John', 'Last Name' => 'Smith', 'age' => 50],
        ['First Name' => 'Jane', 'Last Name' => 'Smith', 'age' => 60],
    ])
    ->map([
        // rename the key
        'first_name' => 'First Name',
        'last_name' => 'Last Name',

        // customise data via callback method.
        'full_name' => fn($data) => $data['first_name'] . ' ' . $data['last_name'],
        'senior' => fn($data) => $data['age'] > 30 ? 'Yes' : 'No',
    ])
    ->load(function($data) {
        echo $data['full_name'] . ' is ' . $data['age'] . ' years old. ' . $data['senior'] . PHP_EOL;
    })
    ->run();

// Output:
// John Doe is 20 years old. No
// Jane Doe is 30 years old. Yes
// John Smith is 50 years old. Yes
// Jane Smith is 60 years old. Yes
```
## Flow Continuation

Connecting flows into a chain.

```php
$flow1 = (new DataFlow())
    ->from([1, 2, 3])
    ->transform(function($num) {
        return $num * 2;
    });

(new DataFlow())
    ->from($flow1) // connect flow1 to flow2.
    ->transform(function($num) {
        return $num * 3;
    })
    ->load(function($num) {
        echo $num . PHP_EOL;
    })
    ->run();

// Output:
// 6
// 12
// 18
```

## Advanced Usage

1. [Using Closure](docs/01-USING_CLOSURE.md)
2. [Useful Processors](docs/02-USEFUL_PROCESSORS.md)
3. [Customized ETL Processor](docs/03-CUSTOMIZED_PROCESSOR.md)
4. [Create Reusable Data Flow](docs/04-CONTROLLABLE_DATAFLOW.md)
5. [Using Payload](docs/05-USING_PAYLOAD.md)
6. [Macro & Mixin](docs/06-MACRO_AND_MIXIN.md)

## License

The Simsoft DataFlow is licensed under the MIT License. See
the [LICENSE](LICENSE) file for details
