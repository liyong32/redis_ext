<?php

namespace Redis;
/**
 * Class RedisConfig
 */
class RedisConfig{

	private static $topics= [
        /*redis基础默认配置*/
        'redisBase' => array(
            'node' => array(
                array('master' => '127.0.0.1:6379', 'slave' => '127.0.0.1:6379'),
            ),
            'db' => 0, // 选择数据存储DB，选定后不可更改，否则会找不到数据
            'is_sys' => true, // 系统授权，可使用部分敏感函数
            'master_auth' => 'myredis', // 主节点密码
            'slave_auth' =>  'myredis'    // 从节点密码
        ),

        /*redis同步任务配置*/
        'redisCron' => array(
            'node' => array(
                array('master' => '127.0.0.1:6379', 'slave' => '127.0.0.1:6379'),
            ),
            'db' => 1, // 选择数据存储DB，选定后不可更改，否则会找不到数据
            'master_auth' => 'myredis', // 主节点密码
            'slave_auth' =>  'myredis',  // 从节点密码
            'debug' => false, // 打开调试日志，必须首先开启config中的redis日志，此设置才会生效,此日志打开后会些许影响性能
        ),
    ];

	public static function gettopic($topicname)
	{
		return self::$topics[$topicname];
	}


}


