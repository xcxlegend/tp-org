<?php

/// +----------------------------------------------------------------------
// | 文件 TenEvent.class.php
// +----------------------------------------------------------------------
// | 说明 财付通支付
// +----------------------------------------------------------------------
// | 
// +----------------------------------------------------------------------
// | Author: 谢赤心 Legend. <xcx_legender@qq.com>
// +----------------------------------------------------------------------

namespace V201601\Event\Pay;

use V201601\Event\IPayEvent;
use Common\Util\tenpay\RequestHandler;
use Common\Util\tenpay\WapNotifyResponseHandler;
use Common\Util\tenpay\client\TenpayHttpClient;
use Common\Util\tenpay\client\ClientResponseHandler;

class TenEvent implements IPayEvent
{


	protected $config = null;

	public function __construct()
	{
		$this->config = C('PAYMETHOD_TEN');
	}

	public function submit($order)
	{ 
		/* 创建支付请求对象 */
		$reqHandler = new RequestHandler();
		$reqHandler->init();
		$reqHandler->setKey($this->config['key']);
		//$reqHandler->setGateUrl("https://gw.tenpay.com/gateway/pay.htm");
		//设置初始化请求接口，以获得token_id
		$reqHandler->setGateUrl("http://wap.tenpay.com/cgi-bin/wappayv2.0/wappay_init.cgi");


		$httpClient = new TenpayHttpClient();
		//应答对象
		$resHandler = new ClientResponseHandler();
		//----------------------------------------
		//设置支付参数 
		//----------------------------------------
		$reqHandler->setParameter("total_fee", $order['amount']);  //总金额
		//用户ip
		$reqHandler->setParameter("spbill_create_ip", $_SERVER['REMOTE_ADDR']);//客户端IP
		$reqHandler->setParameter("ver", $this->config['ver']);//版本类型
		$reqHandler->setParameter("bank_type", $this->config['bank_type']); //银行类型，财付通填写0
		$reqHandler->setParameter("callback_url", C('DOMAIN').$this->config['callback_url']);//交易完成后跳转的URL
		$reqHandler->setParameter("bargainor_id", $this->config['partner']); //商户号
		$reqHandler->setParameter("sp_billno", $order['out_trade_no']); //商户订单号
		$reqHandler->setParameter("notify_url", C('DOMAIN').$this->config['notify_url']);//接收财付通通知的URL，需绝对路径
		$reqHandler->setParameter("desc", $order['subject']?:'龙渊支付');
		//$reqHandler->setParameter("attach", "龙渊平台充值");
		//$reqHandler->setParameter("charset", 1);
		//$reqHandler->setParameter("fee_type", 1);
		$reqHandler->createSign();
		$httpClient->setReqContent($reqHandler->getRequestURL());
		$reqUrl = "http://wap.tenpay.com/cgi-bin/wappayv2.0/wappay_gate.cgi?token_id=";
		//后台调用
		if($httpClient->call()) {
			$resHandler->setContent($httpClient->getResContent());
			//获得的token_id，用于支付请求
			$token_id = $resHandler->getParameter('token_id');
			$reqHandler->setParameter("token_id", $token_id);
			$reqUrl .= $token_id;	
		}
		header("Content-type: text/html; charset=utf-8");
		if (!$token_id)
		{
			echo $reqHandler->getRequestURL();
			print_r($resHandler->getAllParameters());
			echo '未授权';
			exit;
		}
		echo '正在跳转到财付通...';
		echo '<script>location.href="'.$reqUrl.'"</script>';
		exit;
	}
	 
	public function notify(&$order)
	{
		$resHandler = new WapNotifyResponseHandler();
		$resHandler->setKey($this->config['key']);

		//判断签名
		if($resHandler->isTenpaySign()) {
			$pay_result = $resHandler->getParameter("pay_result");
			if( "0" == $pay_result  ) {
				$order['pay_trade_no'] = $data['trade_no'] = $resHandler->getParameter("transaction_id");
			    return $data;
			}
		}
		return false;
	}

	public function notifyReturn($order)
	{
		$resHandler = new WapNotifyResponseHandler();
		$resHandler->setKey($this->config['key']);

		//判断签名
		if($resHandler->isTenpaySign()) {
			$pay_result = $resHandler->getParameter("pay_result");
			if( "0" == $pay_result  ) {
			    return true;
			}
		}
		return false;
	}


	public function getPayTradeNo(&$order)
	{
		 return null;
	}


	public function getOrderNo()
	{
		$out_trade_no = IS_POST ? I('request.out_trade_no') : I('sp_billno');
		return $out_trade_no;
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