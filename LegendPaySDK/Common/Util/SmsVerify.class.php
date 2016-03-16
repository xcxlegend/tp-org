<?php 

namespace Common\Util;

class SmsVerify
{
	
	const CLASS_PREFIX = 'SMSVERIFY.';

	protected $sessionPrefix;
	protected $expired = 0;
	protected $sendInterval = 0;
	protected $sendIntervalError = '发送太频繁, 请稍后再获取!';
	protected $verifyError = '验证码错误或已失效, 请重新获取!';
	protected $sendError = '验证码错误或已失效, 请重新获取!';

	protected $demo = false;

	public $error;
	protected $sessIntance;


	public function __construct($sessionPrefix,$sessIntance)
	{
		$this->sessionPrefix = self::CLASS_PREFIX.$sessionPrefix;
		$this->sessIntance = $sessIntance;
		if(!defined('NOW_TIME')){
			define('NOW_TIME', time());
		}
	}

	public function setDemo($demo=false)
	{
		$this->demo = $demo;
	}

	// 设置过期时效  设置0为不时效
	public function setExpired($time)
	{
		$this->expired = $time;
	}

	// 设置发送间隔 设置0为无
	public function setSendInterval($time)
	{
		$this->sendInterval = $time;
	}

	public function setSendIntervalError($info){
		$this->sendIntervalError = $info;
	}

	public function setVerifyError($info)
	{
		$this->verifyError = $info;
	}

	// 开始发送
	public function send($phone, $code)
	{
		$time = time();

		$sess = $this->gsetSession($phone);
		if ($sess)
		{
			$last_send = $sess['last_send'];
			if ($this->sendInterval > 0 && $last_send + $this->sendInterval < NOW_TIME){
				$this->error = $this->sendIntervalError;
				return false;
			}
		}
		$res = $this->demo ?: sendSMSCode($phone, $code);
		if ($res !== true){
			$this->error = $res;
			return false;
		}
		$sess['expired'] = $this->expired > 0 ? NOW_TIME + $this->expired : 0;
		$sess['code']    = $code;
		$sess['last_send'] = NOW_TIME;
 		$this->gsetSession('.'.$phone, $sess);
		return true;
	}

	// 进行验证
	public function check($phone, $code)
	{
		$sess = $this->gsetSession('.'.$phone);
		if (!$sess || !$code || $sess['code'] != $code || !$phone || ($sess['expired'] > 0 && $sess['expired'] < NOW_TIME)){
			$this->error = $this->verifyError;
			return false; 
		}
		$this->clearSession();
		return true;
	}

	// 读取或者设置session
	protected function gsetSession($key, $value = '')
	{
		$key = $this->sessionPrefix.$key;
		if ($value === ''){
			return unserialize( $this->sessIntance->get($key) );
		}else
		{
			$value = serialize($value);
			$this->sessIntance->set($key, $value);
		}
	}

	protected function clearSession()
	{
 		$this->gsetSession('phone', null);
	}

}