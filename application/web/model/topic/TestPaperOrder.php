<?php
// +----------------------------------------------------------------------
// | CRMEB [ CRMEB赋能开发者，助力企业发展 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2016～2023 https://www.crmeb.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed CRMEB并不是自由软件，未经许可不能去掉CRMEB相关版权
// +----------------------------------------------------------------------
// | Author: CRMEB Team <admin@crmeb.com>
// +----------------------------------------------------------------------

namespace app\web\model\topic;

use service\AlipayTradeWapService;
use traits\ModelTrait;
use basic\ModelBasic;
use app\web\model\topic\TestPaper;
use app\web\model\user\User;
use app\web\model\user\UserBill;
use app\wap\model\user\WechatUser;
use app\wap\model\routine\RoutineTemplate;
use app\web\model\merchant\MerchantFlowingWater;
use service\SystemConfigService;
use service\WechatService;
use service\WechatTemplateService;
use think\Url;

/**
 * 试卷订单 model
 * Class TestPaperOrder
 */
class TestPaperOrder extends ModelBasic
{
    use ModelTrait;

    protected $insert = ['add_time'];

    protected static $payType = ['weixin' => '微信支付', 'yue' => '余额支付', 'offline' => '线下支付', 'zhifubao' => '支付宝'];


    protected function setAddTimeAttr()
    {
        return time();
    }

    public static function getNewOrderId()
    {
        $count = (int)self::where('add_time', ['>=', strtotime(date("Y-m-d"))], ['<', strtotime(date("Y-m-d", strtotime('+1 day')))])->count();
        return 'wx' . date('YmdHis', time()) . (10000 + $count + 1);
    }

    /**
     * 创建订单试卷订单
     * @param $special
     * @param $pinkId
     * @param $pay_type
     * @param $uid
     * @param $payType
     * @param int $link_pay_uid
     * @param int $total_num
     * @return bool|object
     */
    public static function createTestPaperOrder($testPaper, $uid, $payType, $total_num)
    {
        if (!array_key_exists($payType, self::$payType)) return self::setErrorInfo('选择支付方式有误!');
        $userInfo = User::getUserData($uid);
        if (!$userInfo) return self::setErrorInfo('用户不存在!');
        $total_price = $testPaper->money;
        if (isset($userInfo['level']) && $userInfo['level'] > 0) {
            $total_price = $testPaper->member_money;
        }
        $res = TestPaperObtain::PayTestPaper($testPaper->id, $uid, 2);
        if ($res) return self::setErrorInfo('您已购买试卷，无需再次购买!');
        $orderInfo = [
            'uid' => $uid,
            'order_id' => self::getNewOrderId(),
            'test_id' => $testPaper->id,
            'total_num' => $total_num,
            'total_price' => $total_price,
            'pay_price' => $total_price,
            'pay_type' => $payType,
            'paid' => 0,
            'unique' => md5(time() . '' . $uid . $testPaper->id),
            'mer_id' => $testPaper->mer_id,
            'is_del' => 0
        ];
        $order = self::set($orderInfo);
        if (!$order) return self::setErrorInfo('订单生成失败!');
        return $order;
    }

    /**
     * 微信支付 为 0元时 试卷
     * @param $order_id
     * @param $uid
     * @return bool
     */
    public static function jsPayTestPaperPrice($order_id, $uid)
    {
        $orderInfo = self::where('uid', $uid)->where('order_id', $order_id)->where('is_del', 0)->find();
        if (!$orderInfo) return self::setErrorInfo('订单不存在!');
        if ($orderInfo['paid']) return self::setErrorInfo('该订单已支付!');
        $userInfo = User::getUserData($uid);
        if (!$userInfo) return self::setErrorInfo('用户不存在!');
        self::beginTrans();
        $res1 = UserBill::expend('购买试卷', $uid, 'now_money', 'pay_test_paper', $orderInfo['pay_price'], $orderInfo['id'], $userInfo['now_money'], '支付' . floatval($orderInfo['pay_price']) . '元购买试卷');
        $res2 = self::payTestPaperSuccess($order_id);
        $res = $res1 && $res2;
        self::checkTrans($res);
        return $res;
    }

