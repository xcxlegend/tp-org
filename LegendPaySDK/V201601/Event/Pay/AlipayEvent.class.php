<?php

// +----------------------------------------------------------------------
// | 文件 AlipayEvent.class.php
// +----------------------------------------------------------------------
// | 说明 处理支付宝的类
// +----------------------------------------------------------------------
// |
// +----------------------------------------------------------------------
// | Author: 谢赤心 Legend. <xcx_legender@qq.com>
// +----------------------------------------------------------------------

namespace V201601\Event\Pay;

use Common\Util\alipay\AlipayNotify;
use Common\Util\alipay\AlipaySubmit;
use V201601\Event\IPayEvent;

class AlipayEvent implements IPayEvent {

	protected $alipay_config = null;

	public function __construct() {
		$this->alipay_config = C('PAYMETHOD_ALIPAY');
	}

	public function submit($order) {

		/**************************请求参数**************************/
		//支付类型
		$payment_type = "1";
		//必填，不能修改
		//服务器异步通知页面路径
		$notify_url = C('DOMAIN') . $this->alipay_config['notify_url'];
		//需http://格式的完整路径，不能加?id=123这类自定义参数

		//页面跳转同步通知页面路径
		$return_url = C('DOMAIN') . $this->alipay_config['return_url'];
		//需http://格式的完整路径，不能加?id=123这类自定义参数，不能写成http://localhost/
		//卖家支付宝帐户
		$seller_email = $this->alipay_config['seller_email'];
		//必填
		//商户订单号
		$out_trade_no = $order['out_trade_no'];
		//商户网站订单系统中唯一订单号，必填
		//订单名称
		$subject = $order['subject'] ?: '龙渊充值';
		//必填
		//付款金额
		$total_fee = $order['amount'];
		//必填
		//订单描述
		$body = $data['body'] ?: '龙渊充值';
		//商品展示地址
		$show_url = 'http://account.ilongyuan.cn';
		//需以http://开头的完整路径，例如：http://www.xxx.com/myorder.html
		//防钓鱼时间戳
		$anti_phishing_key = "";
		//若要使用请调用类文件submit中的query_timestamp函数
		//客户端的IP地址
		$exter_invoke_ip = "";
		//非局域网的外网IP地址，如：221.0.0.1
		/************************************************************/

		//构造要请求的参数数组，无需改动
		$parameter = array(
			"service" => "create_direct_pay_by_user",
			"partner" => trim($this->alipay_config['partner']),
			"payment_type" => $payment_type,
			"notify_url" => $notify_url,
			"return_url" => $return_url,
			"seller_email" => $seller_email,
			"out_trade_no" => $out_trade_no,
			"subject" => $subject,
			"total_fee" => $total_fee,
			"body" => $body,
			"show_url" => $show_url,
			"anti_phishing_key" => $anti_phishing_key,
			"exter_invoke_ip" => $exter_invoke_ip,
			"_input_charset" => trim(strtolower($this->alipay_config['input_charset'])),
		);
		//建立请求
		$alipaySubmit = new AlipaySubmit($this->alipay_config);
		$html_text = $alipaySubmit->buildRequestForm($parameter, "get", "确认");
		echo $html_text;
	}

	public function notify(&$order) {
		if (!empty($order['pay_data']) && !empty($order['pay_data']['pay_config']) ){
			$pay_config = $order['pay_data']['pay_config'];
		}else{
			$pay_config = $this->alipay_config; // default
		}
		// $pay_config['sign_type'] = 'MD5';
		$alipayNotify = new AlipayNotify($pay_config);
		$verify_result = $alipayNotify->verifyNotify();
		$trade_status = $_POST['trade_status'];
		if ($verify_result && $trade_status == 'TRADE_SUCCESS') {
			//验证成功
			$order['pay_trade_no'] = $data['trade_no'] = $_POST['trade_no'];
			return $data;
		} else {
			return false;
		}
	}

	public function getPayInfo($order, $pay_config)
	{
		//pay config 进来的应该是反序列化的数组
		$pay_config = $pay_config ?: C('PAYMETHOD_ALIPAY');
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

	public function getOrderNo() {
		return I('request.out_trade_no');
	}

	public function getPayTradeNo(&$order) {
		return null;
	}

	public function success() {
		echo 'success';
	}

	public function fail() {
		echo 'fail';
	}

}