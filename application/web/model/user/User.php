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


namespace app\web\model\user;


use app\admin\model\wechat\WechatQrcode;
use app\wap\model\user\WechatUser;
use app\wap\model\wap\SmsTemplate;
use service\WechatTemplateService;
use basic\ModelBasic;
use service\SystemConfigService;
use think\Cookie;
use think\Request;
use think\response\Redirect;
use think\Session;
use think\Url;
use traits\ModelTrait;
use app\web\model\special\Special;
use app\web\model\user\MemberShip;

/**用户信息表
 * Class User
 * @package app\web\model\user
 */
class User extends ModelBasic
{
    use ModelTrait;

    protected $insert = ['add_time', 'add_ip', 'last_time', 'last_ip'];

    protected function setAddTimeAttr($value)
    {
        return time();
    }

    protected function setAddIpAttr($value)
    {
        return Request::instance()->ip();
    }

    protected function setLastTimeAttr($value)
    {
        return time();
    }

    protected function setLastIpAttr($value)
    {
        return Request::instance()->ip();
    }

    /**
     * 绑定用户手机号码修改手机号码用户购买的专题和其他数据
     * @param $bindingPhone 绑定手机号码
     * @param $uid 当前用户id
     * @param $newUid 切换用户id
     * @param bool $isDel 是否删除
     * @param int $qcodeId 扫码id
     * @return bool
     * @throws \think\exception\PDOException
     */
    public static function setUserRelationInfos($bindingPhone, $uid, $newUid, $isDel = true, $qcodeId = 0)
    {
        self::startTrans();
        try {
            //修改下级推广人关系
            self::where('spread_uid', $uid)->update(['spread_uid' => $newUid]);

            //修改用户金额变动记录表
            self::getDb('user_bill')->where('uid', $uid)->update(['uid' => $newUid]);

            //修改签到记录表
            self::getDb('user_sign')->where('uid', $uid)->update(['uid' => $newUid]);

            //修改虚拟币充值记录表
            self::getDb('user_recharge')->where('uid', $uid)->update(['uid' => $newUid]);

            //修改收货地址表
            self::getDb('user_address')->where('uid', $uid)->update(['uid' => $newUid]);

            //修改提现记录用户
            self::getDb('user_extract')->where('uid', $uid)->update(['uid' => $newUid]);

            //修改专题相关表
            self::getDb('special_buy')->where('uid', $uid)->update(['uid' => $newUid]);
            self::getDb('special_watch')->where('uid', $uid)->update(['uid' => $newUid]);
            self::getDb('learning_records')->where('uid', $uid)->update(['uid' => $newUid]);
            self::getDb('special_exchange')->where('use_uid', $uid)->update(['use_uid' => $newUid]);
            self::getDb('special_record')->where('uid', $uid)->update(['uid' => $newUid]);
            self::getDb('special_relation')->where('uid', $uid)->update(['uid' => $newUid]);
            self::getDb('special_reply')->where('uid', $uid)->update(['uid' => $newUid]);

            //修改直播嘉宾表
            self::getDb('live_user')->where('uid', $uid)->update(['uid' => $newUid]);
            self::getDb('live_barrage')->where('uid', $uid)->update(['uid' => $newUid]);
            self::getDb('live_honoured_guest')->where('uid', $uid)->update(['uid' => $newUid]);
            self::getDb('live_reward')->where('uid', $uid)->update(['uid' => $newUid]);

            //修改购物车记录表
            self::getDb('store_cart')->where('uid', $uid)->update(['uid' => $newUid]);
            self::getDb('store_product_reply')->where('uid', $uid)->update(['uid' => $newUid]);

            //修改用户订单记录
            self::getDb('store_order')->where('uid', $uid)->update(['uid' => $newUid]);

            //修改拼团用户记录
            self::getDb('store_pink')->where('uid', $uid)->update(['uid' => $newUid]);

            //修改手机用户表记录
            self::getDb('phone_user')->where('uid', $uid)->update(['uid' => $newUid]);

            //修改会员记录表记录
            self::getDb('member_record')->where('uid', $uid)->update(['uid' => $newUid]);

            //修改搜索记录表记录
            self::getDb('search_history')->where('uid', $uid)->update(['uid' => $newUid]);

            //修改用户报名表记录
            self::getDb('event_sign_up')->where('uid', $uid)->update(['uid' => $newUid]);
            self::getDb('event_write_off_user')->where('uid', $uid)->update(['uid' => $newUid]);

            //修改资料订单表记录
            self::getDb('data_download_buy')->where('uid', $uid)->update(['uid' => $newUid]);
            self::getDb('data_download_order')->where('uid', $uid)->update(['uid' => $newUid]);
            self::getDb('data_download_records')->where('uid', $uid)->update(['uid' => $newUid]);

            //修改证书记录表记录
            self::getDb('certificate_record')->where('uid', $uid)->update(['uid' => $newUid]);

            //修改考试相关表记录
            self::getDb('examination_record')->where('uid', $uid)->update(['uid' => $newUid]);
            self::getDb('examination_test_record')->where('uid', $uid)->update(['uid' => $newUid]);
            self::getDb('examination_wrong_bank')->where('uid', $uid)->update(['uid' => $newUid]);
            self::getDb('test_paper_obtain')->where('uid', $uid)->update(['uid' => $newUid]);
            self::getDb('test_paper_order')->where('uid', $uid)->update(['uid' => $newUid]);

            //修改讲师相关表
            self::getDb('merchant')->where('uid', $uid)->update(['uid' => $newUid]);
            self::getDb('merchant_admin')->where('uid', $uid)->update(['uid' => $newUid]);
            self::getDb('merchant_follow')->where('uid', $uid)->update(['uid' => $newUid]);
            self::getDb('user_enter')->where('uid', $uid)->update(['uid' => $newUid]);

            //修改学员相关表
            self::getDb('student')->where('uid', $uid)->update(['uid' => $newUid]);

            //修改老师相关表
            self::getDb('teacher')->where('uid', $uid)->update(['uid' => $newUid]);


            //删除用户表H5用户记录
            $user = self::where('uid', $uid)->find();
            if ($isDel) self::where('uid', $uid)->delete();
            //修改上级推广关系和绑定手机号码
            self::edit([
                'phone' => $bindingPhone,
                'spread_uid' => $user['spread_uid'],
                'spread_time' => $user['spread_time'],
                'valid_time' => $user['valid_time'],
                'is_promoter' => $user['is_promoter'],
                'pay_count' => $user['pay_count'],
                'is_senior' => $user['is_senior'],
                'now_money' => $user['now_money'],
                'gold_num' => $user['gold_num'],
                'brokerage_price' => $user['brokerage_price'],
                'is_permanent' => $user['is_permanent'],
                'member_time' => $user['member_time'],
                'overdue_time' => $user['overdue_time'],
                'level' => $user['level'],
                'identitys' => $user['identitys'],
                'is_write_off' => $user['is_write_off'],
                'business' => $user['business']
            ],$newUid,'uid');
            if ($qcodeId) WechatQrcode::where('id', $qcodeId)->update(['scan_id' => $newUid]);
            self::commit();
            Session::clear('web');
            Session::set('loginUid', $newUid, 'web');
            return true;
        } catch (\Exception $e) {
            self::rollback();
            return self::setErrorInfo($e->getMessage());
        }
    }

