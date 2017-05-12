<?php
namespace Redis;

/**
 * redis缓存操作类
 * 调用方法：
 * RedisStore::instance()->HSET('key', 'field', 'value');
 * RedisStore::instance()为redis操作实例，单例模式，在请求释放前，一直保持与redis服务器的链接
 * HSET为redis操作方法，具体可参考redis文档
 * 
 * 如需按应用使用不同的实例，可在配置中增加不同的实例配置，可实现不同业务数据存储到不同的位置
 * 配置示例见config/RedisConfig.php
 * 调用示例：
 * RedisStore::instance('other')->HSET('key','field','value');
 *
 * 本类中抛出的异常均为RedisException
 *
 */
class RedisStore extends RedisBase {

	/**
	 * 获取操作实例
     * @param  string $name 用于标识实例名字，同时该名字对应读取redis配置
     * @return  object
	 */
	public static function instance($name = 'redisBase') {
		return parent::instance($name);
	}

	/**
	 * 预处理获取节点配置信息.
	 */
	protected function onStart() {
		$list = array();
		$authList = array();
		if (!empty($this->config['node'])) {
			foreach ($this->config['node'] as $key => $node) {
				if (!empty($node['master'])) {
					$list['master'][] = $node['master'];
				} 
				if (!empty($node['slave'])) {
					$list['slave'][] = $node['slave'];
				}
				if (!empty($node['master_auth'])) {
				    $authList[$node['master']] = $node['master_auth'];
				}
				if (!empty($node['slave_auth'])) {
				    $authList[$node['slave']] = $node['slave_auth'];
				}
			}
			$this->debug = !empty($this->config['debug']) ? $this->config['debug'] : false;
		}
		if (empty($list)) {
			throw new RedisException('没有找到redis配置信息');
		}
        $this->targets = $list;
        $this->authList = $authList;
	}

	/**
	 * 发送请求前处理
	 * @return boolean
	 */
	protected function onBeforeRequest($name, $param) {
		$this->startTime = microtime(true);
		$this->memoryStart = memory_get_usage();
	    //统计
	    if (empty($this->config['no_statistic'])) {
	       //TODOSomething
	    }
		return true;
	}

	/**
	 * 发送请求后处理
	 * @return boolean
	 */
	protected function onAfterRequest($name, $return) {
	    //统计
	    if (empty($this->config['no_statistic'])) {
            //TODOSomething
	    }
		$costTime = round(microtime(true) - $this->startTime, 6);
		$costMem = round((memory_get_usage() - $this->memoryStart) / 1024 / 1024, 6);
        file_put_contents('info.log', "请求执行完毕，执行方法：{$name},消耗时间：{$costTime}S, 消耗内存：{$costMem}MB",FILE_APPEND);
		return true;
	}

}