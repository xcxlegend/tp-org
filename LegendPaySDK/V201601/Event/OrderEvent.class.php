<?php

// +----------------------------------------------------------------------
// | 文件 OrderEvent.class.php
// +----------------------------------------------------------------------
// | 说明 订单处理
// +----------------------------------------------------------------------
// |
// +----------------------------------------------------------------------
// | Author: 谢赤心 Legend. <xcx_legender@qq.com>
// +----------------------------------------------------------------------

namespace V201601\Event;

use Common\Util\QueueTask;
use Think\Exception;
use V201601\Event\Discount\UserCouponEvent;
use V201601\Event\IDiscountEvent;
use V201601\Model\OrderModel;

class OrderEvent {
	protected static $methods = ['alipay', 'alipayquick', 'alipayweb', 'ten', 'tenpc', 'upmp', 'ilypay'];

	protected static $methodInstance = null;
	// 获取支付全都实例
	static public function getMethodInstance($method) {
		if (!in_array($method, self::$methods)) {
			return null;
		}
		if (empty(self::$methodInstance[$method])) {
			$method = 'V201601\\Event\\Pay\\' . ucfirst($method) . 'Event';
			self::$methodInstance[$method] = new $method();
		}
		if (!(self::$methodInstance[$method] instanceof IPayEvent)) {
			return null;
		}
		return self::$methodInstance[$method];
	}

	// 根据支付方式获取外部订单号
	static protected function getPayTradeNo($pay_method, &$order) {
		$PayEvent = self::getMethodInstance($pay_method);
		if (!$PayEvent) {
			throw new Exception('err:pay_method:instance', API_ERR_PAY_METHOD);
		}

		$pay_trade_no = $PayEvent->getPayTradeNo($order);
		// 获取外部订单号  如果不需要获取则返回null  获取失败返回false;

		if ($pay_trade_no === false) {
			throw new Exception('err:pay_method:pay_trade_no', API_ERR_PAY_GET_NO);
		} else {
			$order['pay_trade_no'] = $pay_trade_no ?: '';
		};
	}

	static protected function setPayInfo(&$order, $pay_config) {
		$pay_method = $order['pay_method'];
		$PayEvent = self::getMethodInstance($pay_method);
		if (!$PayEvent) {
			throw new Exception('err:pay_method:instance', API_ERR_PAY_METHOD);
		}
		$pay_info = $PayEvent->getPayInfo($order, $pay_config);
		$pay_data['pay_info'] = $pay_info;
		$pay_data['pay_config'] = $pay_config;
		$order['pay_data'] = serialize($pay_data);
	}

	// 获取已经存在的订单
	static public function checkGameOrder(&$reqs) {
		$order = D('V201601/Order')->where(['game_id' => $reqs['game_id'], 'app_order_id' => $reqs['app_order_id']])->find();
		if (!$order) {
			return null;
		} elseif ($order['pay_method'] != $reqs['channel']) {
			self::getPayTradeNo($reqs['channel'], $order);
		}
		// $order['out_trade_no'] = $order['pay_trade_no'] ?: $order['out_trade_no'];
		return $order;
	}

	// 根据内部订单号获取order
	static public function getOrderByNo($out_trade_no) {
		return D('V201601/Order')->where(['out_trade_no' => $out_trade_no])->find();
	}

