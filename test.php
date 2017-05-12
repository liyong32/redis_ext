<?php
error_reporting(E_ALL^E_NOTICE);
/**
 * spl_autoload_register
 */
function autoloader($class) {
    $class= str_replace('Redis\\', '', $class);
    include 'Redis/' . $class . '.php';
}
spl_autoload_register('autoloader');

$redis = Redis\RedisStore::instance('redisBase');
echo "Server is running: " . $redis->PING().PHP_EOL;
exit;