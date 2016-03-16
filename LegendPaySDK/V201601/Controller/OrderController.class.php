<?php

// +----------------------------------------------------------------------
// | 文件 OrderController.class.php
// +----------------------------------------------------------------------
// | 说明 订单相关接口
// +----------------------------------------------------------------------
// | [获取游戏购买订单, 获取购买代金券订单, 订单回调]
// +----------------------------------------------------------------------
// | Author: 谢赤心 Legend. <xcx_legender@qq.com>
// +----------------------------------------------------------------------

namespace V201601\Controller;

use Think\Exception;
use V201601\Event\OauthEvent;
use V201601\Event\OpenGamerEvent;
use V201601\Event\OrderEvent;
use V201601\Event\UserEvent;

class OrderController extends V201601BaseController {

	// 获取游戏购买订单
	public function game() {
		// $coupon_id   使用代金券购买 -> 记录
		# code...
		try {

			// $order = [
			// 	'out_trade_no'  => date('YmdHis'),
			// 	'subject' 		=> '支付',
			// 	'amount' 		=> '0.01',
			// ];
			// $this->getMethodInstance('aliweb');
			// $this->PayEvent->submit($order);

			# 参数验证
			# openId用户验证 / 如果是游客账号 不支持充值和龙渊币支付
			# 验证获取或者生成订单 <如果有代金券  代金券验证>
			# 如果是龙渊支付 / -> 走龙渊支付流程
			# 如果是其他支付 则返回订单号
			# #todo test
			// $this->reqs['amount'] = 1;
			// $this->reqs['app_order_id'] = 'lytest_' . date('YmdHis');
			// $this->reqs['app_uid'] = 1;
			// $this->reqs['notify_uri'] = 'http://api.sandbox.test.ilongyuan.cn/api/pay/notify_test';
			// $this->reqs['product_name'] = '钻石10';
			// $this->reqs['product_id'] = 1001;
			// $this->reqs['app_username'] = 'xcx';
			// $this->reqs['channel'] = 'alipay';
			// $this->reqs['user_coupon_id'] = '1';
			####################

			$orderParam = $this->checkGameParam();
			if ($orderParam == false) {
				throw new Exception('err:order:param', API_ERR_PARAM);
			}
			$this->reqs['notify_uri'] = urldecode($this->reqs['notify_uri']);
			extract($this->reqs);

			// 检查用户信息
			$userTK = OauthEvent::getUserByToken($access_token);

			// TODO test
			// $userTK = [
			// 	'uid' => 5,
			// 	'username' => 'xcx',
			// 	'game_id' => 5,
			// ];

			if (!$userTK) {
				throw new Exception('err:user:access_token', API_ERR_TOKEN_ERROR);
			}
			if (is_numeric($userTK['uid'])) {
				// 如果是数字UID 则为老用户
				$openGamer = $userTK;
				$openGamer['openId'] = 0; // 则无openId
			} else {
				// 验证用户信息
				$openId = $userTK['uid'];
				$openGamer = OpenGamerEvent::getOpenGamer($openId);
			}
			if (!$openGamer) {
				throw new Exception('err:gamer:invalid', API_ERR_USER_INVALID);
			}
			$this->reqs['game_id'] = $openGamer['game_id'];
			// 如果存在同一个订单 则直接返回
			$order = OrderEvent::checkGameOrder($this->reqs);
			if ($order) {
				if (D('V201601/Order')->isPayed($order)) {
					throw new Exception('err:order:payed', API_ERR_ORDER_PAYED);
				}
			} else {
				// 否则生成订单
				// 验证 生成订单
				$order = OrderEvent::createGameOrder($this->reqs, $openGamer);
				if (!$order) {
					throw new Exception('err:order:create', API_ERR_ORDER_CREATE);
				}
			}
			$pay_data = !empty($order['pay_data']) ? unserialize($order['pay_data']) : '';
			$order['pay_info'] = !empty($pay_data['pay_info']) ? $pay_data['pay_info'] : '';
			$ret = [
				'out_trade_no' => $order['out_trade_no'],
				'status' => $order['status'],
				'amount' => $order['amount'],
				'pay_info' => $order['pay_info'] ?: '',
			];
			return $this->success($ret);

		} catch (Exception $e) {
			$this->responseException($e);
		}
	}

	// 进行web订单提交 主要为支付宝wap支付使用 -> 已迁移至Public下
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

	// 使用龙渊余额支付
	public function ilypay() {
		try {
			extract($this->reqs);
			if (!$out_trade_no || !$password) {
				throw new Exception('err:ilypay:param', API_ERR_PARAM);
			}
			$order = OrderEvent::getOrderByNo($out_trade_no);

			if ($order['uid'] <= 0) {
				throw new Exception('err:order:ilypay:visitor', API_ERR_ORDER_VISITOR);
			}

			if (D('V201601/Order')->isPayed($order)) {
				throw new Exception('err:order:payed', API_ERR_ORDER_PAYED);
			}

			$user = D('UcenterMember')->find($order['uid']);
			\Think\Log::record("[DEBUG: - USER -]".http_build_query($user));
			if (!$user) {
				throw new Exception('err:user_invalid', API_ERR_USER_INVALID);
			}

			// $res = UserEvent::getUser($user['username'], true, $password);

			$user = UserEvent::checkPaypassword($order['uid'], $password);
			if ($user === NULL) {
				throw new Exception('err:user:no_pay_password', API_ERR_NO_PAY_PASSWORD);
			} elseif ($user === false) {
				throw new Exception('err:user:pay_password', API_ERR_PAY_PASSWORD);
			}

			// $user = D('Member')->where(['uid' => $user['id']])->find();
			if ($user['money'] < $order['amount']) {
				throw new Exception('err:user:not_enough_money', API_ERR_PAY_MONEY);
			}
			if (!UserEvent::useMoney($user, $order['amount'])) {
				throw new Exception('err:user:not_enough_money', API_ERR_PAY_MONEY);
			}

			$order['pay_method'] = 'ilypay';

			OrderEvent::setPayed($order);

			$ret = [
				'status' => $order['status'],
				'amount' => $order['amount'],
			];
			$this->success($ret);
		} catch (Exception $e) {
			$this->responseException($e);
		}
	}

	// 获取购买代金券订单
	public function coupon() {
		// 参考原 Oauth/Controller/OrderController
	}

	protected function checkGameParam() {
		$order_param = array(
			'amount' => $this->reqs['amount'],
			'app_order_id' => $this->reqs['app_order_id'],
			'app_uid' => $this->reqs['app_uid'],
			'notify_uri' => $this->reqs['notify_uri'],
			'product_name' => $this->reqs['product_name'],
			'product_id' => $this->reqs['product_id'],
			'app_username' => $this->reqs['app_username'],
			'channel' => $this->reqs['channel'],
			// 'openId' 			=> $this->reqs['openId'],
		);
		foreach ($order_param as $key => $value) {
			if ($value === '') {
				return false;
			}
		}
		if ($order_param['amount'] <= 0 || !is_numeric($order_param['amount'])) {
			return false;
		}

		$PayEvent = OrderEvent::getMethodInstance($this->reqs['channel']);
		if (!$PayEvent) {
			return false;
		}

		return $order_param;
	}

}