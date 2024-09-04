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

namespace app\web\model\special;

use app\web\model\user\User;
use app\web\model\user\UserBill;
use app\wap\model\user\WechatUser;
use basic\ModelBasic;
use behavior\wechat\PaymentBehavior;
use app\web\model\user\MemberShip;
use app\web\model\material\DataDownloadBuy;
use app\web\model\merchant\MerchantFlowingWater;
use app\wap\model\topic\TestPaperObtain;
use service\HookService;
use service\SystemConfigService;
use service\WechatService;
use service\WechatTemplateService;
use service\AlipayTradeWapService;
use app\wap\model\wap\SmsTemplate;
use think\Cache;
use think\Url;
use traits\ModelTrait;

/**订单表
 * Class StoreOrder
 * @package app\web\model\special
 */
class StoreOrder extends ModelBasic
{
    use ModelTrait;

    protected $insert = ['add_time'];

    protected static $payType = ['weixin' => '微信支付', 'yue' => '余额支付', 'offline' => '线下支付', 'zhifubao' => '支付宝'];

    protected static $deliveryType = ['send' => '商家配送', 'express' => '快递配送'];

    protected function setAddTimeAttr()
    {
        return time();
    }

    protected function setCartIdAttr($value)
    {
        return is_array($value) ? json_encode($value) : $value;
    }

    protected function getCartIdAttr($value)
    {
        return json_decode($value, true);
    }

    public static function getPinkOrderId($id)
    {
        return self::where('id', $id)->value('order_id');
    }


    public static function cacheOrderInfo($uid, $cartInfo, $priceGroup, $cacheTime = 600)
    {
        $subjectUrl = getUrlToDomain();
        $key = md5(time());
        Cache::store("redis")->set($subjectUrl . 'user_order_' . $uid . $key, compact('cartInfo', 'priceGroup'), $cacheTime);
        return $key;
    }

    public static function getCacheOrderInfo($uid, $key)
    {
        $subjectUrl = getUrlToDomain();
        $cacheName = $subjectUrl . 'user_order_' . $uid . $key;
        if (!Cache::store("redis")->has($cacheName)) return null;
        return Cache::store("redis")->get($cacheName);
    }

    public static function clearCacheOrderInfo($uid, $key)
    {
        $subjectUrl = getUrlToDomain();
        Cache::store("redis")->clear($subjectUrl . 'user_order_' . $uid . $key);
    }

    public static function getSpecialIds($uid)
    {
        return self::where(['is_del' => 0, 'paid' => 1, 'uid' => $uid, 'is_gift' => 0])->column('cart_id');
    }

