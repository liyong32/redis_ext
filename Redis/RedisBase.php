<?php
namespace Redis;
/**
 * REDIS基础抽象类
 */
abstract class RedisBase {
	/**
	 * string数据结构
	 */
	const STRING = 1;

	/**
	 * SET数据结构
	 */
    const SET = 2;

    /**
     * 链表数据结构
     */
    const LISTS = 3;

    /**
     * 有序SET数据结构
     */
    const ZSET = 4;

    /**
     * HASH数据结构
     */
    const HASH = 5;

    /**
     * 所有的读操作
     */
    protected $readFun = array(
            "HGET", "HEXISTS", "HGETALL", "HKEYS", "HMGET", "HSCAN", "HVALS", "ZCOUNT", "ZRANGE", "ZRANGEBYSCORE", "ZRANK", "ZREVRANGE", "ZREVRANGEBYSCORE", "ZREVRANK", "ZSCORE", "ZSCAN","SINTER","LRANGE","TTL","TYPE","GET","LRANGE","ZRANGE", "SINTER", "SCARD", "ZCARD","EXISTS","LINDEX",
            "LLEN","LRANGE","SORT" 
            );
    
    /**
     * 所有的写操作
     */
    protected $writeFun = array(
            "HSET", "HINCRBY", "HINCRBYFLOAT", "HMSET", "HDEL", "HLEN", "HSETNX", "ZADD", "ZINCRBY", "ZREM", "ZREMRANGEBYRANK", "ZREMRANGEBYSCORE", "ZUNIONSTORE", "ZINTERSTORE","SET", "SREM", "ZREM","EXPIRE","EXPIREAT","MULTI","EXEC","LPUSH","RPUSH","LPUSHX","RPUSHX","LPOP","BLPOP",
            "RPOP","BRPOP","BRPOPLPUSH","LINSERT","LREM","LSET","LTRIM","RPOPLPUSH"
            );
    
    /**
     * 系统级操作
     */
    protected $systemFun = array(
            "DBSIZE", "LASTSAVE", "SELECT", "INFO", "CONFIG", "CLIENT", "PING","AUTH","TIME","KEYS","DEL","SLOWLOG","TIME"
            );
    
    /**
     * 禁用的函数,可能对系统造成巨大危害
     */
    protected $disableFun = array(
        "FLUSHDB", "FLUSHALL", "SHUTDOWN", "BGREWRITEAOF", "SLAVEOF", "SAVE", "BGSAVE","CLIENT KILL", "CONFIG RESETSTAT", "CONFIG REWRITE",
        "CONFIG SET", "DEBUG OBJECT", "DEBUG SEGFAULT", "MONITOR", "PSYNC", "SYNC", ""
    );
    
    /**
     * redis实例
     */
    protected $redis = array();

    /**
     * 当前可用的链接节点
     */
    protected $target = '';

    /**
     * 是否需要验证密码
     */
    protected $needAuth = false;

    /**
     * redis访问密码
     */
    private $auth = '';

    /**
     * 链接超时时间，单位秒
     */
    protected $timeOut = 10;

    /**
     * 每次请求之前是否需要PING
     */
    protected $needPing = true;
    
    /**
     * 打开调试日志开关
     * @var unknown
     */
    protected $debug = false;

    /*
     * redis所有节点
     */
    protected $targets = array();
    
    /**
     * redis节点对应auth
     * @var array
     */
    protected $authList = array();
    
    /**
     * 可用管道列表
     * @var array
     */
    protected $pipeList = array();
    
    /**
     * 强制使用首节点
     * 只对设置的最近一次操作有效
     * @var unknown
     */
    protected $forceUseFirstNode = false;

    /**
     * 配置信息
     */
    protected $config;

    /**
     * 实例存储
     */
    protected static $instance = array();

    /**
     * 抽象方法，开始
     */
    abstract protected function onStart();

    /**
     * 抽象方法，请求前
     */
    abstract protected function onBeforeRequest($name, $param);

    /**
     * 抽象方法，请求后
     */
    abstract protected function onAfterRequest($name, $return);

    /**
     * 获取子类可操作实例，不能直接被调用，必须被子类调用
     * @version 1.0
     */
    protected static function instance($name = 'redisBase') {
        if (!isset(self::$instance[$name])) {
            // 不能直接实例化抽象类，必须获得子类的实例
            self::$instance[$name] = new static($name);
        }
        return self::$instance[$name];
    }

