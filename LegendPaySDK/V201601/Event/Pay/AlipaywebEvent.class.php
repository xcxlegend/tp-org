<?php
// +----------------------------------------------------------------------
// | 文件 AliwebEvent.class.php
// +----------------------------------------------------------------------
// | 说明 支付宝手机web支付
// +----------------------------------------------------------------------
// | 
// +----------------------------------------------------------------------
// | Author: 谢赤心 Legend. <xcx_legender@qq.com>
// +----------------------------------------------------------------------

namespace V201601\Event\Pay;

use V201601\Event\IPayEvent;
use Common\Util\alipayweb\AlipayNotify;
use Common\Util\alipayweb\AlipaySubmit;

class AlipaywebEvent implements IPayEvent
{

	protected $alipay_config = null;

	public function __construct()
	{
		$this->alipay_config = C('PAYMETHOD_ALIWEB');
	}

	public function submit($order)
	{ 
		/**************************请求参数**************************/

		//返回格式
		$format = "xml";
		//必填，不需要修改

		//返回格式
		$v = "2.0";
		//必填，不需要修改

		//请求号
		$req_id = date('Ymdhis');
		//必填，须保证每次请求都是唯一

		//**req_data详细信息**
 		$notify_url    = C('DOMAIN').$this->alipay_config['notify_url'];
        //需http://格式的完整路径，不能加?id=123这类自定义参数

        //页面跳转同步通知页面路径
        $call_back_url = C('DOMAIN').$this->alipay_config['call_back_url'];
        //需http://格式的完整路径，不能加?id=123这类自定义参数，不能写成http://localhost/

		//操作中断返回地址
		$merchant_url  = C('DOMAIN').$this->alipay_config['merchant_url'];
		//用户付款中途退出返回商户的地址。需http://格式的完整路径，不允许加?id=123这类自定义参数
 
		//卖家支付宝帐户
		$seller_email = 'pay@ilongyuan.cn';
		//必填

		//商户订单号
		$out_trade_no = $order['out_trade_no'];
		//商户网站订单系统中唯一订单号，必填

		//订单名称
		$subject = $order['subject'] ?: '龙渊支付';
		//必填

		//付款金额
		$total_fee = $order['amount']/100;
		//必填

		//请求业务参数详细
		$req_data = '<direct_trade_create_req><notify_url>' . $notify_url . '</notify_url><call_back_url>' . $call_back_url . '</call_back_url><seller_account_name>' . $seller_email . '</seller_account_name><out_trade_no>' . $out_trade_no . '</out_trade_no><subject>' . $subject . '</subject><total_fee>' . $total_fee . '</total_fee><merchant_url>' . $merchant_url . '</merchant_url></direct_trade_create_req>';
		//必填

		/************************************************************/

		//构造要请求的参数数组，无需改动
		//构造要请求的参数数组，无需改动
		$para_token = array(
			"service" 		=> "alipay.wap.trade.create.direct",
			"partner" 		=> trim($this->alipay_config['partner']),
			"sec_id" 		=> trim($this->alipay_config['sign_type']),
			"format"		=> $format,
			"v"				=> $v,
			"req_id"		=> $req_id,
			"req_data"		=> $req_data,
			"_input_charset"	=> trim(strtolower($this->alipay_config['input_charset']))
		);
	 
		//建立请求
		$alipaySubmit = new AlipaySubmit($this->alipay_config);
		$html_text = $alipaySubmit->buildRequestHttp($para_token);

		//URLDECODE返回的信息
		$html_text = urldecode($html_text);

		//解析远程模拟提交后返回的信息
		$para_html_text = $alipaySubmit->parseResponse($html_text);

		//获取request_token
		$request_token = $para_html_text['request_token'];

		//业务详细
		$req_data = '<auth_and_execute_req><request_token>' . $request_token . '</request_token></auth_and_execute_req>';
		//必填

		//构造要请求的参数数组，无需改动
		$parameter = array(
				"service" 	=> "alipay.wap.auth.authAndExecute",
				"partner" 	=> trim($this->alipay_config['partner']),
				"sec_id" 	=> trim($this->alipay_config['sign_type']),
				"format"	=> $format,
				"v"			=> $v,
				"req_id"	=> $req_id,
				"req_data"	=> $req_data,
				"_input_charset"	=> trim(strtolower($this->alipay_config['input_charset']))
		);
	 
		//建立请求
		$html_text = $alipaySubmit->buildRequestForm($parameter, 'get', '确认');
		echo $html_text;
	}
	 
	public function notify(&$order)
	{
		$alipayNotify = new AlipayNotify($this->alipay_config);
		$verify_result = $alipayNotify->verifyNotify();
		if($verify_result) {//验证成功
		/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
			$doc = new \DOMDocument();	
			if ($this->alipay_config['sign_type'] == 'MD5') {
				$doc->loadXML($_POST['notify_data']);
			}
			
			if ($this->alipay_config['sign_type'] == '0001') {
				$doc->loadXML($alipayNotify->decrypt($_POST['notify_data']));
			}
			$data = array();
			if( ! empty($doc->getElementsByTagName( "notify" )->item(0)->nodeValue) ) {
				//商户订单号
				//支付宝交易号
				$data['trade_no'] = $doc->getElementsByTagName( "trade_no" )->item(0)->nodeValue;
				//交易状态
				$trade_status = $doc->getElementsByTagName( "trade_status" )->item(0)->nodeValue;
				$total_fee = $doc->getElementsByTagName( "total_fee" )->item(0)->nodeValue;
				if ($order['amount']/100 != $total_fee){
					return false;
				}
	    		// 用于回复alipay
	    		if($trade_status == 'TRADE_FINISHED' || $trade_status == 'TRADE_SUCCESS') {
					$order['pay_trade_no'] = $data['trade_no'];
					return $data;
				}
			}
			return false;
		}
		else {
		    return false;
		}
	}

	public function notifyReturn()
	{
		$alipayNotify = new AlipayNotify($this->alipay_config);
		$verify_result = $alipayNotify->verifyReturn();
		return $verify_result;
	}

	public function getOrderNo()
	{
		$doc = new \DOMDocument();	
		if ($this->alipay_config['sign_type'] == 'MD5') {
			$doc->loadXML($_POST['notify_data']);
		}
		
		if ($this->alipay_config['sign_type'] == '0001') {
			$doc->loadXML($alipayNotify->decrypt($_POST['notify_data']));
		}
		if( ! empty($doc->getElementsByTagName( "notify" )->item(0)->nodeValue) ) {
			//商户订单号
			$out_trade_no = $doc->getElementsByTagName( "out_trade_no" )->item(0)->nodeValue;
			return $out_trade_no;	 
		}
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