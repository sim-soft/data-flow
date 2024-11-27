# Introduction

Inspired by Yii2 ActiveRecord and Eloquent.

## Install

```shell
composer require simsoft/db-mysql
```

## Configuration

Examples setup in bootstrap or entry script file.

### Basic Setup

```php
require "vendor/autoload.php";

use Simsoft\DB\MySQL\Connection;

Connection::add('connection_name', [
    'driver' => 'mysql',
    'host' => 'localhost',
    'database' => 'sample_db',
    'username' => 'username',
    'password' => 'password',
    'charset' => 'utf8mb4',
]);
```

### Multiple Connections Setup

```php
$config = [
    'db' => [
        'driver' => 'mysql',
        'host' => 'localhost',
        'database' => 'my_db',
        'username' => 'username',
        'password' => 'password',
        'charset' => 'utf8mb4',
    ],
    'db2' => [
        'driver' => Namespace\MyCustomDriver::class,
        'host' => '127.0.0.1',
        'database' => '/absolute/path/to/database.sqlite',
        'username' => 'username',
        'password' => 'password',
        'charset' => 'utf8mb4',
    ],
];

Connection::configure($config);
```

# Basic Usage

## Declare Model Class

```phpt
namespace Models;

use Simsoft\ActiveRecord\ActiveRecord;

class User extends ActiveRecord
{
    protected string $connection = 'mysql';
    protected string $table = 'user';
    protected string|array $primaryKey = 'id';
}
```

## Query Data

```phpt
// return a single user whose ID is 123
// SELECT * FROM `user` WHERE `id` = 123
$user = User::findByPk(123);

// return all users
// SELECT * FROM `user`
$users = User::find()->get();

// return all users where conditions
$users = User::find()
            ->select('first_name', 'last_name', 'email')
            ->where('email', 'johndoe@email.com')
            ->whereNot('email', 'xyz@email.com')
            ->where('age', '>',  20)
            ->orWhere('age', '<',  30)
            ->where(function($query){
                $query
                    ->like('username', 'abc%')
                    ->orLike('username', '%efg%')
                    ->notLike('username', '%xyz');
            })
            ->orWhere(function($query){
                $query
                    ->whereNull('contact_number')
                    ->orWhereNotNull('mobile_number');
            })
            ->whereIn('country', ['MY', 'SG', 'ID'])
            ->orderBy('id', 'desc')
            ->orderBy(['gender', 'email'])
            ->orderBy([
                'first_name' => 'asc',
                'last_name' => 'desc',
            ])
            ->indexBy('id') // return all users in an array indexed by record IDs
            ->get();
```

## CRUD

```phpt

```

## Massive Assignment

```phpt
$values = [
    'name' => 'John',
    'email' => 'johndoe@email.com',
];

$user = new User();
$user->fill($values)->save();
```

# Documentation

1. [Query Builder](docs/guide/query-builder.md)
2. [Active Record](docs/guide/active-record.md)