    public static function getUserInfo($uid)
    {
        $userInfo = self::where('uid', $uid)->find();
        if (!Session::has('__login_phone_num' . $uid) && $userInfo['phone']) {
            Cookie::set('__login_phone', 1);
            Session::set('__login_phone_num' . $uid, $userInfo['phone'], 'web');
        }
        unset($userInfo['pwd']);
        if (!$userInfo) {
            Session::delete(['loginUid']);
            throw new \Exception('未查询到此用户');
        }
        return $userInfo->toArray();
    }

    public static function getUserData($uid)
    {
        $userInfo = self::where('uid', $uid)->find();
        if (!$userInfo) return false;
        return $userInfo->toArray();
    }

    /**
     * 获得当前登陆用户UID
     * @return int $uid
     */
    public static function getActiveUid()
    {
        $uid = null;
        if (!Cookie::get('is_login')) exit(exception('请登陆!'));
        if (Session::has('loginUid', 'web')) $uid = Session::get('loginUid', 'web');
        if (!$uid) exit(exception('请登陆!'));
        return $uid;
    }

    /**
     * 获取登陆的手机号码
     * @param int $uid 用户id
     * @param string $phone 用户号码
     * @return string
     * */
    public static function getLogPhone($uid, $phone = null)
    {
        $name = '__login_phone_num' . $uid;
        if (!Cookie::get('__login_phone') && $uid) {
            Cookie::set('__login_phone', 1);
        } else if (!Cookie::get('__login_phone') && !$uid) {
            return null;
        }
        if (Session::has($name, 'web')) $phone = Session::get($name, 'web');
        if (is_null($phone)) {
            if (Session::has('__login_phone_number', 'web')) $phone = Session::get('__login_phone_number', 'web');
        }
        return $phone;
    }

