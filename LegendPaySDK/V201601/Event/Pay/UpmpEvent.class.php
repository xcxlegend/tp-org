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
use Common\Util\upmp\UpmpService;
 
class UpmpEvent implements IPayEvent
{

	protected $alipay_config = null;

	public function __construct()
	{
		$this->alipay_config = C('PAYMETHOD_UPMP');
	}

	public function submit($order)
	{ 
		
	}
	 
	public function notify(&$order)
	{
		if (UpmpService::verifySignature($_POST)){// 服务器签名验证成功
		//请在这里加上商户的业务逻辑程序代码
		//获取通知返回参数，可参考接口文档中通知参数列表(以下仅供参考)
			$transStatus = $_POST['transStatus'];// 交易状态
			if (""!=$transStatus && "00"==$transStatus){
				return true;
			}
		}
		return false;
	}

	public function notifyReturn()
	{
		
	}


	public function getPayTradeNo(&$order)
	{
		$req['version']     		= $this->alipay_config['version']; // 版本号
		$req['charset']     		= $this->alipay_config['charset']; // 字符编码
		$req['transType']   		= "01"; // 交易类型
		$req['merId']       		= $this->alipay_config['mer_id']; // 商户代码
		$req['backEndUrl']      	= C('DOMAIN').$this->alipay_config['backUrl']; // 通知URL
		$req['frontEndUrl']     	= C('DOMAIN').$this->alipay_config['frontUrl']; // 前台通知URL(可选)
		$req['orderDescription']	= $order['subject'] ?: '龙渊支付';
		$req['orderTime']   		= date("YmdHis"); // 交易开始日期时间yyyyMMddHHmmss
		//$req['orderTimeout']   		= ""; // 订单超时时间yyyyMMddHHmmss(可选)
		$req['orderNumber'] 		= $order['out_trade_no']; //订单号(商户根据自己需要生成订单号)
		$req['orderAmount'] 		= intval($order['amount']); // 订单金额
		$req['orderCurrency'] 		= "156"; // 交易币种(可选) // 156 RMB
		//$req['reqReserved'] 		= "透传信息"; // 请求方保留域(可选，用于透传商户信息)
		//$merReserved['test']   		= "test";
		//$req['merReserved']   		= \UpmpService::buildReserved($merReserved); // 商户保留域(可选)
		$resp = array ();
		$validResp = UpmpService::trade($req, $resp);
 		if ($validResp && $resp['respCode'] == '00')
		{
			return $resp['tn'];
		}
		else
		{
			return false;
		}	
	}


	public function getOrderNo()
	{
		return I('request.orderNumber');
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