<?php

// +----------------------------------------------------------------------
// | 文件 IlypayEvent.class.php
// +----------------------------------------------------------------------
// | 说明 龙渊余额支付的方法
// +----------------------------------------------------------------------
// | 
// +----------------------------------------------------------------------
// | Author: 谢赤心 Legend. <xcx_legender@qq.com>
// +----------------------------------------------------------------------

namespace V201601\Event\Pay;

use V201601\Event\IPayEvent;

// 暂时全部都没有处理
class IlypayEvent implements IPayEvent
{

	protected $alipay_config = null;

	public function __construct()
	{
	}

	public function submit($order)
	{
	}
	 
	public function notify(&$order)
	{
	}

	public function getOrderNo()
	{
	}

	public function success()
	{
	}
	
	public function fail()
	{
	}

	public function getPayTradeNo(&$order)
	{
		return null;
	}

}