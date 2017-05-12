<?php
namespace Redis;
/**
 * redis运行时异常类
 *
 */
class RedisException extends \Exception {
    /**
     * 把异常对象转换为字符串
     * @return string 
     */
    public function __toString(){
    	return "exception 'RedisException' with message '{$this->message}' in {$this->file}:{$this->line}";
    }
}