    /**
     * 一级推广 专题
     * @param $orderInfo
     * @return bool
     */
    public static function backOrderBrokerage($orderInfo)
    {
        $userInfo = self::getUserData($orderInfo['uid']);
        if (!$userInfo || !$userInfo['spread_uid']) return true;
        $storeBrokerageStatu = SystemConfigService::get('store_brokerage_statu') ?: 1;//获取后台分销类型
        if ($storeBrokerageStatu == 1) {
            if (!self::be(['uid' => $userInfo['spread_uid'], 'is_promoter' => 1])) return true;
        }
        $data = Special::getIndividualDistributionSettings($orderInfo['cart_id']);
        if (isset($data['is_alone']) && $data['is_alone']) {
            if (!isset($data['brokerage_ratio']) || !$data['brokerage_ratio']) return true;
            $brokerageRatio = bcdiv($data['brokerage_ratio'], 100, 2);
        } else {
            $course_distribution_switch = SystemConfigService::get('course_distribution_switch');//课程分销开关
            if ($course_distribution_switch == 0) return true;
            $brokerageRatio = bcdiv(SystemConfigService::get('store_brokerage_ratio'), 100, 2);
        }
        if ($brokerageRatio <= 0) return true;
        $brokeragePrice = bcmul($orderInfo['pay_price'], $brokerageRatio, 2);
        if ($brokeragePrice <= 0) return true;
        $mark = '一级推广人' . $userInfo['nickname'] . '消费' . floatval($orderInfo['pay_price']) . '元购买专题,奖励推广佣金' . floatval($brokeragePrice);
        $bill = UserBill::billDoesDataExist($userInfo['spread_uid'], 'now_money', 'brokerage', $brokeragePrice, $orderInfo['id']);
        if ($bill) return true;
        self::beginTrans();
        $res1 = UserBill::income('购买专题返佣', $userInfo['spread_uid'], 'now_money', 'brokerage', $brokeragePrice, $orderInfo['id'], 0, $mark);
        $res2 = self::bcInc($userInfo['spread_uid'], 'brokerage_price', $brokeragePrice, 'uid');
        $User = self::getUserData($userInfo['spread_uid']);
        if (!$User) {
            self::checkTrans(false);
            return true;
        }
        $dats['brokerage_price'] = $brokeragePrice;
        SmsTemplate::sendSms($userInfo['spread_uid'], $dats, 'ORDER_BROKERAGE');
        $res = $res1 && $res2;
        self::checkTrans($res);
        if ($res) self::backOrderBrokerageTwo($orderInfo);
        return $res;
    }