    /**
     * 获取专题订单列表
     * @param $type
     * @param $page
     * @param $limit
     * @param $uid
     * @return array
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function getSpecialOrderList($type, $page, $limit, $uid)
    {
        $model = self::where(['a.is_del' => 0, 's.is_del' => 0, 'a.uid' => $uid, 'a.paid' => 1])->order('a.add_time desc')->alias('a')->join('__SPECIAL__ s', 'a.cart_id=s.id');
        switch ($type) {
            case 2:
                $model = $model->where(['a.is_gift' => 0, 'a.combination_id' => 0, 'a.pink_id' => 0, 'a.type' => 0]);
                break;
        }
        $list = $model->field(['a.*', 's.title', 's.image', 's.money', 's.pink_number', 's.is_light'])->page($page, $limit)->select();
        $list = count($list) ? $list->toArray() : [];
        foreach ($list as &$item) {
            $item['image'] = get_oss_process($item['image'], 4);
            $item['is_draw'] = false;
        }
        $page++;
        return compact('list', 'page');
    }


    /**
     * 获取订单的专题详情信息
     * @param $order_id 订单号
     * @return bool
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function getOrderIdToSpecial($order_id, $uid)
    {
        $order = self::where(['is_del' => 0, 'order_id' => $order_id, 'uid' => $uid])->find();
        if (!$order) return self::setErrorInfo('订单不存在或给订单不是您的!');
        if (!$order->cart_id) return self::setErrorInfo('订单专题不存在!');
        $special = Special::PreWhere()->where(['id' => $order->cart_id])->find();
        if (!$special) return self::setErrorInfo('赠送的专题已下架,或已被删除!');
        $special->abstract = self::HtmlToMbStr($special->abstract);
        return $special->toArray();
    }


    /**
     * 创建订单专题订单
     * @param $special
     * @param $pinkId
     * @param $pay_type
     * @param $uid
     * @param $payType
     * @param int $link_pay_uid
     * @param int $total_num
     * @return bool|object
     */
    public static function createSpecialOrder($special, $pay_type_num, $uid, $payType)
    {
        if (!array_key_exists($payType, self::$payType)) return self::setErrorInfo('选择支付方式有误!');
        $userInfo = User::getUserData($uid);
        if (!$userInfo) return self::setErrorInfo('用户不存在!');
        $total_price = 0;
        switch ((int)$pay_type_num) {
            case 2:
                //自己买
                $total_price = $special->money;
                if (isset($userInfo['level']) && $userInfo['level'] > 0 && $special->member_pay_type == 1 && $special->member_money > 0) {
                    $total_price = $special->member_money;
                } else if (isset($userInfo['level']) && $userInfo['level'] > 0 && $special->member_pay_type == 0) {
                    return self::setErrorInfo('会员免费，该专题您不需要支付!');
                }
                $res = SpecialBuy::PaySpecial($special->id, $uid);
                if ($res) return self::setErrorInfo('您已购买专题，无需再次购买!');
                break;
            default:
                return self::setErrorInfo('购买方式有误!');

        }
        $orderInfo = [
            'uid' => $uid,
            'mer_id' => $special->mer_id,
            'order_id' => self::getNewOrderId(),
            'cart_id' => $special->id,
            'total_num' => 1,
            'total_price' => $total_price,
            'pay_price' => $total_price,
            'pay_type' => $payType,
            'combination_id' => 0,
            'is_gift' => 0,
            'pink_time' => 0,
            'paid' => 0,
            'pink_id' => 0,
            'unique' => md5(time() . '' . $uid . $special->id),
            'cost' => $total_price,
            'link_pay_uid' => 0,
            'spread_uid' => $userInfo['spread_uid'] ? $userInfo['spread_uid'] : 0,
            'is_del' => 0,
        ];
        $order = self::set($orderInfo);
        if (!$order) return self::setErrorInfo('订单生成失败!');
        StoreOrderStatus::status($order['id'], 'cache_key_create_order', '订单生成');
        return $order;
    }

