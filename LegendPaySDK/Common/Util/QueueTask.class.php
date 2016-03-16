<?php 

namespace Common\Util;

// 队列任务
class QueueTask
{

	const HOST 			= '';
	const REGISTER_SYNC = '/user/register';
	const REGISTER 		= '/user/register_immediately';
	const CALLBACK_SYNC = '/pay/callback';
	const CALLBACK 		= '/pay/callback_immediately';

	const STATIS_HOST   = '';
	const STATIS_LOGIN  = '/DCAccount/login';

	const STATIS_SMS_REGISTER = '/DCAccount/registerSmsSend';
	const STATIS_SMS_BIND  = '/DCAccount/bindSmsSend';
	

	// 异步注册
	/** 
	 * [异步注册]
	 * @author Legend. <xcx_legender@qq.com>
	 * @param $uid UCID, $username, $password; $is_mobile 是否为手机注册账号
	 * @return 
	 */
	static public function register($uid, $username, $password, $mobile, $email)
	{
		$param = compact('uid', 'username', 'password', 'mobile', 'email');
		self::doHttp(self::HOST.self::REGISTER_SYNC, $param, false, true);
	}

	// 异步回调游戏
	/** 
	 * [异步回调游戏]
	 * @author Legend. <xcx_legender@qq.com>
	 * @param $id orderId 订单ID; $notify_uri 回调的路径包括全部参数
	 * @return 
	 */
	static public function callback($id, $notify_uri)
	{
		$param = compact('id', 'notify_uri');
		$res = self::doHttp(self::HOST.self::CALLBACK_SYNC, $param, false, true);
		\Think\Log::record("[callback] - [uri: ".self::HOST.self::CALLBACK_SYNC."] - [param: ".http_build_query($param)."]");
		\Think\Log::record("[callback-return: {$res}]");
	}


	static public function statis($appId, $packageId, $accountId, $channelId = 'ly', $devId = '')
	{
		$ts = time();
		$packageId = $packageId ?: '0';
		$param = compact('appId', 'packageId', 'accountId', 'channelId', 'devId', 'ts');
		// gameServer
		$body = json_encode($param);
		$res = self::doHttp(self::STATIS_HOST.self::STATIS_LOGIN, $body, false, true);
	}



	static public function phoneSendStatis($type = 'register', $appId, $packageId, $mobile, $isok)
	{
		$ts = time();
		$channelId = 'ly';
		$packageId = $packageId ?: '';
		$devId = '';
		$param = compact('appId', 'packageId', 'channelId', 'mobile', 'isok', 'ts', 'devId');
		$body = json_encode($param);
		$uri = $type == 'bind' ? self::STATIS_SMS_BIND : self::STATIS_SMS_REGISTER;
		$res = self::doHttp(self::STATIS_HOST.$uri, $body, false, true);
	}



	static protected function doHttp($url, $params = false, $header= false, $post=false){
	    if(is_array($params)){
	        $params = http_build_query($params);
	    }
	    $ch = curl_init();
	    if (!$post) {
	        if(!empty($params)) {
	            curl_setopt($ch, CURLOPT_URL, $url."?".$params);
	        } else {
	            curl_setopt($ch, CURLOPT_URL, $url);
	        }
	    }
	    else {
	        curl_setopt($ch, CURLOPT_URL, $url);
	        curl_setopt($ch, CURLOPT_POST, 1);
	        curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
	    }

	    curl_setopt($ch, CURLOPT_HEADER, false);
	    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

	    if (is_array($header)) {
	        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
	    } else {
	        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Expect:'));
	    }
	    if (stripos($url, 'https://') === 0) {
	        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
	    }
	    $result = curl_exec($ch);
	    curl_close($ch);
	    return $result;
	}

}