# Getting Started

1. [Raw query](#raw-query)
2. [Query Builder](#query-builder)
    1. [SELECT](#select)

## Raw Query

### Find All Records

```phpt
use Simsoft\ActiveRecord\Builder\Raw;

$users = (new Raw('SELECT * FROM users WHERE status = ?', [1]))->get();

foreach($users as $user) {
    echo $user->first_name;
    echo $user->last_name;
}
```

### Find First Record

```phpt
use Simsoft\ActiveRecord\Builder\Raw;

$user = (new Raw('SELECT * FROM users WHERE status = ?', [1]))->first();

echo $user->first_name;
echo $user->last_name;
```

### Raw CRUD

```phpt
use Simsoft\ActiveRecord\Builder\Raw;

$status = (new Raw('INSERT INTO users (name, status) VALUES (?, ?)', ['John', 1]))->execute();
// or
$status = (new Raw('INSERT INTO users (name, status) VALUES (?, ?)', ['John', 1]))();

$status = (new Raw('UPDATE users SET name = 'john doe' WHERE id = ?', [1]))();

$status = (new Raw('DELETE FROM users WHERE id = ?', [1]))();
```

# Query Builder

## Basic Select

```phpt
use Simsoft\ActiveRecord\Builder\Query;

// SELECT t.* FROM user t
$users = (new Query())->from('user')->get();

// Set table alias `u`
// SELECT u.first_name, u.last_name FROM user u
$users = (new Query())->from('user', 'u')->select('first_name', 'last_name')->get();

foreach($users as $user) {
    echo $user->first_name;
    echo $user->last_name;
}

// Get first record
$user = (new Query())->from('user')->select('first_name', 'last_name')->first();
echo $user->first_name;
echo $user->last_name;
```

### Find One Record

```phpt
use Simsoft\ActiveRecord\Builder\Query;

// SELECT t.* FROM user t WHERE t.id = ? LIMIT 1
$user = (new Query())->from('user')->where('id', 1)->first();

SELECT t.* FROM user t WHERE t.age > 25 AND t.status = 1 LIMIT 1
$user = (new Query())->from('user')->where('age', '>', 25)->where('status', 1)->first();
```

## Basic Where Clauses

### Where methods: **where(), orWhere(), whereNot(), orWhereNot(), whereNull(), orWhereNotNull();

```phpt
// SELECT t.first_name, t.last_name, t.email FROM user t WHERE t.age > 25
// AND t.status = 1 LIMIT 800
// default limit is 800
$users = (new Query())
    ->from('user')
    ->select('first_name', 'last_name', 'email')
    ->where('age', '>', 25)
    ->where('status', 1)
    ->get();

foreach($users as $user) {
    echo $user->first_name;
    echo $user->last_name;
}

// SELECT t.* FROM user t WHERE t.status = 1 AND t.gender != 'male' AND t.height >= 150
// AND t.weight < 70 AND t.salary >= 3000 AND (t.age > 18 OR t.age <= 25) LIMIT 800
$users = (new Query())
    ->from('user')
    ->where('status', 1)
    ->whereNot('gender', 'male')
    ->where([                                   // array conditions
        ['height', '>=', 150],
        ['weight', '<', 70],
    ])
    ->where(new Raw('t.salary >= ?', [3000]))   // Raw query
    ->where(function($q){                       // group conditions
        $q->where('age', '>', 18)
            ->orWhere('age', '<=', 25);
    })
    ->get();

// SELECT t.* FROM user t WHERE t.last_name IS NULL OR t.email IS NOT NULL LIMIT 800
$users = (new Query())
    ->from('user')
    ->whereNull('last_name')
    ->orWhereNotNull('email')
    ->get();
```

### In methods: whereIn(), whereNotIn(), orWhereIn(), orWhereNotIn().

```phpt
// SELECT t.* FROM user t WHERE t.role IN (1,2,3) AND t.status NOT IN (1,2,3,4) LIMIT 800;
$users = (new Query())
    ->from('user')
    ->whereIn('role', [1, 2, 3])
    ->whereNotIn('status', [1, 2, 3, 4])
    ->get();
```

### Between methods: between(), notBetween(), orBetween(), orNotBetween().

```phpt
// SELECT t.* FROM user t WHERE t.height BETWEEN 150 AND 200
// OR t.birth_day NOT BETWEEN '1990-01-01' AND '1990-01-31' LIMIT 800;
$users = (new Query())
    ->from('user')
    ->between('height', 150, 200)
    ->orNotBetween('birth_day', '1990-01-01', '1990-01-31')
    ->get();
```

### Like Methods: like(), notLike(), orLike(), orNotLike().

```phpt
// SELECT t.* FROM user t WHERE t.name LIKE '%john%' AND (t.name NOT LIKE '%Jane%' OR t.name NOT LIKE '%Simon%')
(new Query())
    ->from('user')
    ->like('name', '%john%')
    ->where(function($q){
        $q->notLike('name', '%Jane%')
          ->orNotLike('name', '%Simon%');
    })
    ->get();
```

### Ordering, Grouping, Limit & Offset

```phpt
// SELECT first_name, last_name, email FROM users WHERE id = 1
(new Select('users'))
    ->select('first_name', 'last_name', new Raw('COUNT(*) AS count, SUM(age) AS sum'))
    ->where('id', 6)
    ->orWhere('id', 7)
    ->where([
        'a' => 1,
        'b' => 2,
    ])
    ->where(new Raw('created > NOW()'))
    ->where(new Raw('introducer = ?', [1]))
    ->where('salary', '>', 3000)
    ->orWhere('working_hours', '<', 9)

    ->whereNot('gender', 'f')
    ->orWhereNot('gender', 'f')
    ->where(function($q) {
        $q
            ->where('email', 'abc@email.com')
            ->orWhere(function($q){
                $q->where('height', '>', 160)
                    ->where('weight', '<', 60);
                ;
            })
        ;
    })
    ->orWhere(function($q){
        $q->where('education', '>', 'bachelor')
            ->orWhere('education', '<', 'master');
    })
    ->whereNull('name')
    ->orWhereNull('name')
    ->whereNotNull('name')
    ->orWhereNotNull('name')

    ->whereIn('invoice_id', [1, 2, 3])
    ->orWhereIn('invoice_id', [8, 9, 10])
    ->whereNotIn('invoice_id', [4, 5, 6, 7])
    ->orWhereNotIn('invoice_id', [11, 12, 13, 14])

    ->between('created', '2018-01-01', '2018-12-31')
    ->orBetween('created', '2018-01-01', '2018-12-31')
    ->notBetween('created', '2018-01-01', '2018-12-31')
    ->orNotBetween('created', '2018-01-01', '2018-12-31')

    ->orderBy('id', 'desc')
    ->orderBy([
        'id' => 'desc',
        'name' => 'asc',
    ])
    ->groupBy('id', 'name')
    ->having('salary', '>', 3000)
    ->having(new Raw('SUM(salary) > ?', [2000]))
    ->limit(20, 30)
    ->page(1)
    ->get();
```

## Aggregation