    /**
     * 二级推广 专题
     * @param $orderInfo
     * @return bool
     */
    public static function backOrderBrokerageTwo($orderInfo)
    {
        $userInfo = self::getUserData($orderInfo['uid']);
        $userInfoTwo = self::getUserData($userInfo['spread_uid']);
        if (!$userInfoTwo || !$userInfoTwo['spread_uid']) return true;
        $storeBrokerageStatu = SystemConfigService::get('store_brokerage_statu') ?: 1;//获取后台分销类型
        if ($storeBrokerageStatu == 1) {
            if (!self::be(['uid' => $userInfoTwo['spread_uid'], 'is_promoter' => 1])) return true;
        }
        $data = Special::getIndividualDistributionSettings($orderInfo['cart_id']);
        if (isset($data['is_alone']) && $data['is_alone']) {
            if (!isset($data['brokerage_two']) || !$data['brokerage_two']) return true;
            $brokerageRatio = bcdiv($data['brokerage_two'], 100, 2);
        } else {
            $course_distribution_switch = SystemConfigService::get('course_distribution_switch');//课程分销开关
            if ($course_distribution_switch == 0) return true;
            $brokerageRatio = bcdiv(SystemConfigService::get('store_brokerage_two'), 100, 2);
        }
        if ($brokerageRatio <= 0) return true;
        $brokeragePrice = bcmul($orderInfo['pay_price'], $brokerageRatio, 2);
        if ($brokeragePrice <= 0) return true;
        $mark = '二级推广人' . $userInfo['nickname'] . '消费' . floatval($orderInfo['pay_price']) . '元购买专题,奖励推广佣金' . floatval($brokeragePrice);
        $bill = UserBill::billDoesDataExist($userInfoTwo['spread_uid'], 'now_money', 'brokerage', $brokeragePrice, $orderInfo['id']);
        if ($bill) return true;
        self::beginTrans();
        $res1 = UserBill::income('购买专题返佣', $userInfoTwo['spread_uid'], 'now_money', 'brokerage', $brokeragePrice, $orderInfo['id'], 0, $mark);
        $res2 = self::bcInc($userInfoTwo['spread_uid'], 'brokerage_price', $brokeragePrice, 'uid');
        $User = self::getUserData($userInfoTwo['spread_uid']);
        if (!$User) {
            self::checkTrans(false);
            return true;
        }
        $dats['brokerage_price'] = $brokeragePrice;
        SmsTemplate::sendSms($userInfoTwo['spread_uid'], $dats, 'ORDER_BROKERAGE');
        $res = $res1 && $res2;
        self::checkTrans($res);
        return $res;
    }

    /**
     * 一级推广 会员
     * @param $orderInfo
     * @return bool
     */
    public static function backOrderBrokerageMember($orderInfo)
    {
        $userInfo = self::getUserData($orderInfo['uid']);
        if (!$userInfo || !$userInfo['spread_uid']) return true;
        $storeBrokerageStatu = SystemConfigService::get('store_brokerage_statu') ?: 1;//获取后台分销类型
        if ($storeBrokerageStatu == 1) {
            if (!self::be(['uid' => $userInfo['spread_uid'], 'is_promoter' => 1])) return true;
        }
        $data = MemberShip::getIndividualDistributionSettings($orderInfo['member_id']);
        if (isset($data['is_alone']) && $data['is_alone']) {
            if (!isset($data['brokerage_ratio']) || !$data['brokerage_ratio']) return true;
            $brokerageRatio = bcdiv($data['brokerage_ratio'], 100, 2);
        } else {
            $member_distribution_switch = SystemConfigService::get('member_distribution_switch');//会员分销开关
            if ($member_distribution_switch == 0) return true;
            $brokerageRatio = bcdiv(SystemConfigService::get('member_brokerage_ratio'), 100, 2);
        }
        if ($brokerageRatio <= 0) return true;
        $brokeragePrice = bcmul($orderInfo['pay_price'], $brokerageRatio, 2);
        if ($brokeragePrice <= 0) return true;
        $mark = '一级推广人' . $userInfo['nickname'] . '消费' . floatval($orderInfo['pay_price']) . '元购买会员,奖励推广佣金' . floatval($brokeragePrice);
        $bill = UserBill::billDoesDataExist($userInfo['spread_uid'], 'now_money', 'brokerage', $brokeragePrice, $orderInfo['id']);
        if ($bill) return true;
        self::beginTrans();
        $res1 = UserBill::income('购买会员返佣', $userInfo['spread_uid'], 'now_money', 'brokerage', $brokeragePrice, $orderInfo['id'], 0, $mark);
        $res2 = self::bcInc($userInfo['spread_uid'], 'brokerage_price', $brokeragePrice, 'uid');
        $User = self::getUserData($userInfo['spread_uid']);
        if (!$User) {
            self::checkTrans(false);
            return true;
        }
        $dats['brokerage_price'] = $brokeragePrice;
        SmsTemplate::sendSms($userInfo['spread_uid'], $dats, 'ORDER_BROKERAGE');
        $res = $res1 && $res2;
        self::checkTrans($res);
        if ($res) self::backOrderBrokerageTwoMember($orderInfo);
        return $res;
    }