    /**
     * 私有化构造函数，子类不可覆盖该方法，否则将导致子类实例化失败
     */
    private function __construct($name = 'redisBase') {
        // 实例化时处理，主要用于设定配置文件等
        $this->_setConfig($name)->onStart();
    }

    /**
     * 通过配置文件设定参数
     */
    private function _setConfig($name = 'redisBase') {
        $config = RedisConfig::gettopic($name);
        if (!empty($config)) {
            $this->config = $config;
        }
        if (empty($this->config)) {
            throw new RedisException('没有找到redis配置信息');
        }
        return $this;
    }

    /**
     * 获取节点配置
     * @param string $key    redis操作key
     * @param string $type 读写操作标识，w表示读写服务器，r表示只读服务器，默认为读服务器
     */
    private function _getTarget($key, $type = 'w') {
        if ($type == 'r' && isset($this->targets['slave'])) {
            $this->target = $this->_hash($key, $this->targets['slave']);
            // 优先读取当前node配置
            if (!empty($this->authList[$this->target])) {
                $this->needAuth = true;
                $this->auth = $this->authList[$this->target];
            } elseif (!empty($this->config['slave_auth'])) {
                $this->needAuth = true;
                $this->auth = $this->config['slave_auth'];
            }
        } else {
            $this->target = $this->_hash($key, $this->targets['master']);
            // 优先读取当前node配置
            if (!empty($this->authList[$this->target])) {
                $this->needAuth = true;
                $this->auth = $this->authList[$this->target];
            } elseif (!empty($this->config['master_auth'])) {
                $this->needAuth = true;
                $this->auth = $this->config['master_auth'];
            }
        }
    }

    /**
     * 根据数据缓存key分片，得到对应数据存储节点
     * @param  string $key redis缓存键
     * @return array 链接节点
     */
    private function _hash($key, $targets) {
        // 设置强制使用首节点
        if ($this->forceUseFirstNode) {
            // 还原设置
            $this->forceUseFirstNode = false;
            return $targets[0];
        }
        // 生成对应key的多项式结果，32位机器有可能是负整数，直接取绝对值
        $hash = abs(crc32($key));
        $count = count($targets);
        $mod = $hash % $count;
        return $targets[$mod];
    }
    
    /**
     * 打开所有可用管道
     */
    public function openMultiPipe() {
        // 管道默认全部走主节点
        foreach ($this->targets['master'] as $k => $v) {
            // 优先读取当前node配置
            if (!empty($this->authList[$v])) {
                $this->needAuth = true;
                $this->auth = $this->authList[$v];
            } elseif (!empty($this->config['master_auth'])) {
                $this->needAuth = true;
                $this->auth = $this->config['master_auth'];
            }
            $this->pipeList[$v] = $this->_connectTarget($v)->MULTI(\Redis::PIPELINE);
        }
        return $this->pipeList;
    }
    
    /**
     * 根据key获得管道对象
     * @param unknown $key
     * @return boolean|boolean|mixed
     */
    public function getMultiPipe($key) {
        if (empty($key)) return false;
        if (!empty($this->pipeList)) {
            $node = $this->_hash($key, $this->targets['master']);
            if (isset($this->pipeList[$node])) {
                return $this->pipeList[$node];
            }
        }
        return false;
    }
    
    /**
     * 执行并关闭所有可用管道
     */
    public function execMultiPipe() {
        $resultArray = array();
        if (!empty($this->pipeList)) {
            foreach ($this->targets['master'] as $v) {
                if (isset($this->pipeList[$v])) {
                    $resultArray[$v] = $this->pipeList[$v]->EXEC();
                }
            }
            $this->pipeList = array();
        }
        return $resultArray;
    }

    /**
     * 建立redis链接,并返回链接对象
     * @param   $target 链接节点
     * @return object
     */
    private function _connectTarget($target) {
        if (isset($this->redis[$target])) {
            // 测试链接是否有效,无效的话重新建立链接
            if ($this->needPing) {
                $PONG = $this->redis[$target]->PING();
                if ($PONG !== '+PONG') {
                    unset($this->redis[$this->target]);
                }
            }
        } 
        if (!isset($this->redis[$target])) {
            //一个redis实例
            $this->redis[$target] = new \Redis();
            $ipInfo = explode(":", $target);
            if (false === $this->redis[$target]->connect($ipInfo[0], $ipInfo[1], $this->timeOut)) {
                // 重试一次
                if (false === $this->redis[$target]->connect($ipInfo[0], $ipInfo[1], $this->timeOut)) {
                    unset($this->redis[$target]);
                    return false;
                }
            }
            // 如果设置了密码
            if ($this->needAuth) {
                $this->redis[$target]->auth($this->auth);
            }
            //如果设置了db
            if (isset($this->config['db'])) {
                $this->redis[$target]->select($this->config['db']);
            }
        }

        return $this->redis[$target];
    }
    