    /**创建会员订单
     * @param $uid
     * @param $kid
     * @return bool|object
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function cacheMemberCreateOrder($uid, $id, $payType)
    {
        if (!array_key_exists($payType, self::$payType)) return self::setErrorInfo('选择支付方式有误!');
        $userInfo = User::getUserData($uid);
        if (!$userInfo) return self::setErrorInfo('用户不存在!');
        if ($userInfo['level'] && $userInfo['is_permanent']) return self::setErrorInfo('您是永久会员，无需续费!');
        $member = MemberShip::where('id', $id)->where('is_publish', 1)->where('is_del', 0)->where('type', 1)->find();
        if ($member['is_free']) {
            if (self::be(['uid' => $uid, 'member_id' => $id, 'is_del' => 0])) return self::setErrorInfo('免费会员不能重复领取!');
        }
        $orderInfo = [
            'uid' => $uid,
            'order_id' => self::getNewOrderId(),
            'type' => 1,
            'member_id' => $id,
            'total_num' => 1,
            'total_price' => $member['original_price'],
            'pay_price' => $member['price'],
            'pay_type' => $payType,
            'combination_id' => 0,
            'is_gift' => 0,
            'pink_time' => 0,
            'paid' => 0,
            'pink_id' => 0,
            'unique' => md5(time() . '' . $uid . $id),
            'cost' => $member['original_price'],
            'link_pay_uid' => 0,
            'spread_uid' => $userInfo['spread_uid'] ? $userInfo['spread_uid'] : 0,
            'is_del' => 0,
        ];
        $order = self::set($orderInfo);
        if (!$order) return self::setErrorInfo('订单生成失败!');
        StoreOrderStatus::status($order['id'], 'cache_key_create_order', '订单生成');
        return $order;
    }

    public static function getNewOrderId()
    {
        $count = (int)self::where('add_time', ['>=', strtotime(date("Y-m-d"))], ['<', strtotime(date("Y-m-d", strtotime('+1 day')))])->count();
        return 'wx' . date('YmdHis', time()) . (10000 + $count + 1);
    }

    public static function changeOrderId($orderId)
    {
        $ymd = substr($orderId, 2, 8);
        $key = substr($orderId, 16);
        return 'wx' . $ymd . date('His') . $key;
    }

    /**专题微信支付
     * @param $orderId
     * @param string $field
     * @return array|string
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function jsSpecialPay($orderId, $field = 'order_id')
    {
        if (is_string($orderId))
            $orderInfo = self::where($field, $orderId)->find();
        else
            $orderInfo = $orderId;
        if (!$orderInfo || !isset($orderInfo['paid'])) exception('支付订单不存在!');
        if ($orderInfo['paid']) exception('支付已支付!');
        if ($orderInfo['pay_price'] <= 0) exception('该支付无需支付!');
        $site_name = SystemConfigService::get('site_name');
        $openid = WechatUser::uidToOpenid($orderInfo['uid']);
        return WechatService::jsPay($openid, $orderInfo['order_id'], $orderInfo['pay_price'], 'special', self::getSubstrUTf8($site_name, 30));
    }

    /**
     * 专题微信扫码支付
     * @param $orderId
     * @param string $field
     * @return array|string
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function nativeSpecialPay($orderId, $field = 'order_id')
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
        return WechatService::nativePay(null, $orderInfo['order_id'], $orderInfo['pay_price'], 'special', self::getSubstrUTf8($site_name . '-专题购买', 30), '', 'NATIVE');
    }

    /**专题购买支付宝扫码支付
     * @param $orderId
     * @param string $field
     * @return mixed|\SimpleXMLElement|string|\提交表单HTML文本
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function alipayAativeSpecialPay($orderId, $field = 'order_id')
    {
        if (is_string($orderId))
            $orderInfo = self::where($field, $orderId)->find();
        else
            $orderInfo = $orderId;
        if (!$orderInfo || !isset($orderInfo['paid'])) exception('支付订单不存在!');
        if ($orderInfo['paid']) exception('支付已支付!');
        if ($orderInfo['pay_price'] <= 0) exception('该支付无需支付!');
        return AlipayTradeWapService::init()->AliPayNative($orderInfo['order_id'], $orderInfo['pay_price'], '专题购买', 'special');
    }

    /**专题余额支付
     * @param $order_id
     * @param $uid
     * @return bool
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function yuePay($order_id, $uid)
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
        $res2 = UserBill::expend('购买专题', $uid, 'now_money', 'pay_product', $orderInfo['pay_price'], $orderInfo['id'], bcsub($userInfo['now_money'], $orderInfo['pay_price'], 2), '余额支付' . floatval($orderInfo['pay_price']) . '元购买专题');
        $res3 = self::paySuccess($order_id);
        try {
            HookService::listen('yue_pay_product', $userInfo, $orderInfo, false, PaymentBehavior::class);
        } catch (\Exception $e) {
            self::rollbackTrans();
            return self::setErrorInfo($e->getMessage());
        }
        $res = $res1 && $res2 && $res3;
        self::checkTrans($res);
        return $res;
    }

    /**
     * 微信支付 为 0元时
     * @param $order_id
     * @param $uid
     * @return bool
     */
    public static function jsPayPrice($order_id, $uid)
    {
        $orderInfo = self::where('uid', $uid)->where('order_id', $order_id)->where('is_del', 0)->find();
        if (!$orderInfo) return self::setErrorInfo('订单不存在!');
        if ($orderInfo['paid']) return self::setErrorInfo('该订单已支付!');
        $userInfo = User::getUserData($uid);
        if (!$userInfo) return self::setErrorInfo('用户不存在!');
        self::beginTrans();
        $res1 = UserBill::expend('购买专题', $uid, 'now_money', 'pay_product', $orderInfo['pay_price'], $orderInfo['id'], $userInfo['now_money'], '支付' . floatval($orderInfo['pay_price']) . '元购买专题');
        $res2 = self::paySuccess($order_id);
        $res = $res1 && $res2;
        self::checkTrans($res);
        return $res;
    }

