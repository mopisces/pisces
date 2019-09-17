<?php
namespace app\common\exception;

use think\exception\Handle;
use think\Log;
use Exception;
/**
 * 重写Handle的render方法，实现自定义异常消息
 * Class ExceptionHandler
 * @package app\api\library\exception
 */
class ExceptionHandler extends Handle
{
    private $responseCode = 200;//response http code 
    private $msg;               //response http data msg
    private $errorCode;         //response http data errorCode
    public function render(Exception $e)
    {   
        $this->errorCode = $e->errorCode;
        $this->msg = $e->msg;
        $this->recordErrorLog($e)
        return json(['msg' => $this->msg, 'errorCode' => $this->errorCode ,'result'=>null],$this->responseCode);
    }

    /**
     * 将异常写入日志
     * @param Exception $e
     */
    private function recordErrorLog(Exception $e)
    {
        Log::record($e->getMessage(), 'error');
        Log::record($e->getTraceAsString(), 'error');
    }
}
