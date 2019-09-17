<?php
namespace app\common\exception;

use app\common\exception\BaseException;

class AliExcception extends BaseException
{
	public $msg       = '支付宝支付参数异常';
	public $errorCode = '10300';
	public function __construct($params = [])
	{
		if (!is_array($params)) {
            return;
        }
        if (array_key_exists('msg', $params)) {
            $this->msg = $params['msg'];
        }
	}
}