	// 创建游戏订单
	static public function createGameOrder(&$reqs, &$openGamer) {

		// 1. 检查游戏支付
		// 2. 获取支付信息
		$configed = false;
		$pay_config = '';
		$method = $reqs['channel'];
		$game = D('V201601/Game')->find($openGamer['game_id']);
		// method -> alipay
		if (!empty($game['pay_data']) && !empty($game['pay_data'][$method])) {
			$pay_id = $game['pay_data'][$method]['id'];
			$PayMethod = D('V201601/Paymethod')->find($pay_id);
			if (!empty($PayMethod) && $PayMethod['pay_method'] == $method) {
				$configed = true;
				$pay_config = $PayMethod['pay_config'];
			}
		}

		$order_param = array(
			'amount' => $reqs['amount'],
			'app_order_id' => $reqs['app_order_id'],
			'app_uid' => $reqs['app_uid'],
			'notify_uri' => $reqs['notify_uri'],
			'product_name' => $reqs['product_name'],
			'product_id' => $reqs['product_id'],
			'app_username' => $reqs['app_username'],
			'openId' => $openGamer['openId'] ?: '',
			'uid' => isset($openGamer['uid']) ? $openGamer['uid'] : 0,
			'game_id' => $openGamer['game_id'],
			'pay_method' => $method,
			'is_game' => 1,
			'pack_id' => $reqs['pack_id'] ?: 0,
		);
		$useCoupon = false;
		$hasPayed = false;
		// 如果带有user_coupon_id
		if (isset($reqs['user_coupon_id'])) {

			// 非正式用户不能使用代金券
			if ($order_param['uid'] == 0) {
				throw new Exception('err:order:coupon:visitor', API_ERR_ORDER_VISITOR);
			}

			// 查询用户的代金券
			$userCoupon = UserCouponEvent::checkUserCoupon($openGamer, $reqs['user_coupon_id']);
			if (!$userCoupon) {
				throw new Exception('err:order:coupon:invalid', API_ERR_COUPON_INVALID);
			}

			// 生成代金券信息
			$order_param['object_type'] = 'UserCoupon';
			$order_param['object_data'] = serialize($userCoupon);
			$order_param['discount'] = min($order_param['amount'], $userCoupon['value']);
			$order_param['amount'] -= $order_param['discount'];
			if ($order_param['amount'] == 0) {
				// 如果可以抵用完全则直接进行消费
				// 完全支付
				$hasPayed = true;
			}
			$useCoupon = true;
		}

		$model = D('V201601/Order');
		$order = $model->create($order_param);
		\Think\Log::record("[DEBUG: --ORDER--]".http_build_query($order));
		// 如果有支付方式需要更改out_trade_no
		if (isset($reqs['channel'])) {
			self::getPayTradeNo($reqs['channel'], $order);
			// 如果有支付配置 则使用配置进行订单签名
			if ($configed) {

				self::setPayInfo($order, $pay_config);
			}

		}
		if ($hasPayed) {
			$order['status'] = OrderModel::STATUS_PAYED;
			$order['pay_time'] = NOW_TIME;
		}
		$id = $model->add($order);
		if ($id) {
			$order['id'] = $id;
			// 如果使用了代金券则更改代金券信息
			if ($useCoupon) {
				UserCouponEvent::lockCoupon($userCoupon['id']);
			}

			// 如果是代金券全额支付成功 则直接回调
			if ($hasPayed) {
				$useCoupon && UserCouponEvent::used($order);
				$order['notify_uri'] && self::callbackApp($order);
			}
			$order['out_trade_no'] = $order['pay_trade_no'] ?: $order['out_trade_no'];
			return $order;
		} else {
			throw new Exception('err:server:order', API_ERR_SERVER);
		}
	}

	// 进行支付
	static public function setPayed(&$order) {
		$res = D('V201601/Order')->setPayed($order);
		if ($res) {
			// 如果是购买商品
			if ($order['object_type']) {
				$class = 'V201601\\Event\\Discount\\' . $order['object_type'] . 'Event';
				$Event = new $class;
				if ($Event instanceof IDiscountEvent) {
					$Event->used($order);
				}
			}

			$order['amount'] += $order['discount'];
			self::callbackApp($order);
		}
		return $res;
	}

	static public function callbackApp($order) {
		$param = array(
			'uid' => $order['uid'],
			'amount' => sprintf('%0.2f', $order['amount'] / 100),
			'app_order_id' => $order['app_order_id'],
			'app_uid' => $order['app_uid'],
			'product_id' => $order['product_id'],
		);
		ksort($param);
		reset($param);
		$game_id = $order['game_id'];
		$game = D('V201601/Game')->find($game_id);
		$param['sign'] = md5(http_build_query($param) . $game['app_key'] . $game['app_secret']);
		$uri = $order['notify_uri'] . '?' . http_build_query($param);
		QueueTask::callback($order['id'], $uri);
	}

}