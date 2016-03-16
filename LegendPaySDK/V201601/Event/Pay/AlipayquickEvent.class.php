<?php

// +----------------------------------------------------------------------
// | 文件 AliquickEvent.class.php
// +----------------------------------------------------------------------
// | 说明 快捷支付
// +----------------------------------------------------------------------
// | 
// +----------------------------------------------------------------------
// | Author: 谢赤心 Legend. <xcx_legender@qq.com>
// +----------------------------------------------------------------------

namespace V201601\Event\Pay;

use V201601\Event\IPayEvent;
use Common\Util\alipayquick\AlipayNotify;

class AlipayquickEvent implements IPayEvent
{

	protected $alipay_config = null;

	public function __construct()
	{
		$this->alipay_config = C('PAYMETHOD_ALIQUICK');
	}

	public function submit($order)
	{
		return;
	}
	 
	public function notify(&$order)
	{
		if (!empty($order['pay_data']) && !empty($order['pay_data']['pay_config']) ){
			$pay_config = $order['pay_data']['pay_config'];
		}else{
			$pay_config = C('PAYMETHOD_ALIQUICK'); // default
		}
 		$alipayNotify = new AlipayNotify($pay_config);
		$verify_result = $alipayNotify->verifyNotify();
		$trade_status = $_REQUEST['trade_status'];
		if($verify_result && ( $trade_status == 'TRADE_FINISHED' || $trade_status == 'TRADE_SUCCESS' ) ) {//验证成功
			$order['pay_trade_no'] = $data['trade_no'] = $_REQUEST['trade_no'];
			return $data;
		}
		else {
		    return false;
		}
	}

	public function getPayInfo($order, $pay_config)
	{
		//pay config 进来的应该是反序列化的数组
		$pay_config = $pay_config ?: C('PAYMETHOD_ALIQUICK');
		$subject = $order['subject'] ?: '龙渊充值';
		//必填
		$total_fee = $order['amount'];
		//必填
		$body = !empty($order['body']) ? $order['body'] : '龙渊充值';
		$parameters = [
			'service' => 'mobile.securitypay.pay',
			'partner' => trim($pay_config['partner']),
			'payment_type' => "1",
			'notify_url' => 'http://'.$_SERVER['SERVER_NAME'].$pay_config['notify_url'],
			'return_url' => 'http://'.$_SERVER['SERVER_NAME'].$pay_config['return_url'],
			'seller_id' => $pay_config['seller_email'],
			'out_trade_no' => $order['out_trade_no'],
			'subject' => $subject,
			'it_b_pay' => '30m',
			'total_fee' => sprintf('%.2f', $total_fee/100),
			'body' => $body,
			'_input_charset' => trim(strtolower($pay_config['input_charset'])),
			];
		ksort($parameters);
		array_walk($parameters, [$this, 'formatParams']);
		$signStr = urldecode(http_build_query($parameters));
		$pkey_id = openssl_get_privatekey($pay_config['rsa_private_key']);
	    openssl_sign($signStr, $sign, $pkey_id, OPENSSL_ALGO_SHA1);
	    $sign = urlencode( base64_encode($sign) );
		$orderInfo = $signStr."&sign=\"{$sign}\"&sign_type=\"RSA\"";
\Think\Log::record('APP DEBUG: -----------'.$orderInfo);
		return $orderInfo;
	}

	protected function formatParams(&$value, $key)
	{
		$value = sprintf('"%s"', $value);
	}

	public function getOrderNo()
	{
		return I('request.out_trade_no');
	}

	public function getPayTradeNo(&$order)
	{
		return null;
	}

	public function success()
	{
		echo 'success';
	}
	
	public function fail()
	{
		echo 'fail';
	}

}