    /**
     * 二级推广 会员
     * @param $orderInfo
     * @return bool
     */
    public static function backOrderBrokerageTwoMember($orderInfo)
    {
        $userInfo = self::getUserData($orderInfo['uid']);
        $userInfoTwo = self::getUserData($userInfo['spread_uid']);
        if (!$userInfoTwo || !$userInfoTwo['spread_uid']) return true;
        $storeBrokerageStatu = SystemConfigService::get('store_brokerage_statu') ?: 1;//获取后台分销类型
        if ($storeBrokerageStatu == 1) {
            if (!self::be(['uid' => $userInfoTwo['spread_uid'], 'is_promoter' => 1])) return true;
        }
        $data = MemberShip::getIndividualDistributionSettings($orderInfo['member_id']);
        if (isset($data['is_alone']) && $data['is_alone']) {
            if (!isset($data['brokerage_two']) || !$data['brokerage_two']) return true;
            $brokerageRatio = bcdiv($data['brokerage_two'], 100, 2);
        } else {
            $member_distribution_switch = SystemConfigService::get('member_distribution_switch');//会员分销开关
            if ($member_distribution_switch == 0) return true;
            $brokerageRatio = bcdiv(SystemConfigService::get('member_brokerage_two'), 100, 2);
        }
        if ($brokerageRatio <= 0) return true;
        $brokeragePrice = bcmul($orderInfo['pay_price'], $brokerageRatio, 2);
        if ($brokeragePrice <= 0) return true;
        $mark = '二级推广人' . $userInfo['nickname'] . '消费' . floatval($orderInfo['pay_price']) . '元购买会员,奖励推广佣金' . floatval($brokeragePrice);
        $bill = UserBill::billDoesDataExist($userInfoTwo['spread_uid'], 'now_money', 'brokerage', $brokeragePrice, $orderInfo['id']);
        if ($bill) return true;
        self::beginTrans();
        $res1 = UserBill::income('购买会员返佣', $userInfoTwo['spread_uid'], 'now_money', 'brokerage', $brokeragePrice, $orderInfo['id'], 0, $mark);
        $res2 = self::bcInc($userInfoTwo['spread_uid'], 'brokerage_price', $brokeragePrice, 'uid');
        $User = self::getUserData($userInfoTwo['spread_uid']);
        if (!$User) {
            self::checkTrans(false);
            return true;
        }
        $dats['brokerage_price'] = $brokeragePrice;
        SmsTemplate::sendSms($userInfoTwo['spread_uid'], $dats, 'ORDER_BROKERAGE');
        $res = $res1 && $res2;
        self::checkTrans($res);
        return $res;
    }

}
