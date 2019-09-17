<?php
namespace app\common\exception;

use app\common\exception\BaseException;

class WxExcception extends BaseException
{
	public $msg       = '微信支付参数异常';
	public $errorCode = '10400';

	public function __construct( $params = [] )
	{
		if (!is_array($params)) {
            return;
        }
        if (array_key_exists('msg', $params)) {
            $this->msg = $params['msg'];
        }
	}
}