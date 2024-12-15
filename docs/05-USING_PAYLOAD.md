# Using Payload

Payload is a data container that can be modified every process in the flow.

## Example Usage:

```php
use Simsoft\DataFlow\Payload;

// Initialize the payload.
$payload = new Payload(['total_items' => 0, 'total_price' => 0.00]);

(new DataFlow())
    ->from([
        ['item_name' => 'Coffee', 'qty' => 2, 'price' => 20],
        ['item_name' => 'Tea', 'qty' => 1, 'price' => 10],
        ['item_name' => 'Milk', 'qty' => 3, 'price' => 30],
    ])
    ->transform(function($data) use (&$payload){ // passing payload object as reference

        // Modify the payload.
        $payload['total_items'] += $data['qty'];
        $payload['total_price'] += $data['qty'] * $data['price'];

        return $data;
    })
    ->load(function($data) {
        echo $data['item_name'] . ': ' . $data['qty'] . ' x ' . $data['price'] . PHP_EOL;
    })
    ->run();

// Display the contents of the payload.
echo $payload['total_items'] . ' items, $' . $payload['total_price'];

// Output:
// Coffee: 2 x 20
// Tea: 1 x 10
// Milk: 3 x 30
// 6 items, $110
```
