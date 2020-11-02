# Simplicity Hit-Counter

A simple library for count users' hits.

## Attention

PLEASE NOTE THAT this library is good only for small websites so...
DO NOT USE THIS for high traffic websites and use more professional 
tools to handle huge database and server overheads.

## Install
**composer**
```php 
composer require mmdm/sim-hit-counter
```

Or you can simply download zip file from github and extract it, 
then put file to your project library and use it like other libraries.

Just add line below to autoload files:

```php
require_once 'path_to_library/autoloader.php';
```

and you are good to go.

## Architecture

This library use database to have its best performance

**Collation:**

It should be `utf8mb4_unicode_ci` because it is a very nice collation. 
For more information about differences between `utf8` and `utf8mb4` 
in `general` and `unicode` please see 
[this link][utf8_or_utf8mb4] from `stackoverflow`

**Table:**

- hits

    This table contains all hits.
    
    Least columns of this table should be:
        
    - id (INT(11) UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT)
    
    - url (TEXT NOT NULL)
    
    - type (VARCHAR(20) NOT NULL)
    
    - view_count (BIGINT(20) UNSIGNED NOT NULL  DEFAULT 0)
    
    - unique_view_count (BIGINT(20) UNSIGNED NOT NULL  DEFAULT 0)
    
    - from_time (INT(11) UNSIGNED NOT NULL)
    
    - to_time (INT(11) UNSIGNED NOT NULL)

## How to use

First of all you need a `PDO` connection like below:

```php
$host = '127.0.0.1';
$db = 'database name';
$user = 'username';
$pass = 'password';
// this is very nice collation to use
$charset = 'utf8mb4';

$dsn = "mysql:host={$host};dbname={$db};charset={$charset}";
$options = [
    // add this option to show exception on any bad condition
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
];
try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    throw new \PDOException($e->getMessage(), (int)$e->getCode());
}
```

Then create hit counter instance and use its methods

```php
$hitCounter = new HitCounter($pdo);

// use hit methods
$hitCounter->hitDaily('the_url');
// ...
```

## HitCounter class

#### `__construct(PDO $pdo_instance, ?array $config = null, bool $test_mode = false)`

`$config`: The config of yours like the one in [Architecture](#architecture).

see config file under `src/_Config` path.

`$test_mode`: By default users ip addresses that are unknown or 
invalid, crawlers and `DNT` header are not allowed to hit website. 
To prevent this check, send `true` as parameter's value.

#### `runConfig()`

To make config working and create tables, call this method.

#### `hitDaily(string $url)`

Make a daily hit.

#### `hitWeekly(string $url)`

Make a weekly hit.

#### `hitMonthly(string $url)`

Make a monthly hit.

#### `hitYearly(string $url)`

Make a yearly hit.

#### `hit(string $url, int $hit_at_times = IHitCounter::TIME_ALL)`

Make a hit for a specific period of time according to second 
parameter. please see [Constants](#constants) section

**Note:** You can use multiple constants together with `|` operator

#### `report(?string $url, int $from_time, int $to_time, int $hit_with_types = self::TYPE_ALL): array`

You can get count of *views* and *unique_views* from specific/all 
url(s) from a specific time and for a specific type.

#### `freeReport(?string $url, string $where, array $bind_values = []): array`

You can get count of *views* and *unique_views* from specific/all 
url(s) with specific condition.

**Note:** You should use names parameters and put value of that 
parameter in `$bind_values` parameter.

#### `saveDailyHits(string $path_to_store, bool $delete_from_database = false): bool`

Save all daily hits information before today in a file.

#### `saveWeeklyHits(string $path_to_store, bool $delete_from_database = false): bool`

Save all weekly hits information before this week in a file.

#### `saveMonthlyHits(string $path_to_store, bool $delete_from_database = false): bool`

Save all monthly hits information before this month in a file.

#### `saveYearlyHits(string $path_to_store, bool $delete_from_database = false): bool`

Save all yearly hits information before this year in a file.

#### `saveHits(string $path_to_store, int $hit_at_times = self::TIME_ALL, bool $delete_from_database = false): bool`

Save all hits information before current time in a file.

**Note:** You can use multiple constants together with `|` operator

## Constants

There are some constants in `IHitCounter` interface class as below:

**Time constants**:

- TIME_DAILY

- TIME_WEEKLY

- TIME_MONTHLY

- TIME_YEARLY

- TIME_ALL

**Hit types constants**:

- TYPE_DAILY

- TYPE_WEEKLY

- TYPE_MONTHLY

- TYPE_YEARLY

- TYPE_ALL

# License

Under MIT license

[utf8_or_utf8mb4]: https://stackoverflow.com/questions/766809/whats-the-difference-between-utf8-general-ci-and-utf8-unicode-ci
