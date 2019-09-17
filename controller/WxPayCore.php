<?php
namespace app\pay\controller;

use thinK\Controller;

class AliPayCore extends Controller 
{

	protected $VERSION     = '3.0.10';

	protected $appId       = '';
	protected $merchantId  = '';
	protected $notifyUrl   = '';
	protected $signType    = 'MD5';
	protected $payKey      = '';
 
	protected $proxHost    = '0.0.0.0';
	protected $proxPort    = 0;

	protected $sslCertPath = '';
	protected $sslKeyPath  = '';

	public function unifiedOrder( $payInfo, $input, $timeOut = 6  )
	{
		$url = "https://api.mch.weixin.qq.com/pay/unifiedorder";

		if( !isset($payInfo['out_trade_no']) || empty($payInfo['out_trade_no']) ){
			throw new \app\common\exception\WxPayException(['msg'=>'out_trade_no参数未定义']);
		}
		if( !isset($payInfo['body']) || empty($payInfo['body']) ){
			throw new \app\common\exception\WxPayException(['msg'=>'body参数未定义']);
		}
		if( !isset($payInfo['attach']) || empty($payInfo['attach']) ){
			throw new \app\common\exception\WxPayException(['msg'=>'attach参数未定义']);
		}
		if( !isset($payInfo['total_fee']) || empty($payInfo['total_fee']) ){
			throw new \app\common\exception\WxPayException(['msg'=>'total_fee参数未定义']);
		}
		if( !isset($payInfo['trade_type']) || empty($payInfo['trade_type']) ){
			throw new \app\common\exception\WxPayException(['msg'=>'trade_type参数未定义']);
		}
		if( 'JSAPI' === $payInfo['trade_type'] && (!isset($payInfo['openid']) || empty($payInfo['openid'])) ){
			throw new \app\common\exception\WxPayException(['msg'=>'openid参数未定义']);
		}
		if( 'NATIVE' === $payInfo['trade_type'] && (!isset($payInfo['product_id']) || empty($payInfo['product_id'])) ){
			throw new \app\common\exception\WxPayException(['msg'=>'product_id参数未定义']);
		}
		$payInfo['appid']            = $this->appId;
		$payInfo['mch_id']           = $this->merchantId;
		$payInfo['notify_url']       = $this->notifyUrl;
		$payInfo['sign_type']        = $this->signType;
		$payInfo['spbill_create_ip'] = $_SERVER['REMOTE_ADDR'];
		$payInfo['nonce_str']        = $this->getNonceStr();
		$payInfo['sign']             = $this->makeSign($payInfo);
		$xml = $this->getToXml();
		$response = $this->postXmlCurl( $url, $xml );
		$result = $this->getWxPayResult( $response, $payInfo );
		return $result;
	}

	protected function makeSign( $data )
	{
		ksort($data);
		$str_params = $this->getToUrlParams( $data );
		$str_params .= '&key=' . $this->payKey;
		$result = 'MD5' === $data['sign_type'] ? md5($str_params) : hash_hmac( 'sha256', $str_params, $this->payKey );
		return strtoupper($result);
	}

	protected function getNonceStr()
	{
		$chars = 'abcdefghijklmnopqrstuvwxyz0123456789';  
		$str = '';
		for ( $i = 0; $i < 32; $i++ )  {  
			$str .= substr($chars, mt_rand(0, strlen($chars)-1), 1);  
		} 
		return $str;
	}

	protected function getToUrlParams( $params )
	{
		$buff = '';
		foreach ($params as $k => $v)
		{
			if($k != 'sign' && $v != '' && !is_array($v)){
				$buff .= $k . '=' . $v . '&';
			}
		}
		$buff = trim($buff, '&');
		return $buff;
	}

	protected function getToXml( $data )
	{
		if(!is_array( $data ) || count( $data ) <= 0 ){
    		throw new \app\common\exception\WxPayException(['msg'=>'微信数组异常']);
    	}
    	$xml = "<xml>";
    	foreach ($data as $key=>$value)
    	{
    		if (is_numeric($value)){
    			$xml.="<".$key.">".$value."</".$key.">";
    		}else{
    			$xml.="<".$key."><![CDATA[".$value."]]></".$key.">";
    		}
        }
        $xml.="</xml>";
        return $xml; 
	}

	protected function getWxPayResult( $response, $payInfo )
	{
		$result = $this->getFromXml( $response );
		if( 'SUCCESS' != $result['return_code'] ){
			foreach ($result as $key => $value) {
				if( $key != "return_code" && $key != "return_msg" ){
					throw new \app\common\exception\WxPayException(['msg'=>'微信支付参数异常']);
				}
			}
		}
		$this->checkSign( $result, $payInfo );
		return $result;
	}

	protected function checkSign( $data, $payInfo )
	{
		if( !isset($data['sign']) || empty($data['sign']) ){
			throw new \app\common\exception\WxPayException(['msg'=>'签名错误']);
		}
		$response_sign = $this->makeSign( $data );
		if( $payInfo['sign'] != $response_sign ){
			throw new \app\common\exception\WxPayException(['msg'=>'签名错误']);
		}
		return true;
	}

	protected function getFromXml( $response )
	{
		libxml_disable_entity_loader(true);
       	return json_decode(json_encode(simplexml_load_string( $response, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
	}

	protected function postXmlCurl( $url, $xml, $useCert = FALSE, $second = 6 )
	{
		
		if( !isset($this->proxHost) || empty($this->proxHost) ){
			throw new \app\common\exception\WxPayException(['msg'=>'proxy_host参数未定义']);
		}
		if( !isset($this->proxPort) ){
			throw new \app\common\exception\WxPayException(['msg'=>'proxy_port参数未定义']);
		}
		$ch = curl_init();
		$curlVersion = curl_version();
		$ua = "WXPaySDK/".$this->VERSION." (".PHP_OS.") PHP/".PHP_VERSION." CURL/".$curlVersion['version']." ".$this->merchantId;
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
		curl_setopt($ch, CURLOPT_POST, TRUE);
		if( '0.0.0.0' !== $this->proxHost && 0 !== $this->proxPort ){
			curl_setopt($ch, CURLOPT_PROXY, $this->proxHost);
			curl_setopt($ch, CURLOPT_PROXYPORT, $this->proxPort);
		}
		if( TRUE ===  $useCert ){
			if( !isset($this->sslCertPath) && empty($this->sslCertPath)){
				throw new \app\common\exception\WxPayException(['msg'=>'ssl_cert_path参数未定义']);
			}
			if( !isset($this->sslKeyPath) && empty($this->sslKeyPath) ){
				throw new \app\common\exception\WxPayException(['msg'=>'ssl_key_path参数未定义']);
			}
			curl_setopt($ch,CURLOPT_SSLCERTTYPE,'PEM');
			curl_setopt($ch,CURLOPT_SSLCERT, $this->sslCertPath );
			curl_setopt($ch,CURLOPT_SSLKEYTYPE,'PEM');
			curl_setopt($ch,CURLOPT_SSLKEY, $this->sslKeyPath );
		}
		curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
		curl_setopt($ch, CURLOPT_TIMEOUT, $second);

		$data = curl_exec($ch);
		if( $data ){
			curl_close($ch);
			return $data;
		}else{
			curl_close($ch);
			throw new \app\common\exception\WxPayException(['msg'=>'微信支付或者退款失败']);
		}
	}


}