    /**
     * 试卷微信扫码支付
     * @param $orderId
     * @param string $field
     * @return array|string
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function nativeTestPaperPay($orderId, $field = 'order_id')
    {
        if (is_string($orderId))
            $orderInfo = self::where($field, $orderId)->find();
        else
            $orderInfo = $orderId;
        if (!$orderInfo || !isset($orderInfo['paid'])) exception('支付订单不存在!');
        if ($orderInfo['paid']) exception('支付已支付!');
        if ($orderInfo['pay_price'] <= 0) exception('该支付无需支付!');
        $site_name = SystemConfigService::get('site_name');
        if (!$site_name) exception('支付参数缺少：请前往后台设置->系统设置-> 填写 网站名称');
        return WechatService::nativePay(null, $orderInfo['order_id'], $orderInfo['pay_price'], 'testpaper', self::getSubstrUTf8($site_name . '-考试购买', 30), '', 'NATIVE');
    }

    /**试卷购买支付宝扫码支付
     * @param $orderId
     * @param string $field
     * @return mixed|\SimpleXMLElement|string|\提交表单HTML文本
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function alipayAativeTestPaperPay($orderId, $field = 'order_id')
    {
        if (is_string($orderId))
            $orderInfo = self::where($field, $orderId)->find();
        else
            $orderInfo = $orderId;
        if (!$orderInfo || !isset($orderInfo['paid'])) exception('支付订单不存在!');
        if ($orderInfo['paid']) exception('支付已支付!');
        if ($orderInfo['pay_price'] <= 0) exception('该支付无需支付!');
        return AlipayTradeWapService::init()->AliPayNative($orderInfo['order_id'], $orderInfo['pay_price'], '考试购买', 'testpaper');
    }

    /**试卷余额支付
     * @param $order_id
     * @param $uid
     * @return bool
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function yueTestPaperPay($order_id, $uid)
    {
        $orderInfo = self::where('uid', $uid)->where('order_id', $order_id)->where('is_del', 0)->find();
        if (!$orderInfo) return self::setErrorInfo('订单不存在!');
        if ($orderInfo['paid']) return self::setErrorInfo('该订单已支付!');
        if ($orderInfo['pay_type'] != 'yue') return self::setErrorInfo('该订单不能使用余额支付!');
        $userInfo = User::getUserData($uid);
        if (!$userInfo) return self::setErrorInfo('用户不存在!');
        if ($userInfo['now_money'] < $orderInfo['pay_price']) return self::setErrorInfo('余额不足' . floatval($orderInfo['pay_price']));
        self::beginTrans();
        $res1 = false !== User::bcDec($uid, 'now_money', $orderInfo['pay_price'], 'uid');
        $res2 = UserBill::expend('购买试卷', $uid, 'now_money', 'pay_test_paper', $orderInfo['pay_price'], $orderInfo['id'], bcsub($userInfo['now_money'], $orderInfo['pay_price'], 2), '余额支付' . floatval($orderInfo['pay_price']) . '元购买试卷');
        $res3 = self::payTestPaperSuccess($order_id);
        $res = $res1 && $res2 && $res3;
        self::checkTrans($res);
        return $res;
    }

    /**
     * //TODO 试卷支付成功后
     * @param $orderId
     * @param $notify
     * @return bool
     */
    public static function payTestPaperSuccess($orderId)
    {
        $order = self::where('order_id', $orderId)->where('is_del', 0)->find();
        if (!$order) return false;
        $res1 = self::where('order_id', $orderId)->where('is_del', 0)->update(['paid' => 1, 'pay_time' => time()]);
        $res2 = true;
        $res3 = true;
        if ($res1) {
            try {
                if ($order['pay_type'] != 'yue') {
                    $res2 = UserBill::expend('购买试卷', $order['uid'], $order['pay_type'], 'pay_test_paper', $order['pay_price'], $order['id'], 0, '支付' . floatval($order['pay_price']) . '元购买试卷');
                }
                $res3 = MerchantFlowingWater::setMerchantFlowingWater($order, 5);
                TestPaperObtain::setUserTestPaper($orderId, $order['test_id'], $order['uid'], 2, 1);
            } catch (\Throwable $e) {
            }
        }
        $site_url = SystemConfigService::get('site_url');
        try {
            $wechat_notification_message = SystemConfigService::get('wechat_notification_message');
            if ($wechat_notification_message == 1) {
                $title = TestPaper::getName($order['test_id']);
                WechatTemplateService::sendTemplate(WechatUser::where('uid', $order['uid'])->value('openid'), WechatTemplateService::ORDER_PAY_SUCCESS, [
                    'character_string2' => $orderId,
                    'thing3' => mb_substr($title, 0, 20, 'utf-8'),
                    'amount5' => $order['pay_price'],
                    'time4' => date('Y-m-d H:i:s', time())
                ], $site_url . Url::build('wap/topic/question_user'));
                WechatTemplateService::sendAdminNoticeTemplate($order['mer_id'], [
                    'character_string2' => $orderId,
                    'thing3' => mb_substr($title, 0, 20, 'utf-8'),
                    'amount5' => $order['pay_price'],
                    'time4' => date('Y-m-d H:i:s', time())
                ]);
            }
        } catch (\Throwable $e) {
        }
        $res = $res1 && $res2 && $res3;
        return false !== $res;
    }

}