    /**
     * 强制使用首节点
     */
    public function setForceUseNode() {
        $this->forceUseFirstNode = true;
    }

    /**
     * 调用redis魔术方法，转发请求到redis
     * @param  string $name      操作方法
     * @param  array $arguments 参数
     * @return array
     */
    public function __call($name, $arguments) {
        // 请求前调用函数，可用于SLA
        $this->onBeforeRequest($name, $arguments);
        // 记录redis操作状态，为避免redis返回数据量过于巨大，只用简略信息表示redis是否操作成功
        // 检测是否访问到禁用的redis方法
        if (in_array(strtoupper($name), $this->disableFun)) {
            $this->onAfterRequest($name, "调用禁用方法:" . $name);
            return false;
        }
        
        // 根据操作区分读写,使用主从库
        if (in_array(strtoupper($name), $this->readFun)) {
            $this->_getTarget($arguments[0], 'r');
        } elseif (in_array(strtoupper($name), $this->writeFun)) {
            $this->_getTarget($arguments[0]);
        } elseif (in_array(strtoupper($name), $this->systemFun) && !empty($this->config['is_sys'])) {
            // 访问系统级函数需要检测权限
            $this->_getTarget($arguments[0]);
        } else {
            $this->onAfterRequest($name, "调用非法方法:" . $name);
            //throw new \Redis\RedisException("调用非法方法");
            return false;
        }
        //服务器配置都down了
        if (empty($this->target)) {
            $this->onAfterRequest($name, "redis服务器节点配置丢失:" . $name);
            return false;
        }
        
        // 获取链接对象
        $redisObj = $this->_connectTarget($this->target);
        if (empty($redisObj)) {
            unset($this->redis[$this->target]);
            $this->onAfterRequest($name, 'redis服务器链接失败');
            return false;
        }
        // 记录redis请求,必须配置开启后才能记录
        if ($this->debug) {
            self::redisClientSend($this->target, $name, serialize($arguments));
        }
        // 发送请求
        try {
            $ret = call_user_func_array(array($redisObj, $name), $arguments);
            $returnStatus = 'SUCCESS,' . strlen(serialize($ret));
        } catch (\Exception $e) {
            // 注销掉发生异常的链接对象
            unset($this->redis[$this->target]);
            $this->onAfterRequest($name, "redis捕获异常:" . $e->getMessage());
            return false;
        }
        // 记录redis返回，必须配置开启后才能记录
        if ($this->debug) {
            self::redisClientReponse("SUCCESS",strlen(serialize($ret)));
        }
        // 请求后调用函数，可用于SLA
        $this->onAfterRequest($name, $returnStatus);
    
        return $ret;
    }

    /**
     * 记录redis请求日志
     * @param  obj  $point 链接节点
     * @param  string $method 请求方法
     * @param  string $query 请求参数
     * @return null
     */
    public static function redisClientSend($point, $method, $query) {
        $type = 'CS';
        $call_name = 'REDIS.'. $method;
        $timestamp = (int)(microtime(true)*1000);
        $attachment = "QUERY: {$query}";

        $line = "TRACE: {$type} {$call_name} {$point} {$timestamp} {$attachment}";
        file_put_contents('info.log', $line,FILE_APPEND);
    }

    /**
     * 记录redis返回日志
     * @param  string $response_type 返回类型
     * @param  int $response_data_size 返回记录长度
     * @param  string $response_data 返回记录
     * @return null
     */
    public static function redisClientReponse($response_type, $response_data_size, $response_data = '') {
        $type = 'CR';
        $timestamp = (int)(microtime(true)*1000);
        $attachment = "RESPONSE_TYPE: {$response_type} DATA_SIZE {$response_data_size} DATA {$response_data}";

        $line = "TRACE: {$type} {$timestamp} {$attachment}";
        file_put_contents('info.log', $line,FILE_APPEND);
    }

}