    /**
     * 微信支付 为 0元时
     * @param $order_id
     * @param $uid
     * @return bool
     */
    public static function jsPayMePrice($order_id, $uid)
    {
        $orderInfo = self::where('uid', $uid)->where('order_id', $order_id)->where('is_del', 0)->find();
        if (!$orderInfo) return self::setErrorInfo('订单不存在!');
        if ($orderInfo['paid']) return self::setErrorInfo('该订单已支付!');
        $userInfo = User::getUserData($uid);
        if (!$userInfo) return self::setErrorInfo('用户不存在!');
        self::beginTrans();
        $res1 = UserBill::expend('购买会员', $uid, 'now_money', 'pay_vip', $orderInfo['pay_price'], $orderInfo['id'], $userInfo['now_money'], '支付' . floatval($orderInfo['pay_price']) . '元购买会员');
        $res2 = self::payMeSuccess($order_id);
        $res = $res1 && $res2;
        self::checkTrans($res);
        return $res;
    }

    /**会员微信支付
     * @param $orderId
     * @param string $field
     * @return array|string
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function jsPayMember($orderId, $field = 'order_id')
    {
        if (is_string($orderId))
            $orderInfo = self::where($field, $orderId)->find();
        else
            $orderInfo = $orderId;
        if (!$orderInfo || !isset($orderInfo['paid'])) exception('支付订单不存在!');
        if ($orderInfo['paid']) exception('支付已支付!');
        if ($orderInfo['pay_price'] <= 0) exception('该支付无需支付!');
        $openid = WechatUser::uidToOpenid($orderInfo['uid']);
        return WechatService::jsPay($openid, $orderInfo['order_id'], $orderInfo['pay_price'], 'member', SystemConfigService::get('site_name'));
    }

    /**
     * 会员微信扫码支付
     * @param $orderId
     * @param string $field
     * @return array|string
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function nativePayMember($orderId, $field = 'order_id')
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
        return WechatService::nativePay(null, $orderInfo['order_id'], $orderInfo['pay_price'], 'member', self::getSubstrUTf8($site_name . '-会员购买', 30), '', 'NATIVE');
    }

    /**会员阿里云扫码支付
     * @param $orderId
     * @param string $field
     * @return mixed|\SimpleXMLElement|string|\提交表单HTML文本
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function alipayAativePayMember($orderId, $field = 'order_id')
    {
        if (is_string($orderId))
            $orderInfo = self::where($field, $orderId)->find();
        else
            $orderInfo = $orderId;
        if (!$orderInfo || !isset($orderInfo['paid'])) exception('支付订单不存在!');
        if ($orderInfo['paid']) exception('支付已支付!');
        if ($orderInfo['pay_price'] <= 0) exception('该支付无需支付!');
        return AlipayTradeWapService::init()->AliPayNative($orderInfo['order_id'], $orderInfo['pay_price'], '会员购买', 'member');
    }

    public static function yuePayMember($order_id, $uid)
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
        $res3 = self::payMeSuccess($order_id);
        $res = $res1 && $res3;
        self::checkTrans($res);
        return $res;
    }

    /**
     * //TODO 专题支付成功后
     * @param $orderId
     * @param $notify
     * @return bool
     */
    public static function paySuccess($orderId)
    {
        $order = self::where('order_id', $orderId)->where('type', 0)->find();
        if (!$order) return false;
        User::bcInc($order['uid'], 'pay_count', 1, 'uid');
        $res1 = self::where('order_id', $orderId)->where('type', 0)->update(['paid' => 1, 'pay_time' => time()]);
        $res2 = true;
        $res3 = true;
        if ($res1 && $order['pay_type'] != 'yue') {
            $res2 = UserBill::expend('购买专题', $order['uid'], $order['pay_type'], 'pay_product', $order['pay_price'], $order['id'], 0, '支付' . floatval($order['pay_price']) . '元购买专题');
        }
        if ($res1) {
            $res3 = MerchantFlowingWater::setMerchantFlowingWater($order, 0);
        }
        if ($order['combination_id'] == 0 && $res1 && !$order['refund_status'] && !$order['is_gift']) {
            //如果是专栏，记录专栏下所有专题购买。
            SpecialBuy::setAllBuySpecial($orderId, $order['uid'], $order['cart_id']);
            TestPaperObtain::setTestPaper($orderId, $order['uid'], $order['cart_id'], 2);
            DataDownloadBuy::setDataDownload($orderId, $order['uid'], $order['cart_id'], 0);
            try {
                //专题返佣
                User::backOrderBrokerage($order);
            } catch (\Throwable $e) {
            }
        }
        StoreOrderStatus::status($order['id'], 'pay_success', '用户付款成功');
        $site_url = SystemConfigService::get('site_url');
        try {
            $wechat_notification_message = SystemConfigService::get('wechat_notification_message');
            if ($wechat_notification_message == 1) {
                $title = Special::getName($order['cart_id']);
                WechatTemplateService::sendTemplate(WechatUser::where('uid', $order['uid'])->value('openid'), WechatTemplateService::ORDER_PAY_SUCCESS, [
                    'character_string2' => $orderId,
                    'thing3' => mb_substr($title, 0, 20, 'utf-8'),
                    'amount5' => $order['pay_price'],
                    'time4' => date('Y-m-d H:i:s', time())
                ], $site_url . Url::build('wap/special/grade_special'));
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

    /**
     * //TODO 会员支付成功后
     * @param $orderId
     * @param $notify
     * @return bool
     */
    public static function payMeSuccess($orderId)
    {
        $order = self::where('order_id', $orderId)->where('type', 1)->find();
        if (!$order) return false;
        $resMer = true;
        $res2 = true;
        $res1 = self::where('order_id', $orderId)->where('type', 1)->update(['paid' => 1, 'pay_time' => time()]);
        $userInfo = User::getUserData($order['uid']);
        if ($order['type'] == 1 && $res1 && !$order['refund_status']) {
            if ($order['pay_type'] != 'yue') {
                $res2 = UserBill::expend('购买会员', $order['uid'], $order['pay_type'], 'pay_vip', $order['pay_price'], $order['id'], 0, '支付' . floatval($order['pay_price']) . '元购买会员');
            }
            $resMer = MemberShip::getUserMember($order, $userInfo);
            try {
                //会员返佣
                User::backOrderBrokerageMember($order);
            } catch (\Throwable $e) {
            }
        }
        $site_url = SystemConfigService::get('site_url');
        try {
            $wechat_notification_message = SystemConfigService::get('wechat_notification_message');
            if ($wechat_notification_message == 1) {
                $title = MemberShip::getName($order['member_id']);
                WechatTemplateService::sendTemplate(WechatUser::where('uid', $order['uid'])->value('openid'), WechatTemplateService::ORDER_PAY_SUCCESS, [
                    'character_string2' => $orderId,
                    'thing3' => $title . '会员',
                    'amount5' => $order['pay_price'],
                    'time4' => date('Y-m-d H:i:s', time())
                ], $site_url . Url::build('wap/special/member_recharge'));
                WechatTemplateService::sendAdminNoticeTemplate(0, [
                    'character_string2' => $orderId,
                    'thing3' => $title . '会员',
                    'amount5' => $order['pay_price'],
                    'time4' => date('Y-m-d H:i:s', time())
                ]);
            }
        } catch (\Throwable $e) {
        }
        StoreOrderStatus::status($order['id'], 'pay_success', '用户付款成功');
        $res = $res1 && $resMer && $res2;
        return false !== $res;
    }

    /**
     * 计算普通裂变推广人返佣金额
     * @param int $is_promoter 推广人级别
     * @param float $money 返佣金额
     * @return float
     * */
    public static function getBrokerageMoney($is_promoter, $money)
    {
        $is_promoter = !is_int($is_promoter) ? (int)$is_promoter : $is_promoter;
        $systemName = 'store_brokerage_three_[###]x';
        //配置星级字段和设置如： store_brokerage_three_0x store_brokerage_three_1x
        //后台设置字段从零星开始 $is_promoter 应 -1 才能对应字段
        $store_brokerage_three = $is_promoter ? SystemConfigService::get(str_replace('[###]', $is_promoter - 1, $systemName)) : 100;
        //返佣比例为0则不返佣
        $store_brokerage_three = $store_brokerage_three == 0 ? 0 : bcdiv($store_brokerage_three, 100, 2);
        return bcmul($money, $store_brokerage_three, 2);
    }

    /**获取订单详情
     * @param $uid
     * @param $key
     * @return array|false|\PDOStatement|string|\think\Model
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function getUserOrderDetail($uid, $key)
    {
        return self::where('order_id|unique', $key)->where('uid', $uid)->where('is_del', 0)->find();
    }
}
