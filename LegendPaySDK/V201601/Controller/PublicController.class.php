<?php 

// +----------------------------------------------------------------------
// | 文件 PublicController.class.php
// +----------------------------------------------------------------------
// | 说明 公开的接口 比如回调
// +----------------------------------------------------------------------
// | 
// +----------------------------------------------------------------------
// | Author: 谢赤心 Legend. <xcx_legender@qq.com>
// +----------------------------------------------------------------------

namespace V201601\Controller;

use Common\Controller\BaseController;
use Think\Log;
use Org\Util\String;
use Exception;
use V201601\Event\OauthEvent;
use V201601\Event\OrderEvent;
use V201601\Model\OrderModel;
use Common\Util\QueueTask;
use V201601\Event\StatisEvent;

class PublicController extends BaseController
{
	protected $reqs = [];

	public function _initialize()
	{
		parent::_initialize();
		// $param = urldecode( http_build_query(I('request.')) );
		// $str = "[DEBUG] - [PARAM: {$param}]";
		// Log::record($str, Log::DEBUG, true);
		$this->reqs = I('request.');
	}
	
	// 支付回调
	public function notify()
	{
		$channel = $this->reqs['channel'];
		unset($_REQUEST['channel'], $_GET['channel'], $_POST['channel']);

		$PayEvent = OrderEvent::getMethodInstance($channel);
		if (!$PayEvent){
			Log::record("[DEBUG] - [NOTIFY] - [err: no channel instance| {$channel}]");
			return;
		}

		$out_trade_no = $PayEvent->getOrderNo();
		if (!$out_trade_no){
			Log::record("[DEBUG] - [NOTIFY] - [err: not get out_trade_no]");
			return $PayEvent->fail();
		}
		$order = OrderEvent::getOrderByNo($out_trade_no);
		if (!$order){
			Log::record("[DEBUG] - [NOTIFY] - [err: no order]");
			return $PayEvent->fail();
		}

		if ($order['status'] == OrderModel::STATUS_FINISH || $order['status'] == OrderModel::STATUS_PAYED ){
			Log::record("[DEBUG] - [NOTIFY] - [err: order payed; id: {$order['id']}]");
			return $PayEvent->fail();
		}
		$res = $PayEvent->notify($order);
		if (!$res){
			Log::record("[DEBUG] - [NOTIFY] - [err: sign err]");
			StatisEvent::notify($order, $this->reqs, (bool)$res);
			return $PayEvent->fail();
		}
		$PayEvent->success();
		$res = OrderEvent::setPayed($order);
		
	}

	public function webpay() {
		extract($this->reqs);
		if (!$out_trade_no) {
			return $this->show('参数错误');
		}
		$order = OrderEvent::getOrderByNo($out_trade_no);

		if (D('V201601/Order')->isPayed($order)) {
			return $this->show('订单已经支付');
		}

		$method = $channel ?: $order['pay_method'];
		$PayEvent = OrderEvent::getMethodInstance($method);

		if (!$PayEvent) {
			return $this->show('支付方式错误');
		}
		$PayEvent->submit($order);
	}

	#todo 手动回调
	// public function callback()
	// {
	// 	extract($this->reqs);
	// 	$order = OrderEvent::getOrderByNo($out_trade_no);
	// 	OrderEvent::callbackApp($order);
	// }

	public function frontReturn()
	{
		$channel = $this->reqs['channel'];
		$PayEvent = OrderEvent::getMethodInstance($channel);
		if (!$PayEvent){
			$this->show('支付方式错误');
			return;
		}
		unset($_GET['channel']);
		if ($PayEvent->notifyReturn()){
			$this->show('支付成功');
		}else{
			$this->show('支付失败');
		}
	}

}