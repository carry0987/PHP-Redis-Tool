<?php
require dirname(__DIR__).'/vendor/autoload.php';

use carry0987\Redis\RedisTool;

$config = array(
    'host' => 'localhost',
    'port' => 6379,
    'pwd' => '',
    'database' => '0'
);

$redis = new RedisTool($config);

$redis->setValue('test', 'test');
var_dump($redis->getValue('test'));
