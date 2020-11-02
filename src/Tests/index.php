<?php

use Sim\HitCounter\Utils\HitCounterUtil;

include_once __DIR__ . '/../../vendor/autoload.php';
//include_once __DIR__ . '/../../autoloader.php';

$host = '127.0.0.1';
$db = 'test';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host={$host};dbname={$db};charset={$charset}";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
];
try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    throw new \PDOException($e->getMessage(), (int)$e->getCode());
}

try {
    $hitCounter = new \Sim\HitCounter\HitCounter($pdo, null, true);

    // up hit tables
//    $hitCounter->runConfig();

    // test hit
//    $hitCounter->hit('index');
//    $hitCounter->hitDaily('index');

    // test report
//    var_dump($hitCounter->report('index', HitCounterUtil::getYesterdayStartTime(), HitCounterUtil::getTodayEndTime()));
} catch (\Sim\HitCounter\Interfaces\IDBException $e) {
    echo $e->getMessage();
}
