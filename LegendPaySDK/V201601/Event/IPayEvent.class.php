<?php 

namespace V201601\Event;

interface IPayEvent
{
	// 从回调数据中获取内部订单号
	public function getOrderNo();
	// 验证回调
	public function notify(&$order);
	// 回复回调成功
	public function success();
	// 回复回调失败
	public function fail();
	// 提交web订单
	public function submit($order);
	// 获取外部支付订单
	public function getPayTradeNo(&$order);

}