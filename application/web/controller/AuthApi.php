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

namespace app\web\controller;

use app\wap\model\user\SmsCode;
use app\web\model\user\User;
use app\web\model\user\UserBill;
use app\web\model\user\UserRecharge;
use app\web\model\activity\EventSignUp;
use app\web\model\special\Special as SpecialModel;
use app\web\model\special\StoreOrder;
use app\web\model\topic\TestPaper;
use app\web\model\topic\TestPaperOrder;
use app\web\model\special\Lecturer;
use app\web\model\article\Article;
use app\web\model\user\MemberShip;
use app\web\model\user\MemberCard;
use service\FastknifeService;
use service\sms\storage\Sms;
use app\web\model\material\DataDownload;
use app\web\model\material\DataDownloadBuy;
use app\web\model\material\DataDownloadOrder;
use service\AliMessageService;
use service\CacheService;
use service\HookService;
use service\JsonService;
use service\SystemConfigService;
use service\GroupDataService;
use service\UtilService;
use think\Cache;
use think\Config;
use think\Db;
use think\Request;
use think\Session;
use think\Url;

/**接口
 * Class AuthApi
 * @package app\web\controller
 */
class AuthApi extends AuthController
{

    public static function WhiteList()
    {
        return [
            'code',
            'ajcaptcha',
            'ajcheck',
            'merber_data',
            'suspensionButton',
            'getVersion',
            'get_course_ranking',
            'get_new_course_first',
            'get_article_unifiend_list',
            'get_good_class_recommend',
            'lecturer_special_list',
            'getLecturer',
            'lecturer_details',
            'captcha',
            'del_redis_phone'
        ];
    }

    public function upload()
    {
        $aliyunOss = \Api\AliyunOss::instance([
            'AccessKey' => SystemConfigService::get('accessKeyId'),
            'AccessKeySecret' => SystemConfigService::get('accessKeySecret'),
            'OssEndpoint' => SystemConfigService::get('end_point'),
            'OssBucket' => SystemConfigService::get('OssBucket'),
            'uploadUrl' => SystemConfigService::get('uploadUrl'),
        ]);
        $res = $aliyunOss->upload('file');
        if ($res && isset($res['url'])) {
            return JsonService::successful('上传成功', ['url' => $res['url']]);
        } else {
            return JsonService::fail('上传失败');
        }
    }

    /**
     * @return mixed
     */
    public function ajcaptcha()
    {
        $captchaType = $this->request->get('captchaType', 'blockPuzzle');
        return JsonService::successful('ok', FastknifeService::aj_captcha_create($captchaType));
    }

    /**
     * 一次验证
     * @return mixed
     */
    public function ajcheck()
    {
        $data = UtilService::postMore([
            ['token', ''],
            ['pointJson', ''],
            ['captchaType', '']
        ]);
        try {
            FastknifeService::aj_captcha_check_one($data['captchaType'], $data['token'], $data['pointJson']);
            return JsonService::successful('滑块验证成功');
        } catch (\Throwable $e) {
            return JsonService::fail('滑块验证失败');
        }
    }

    /**
     * 发送短信验证码
     * @param string $phone
     */
    public function code()
    {
        $data = UtilService::postMore([
            ['phone', ''],
            ['captchaType', ''],
            ['captchaVerification', '']
        ]);
        $phone = $data['phone'];
        $name = "is_phone_code" . $phone;
        if ($phone == '') return JsonService::fail('请输入手机号码!');
        if (!$data['captchaType'] || !$data['captchaVerification']) return JsonService::fail('滑块验证参数错误！');
        try {
            FastknifeService::aj_captcha_check_two($data['captchaType'], $data['captchaVerification']);
        } catch (\Throwable $e) {
            return JsonService::fail('滑块验证失败');
        }
        $time = Session::get($name, 'web');
        if ($time < time() + 60) Session::delete($name, 'web');
        if (Session::has($name, 'web') && $time < time()) return JsonService::fail('您发送验证码的频率过高,请稍后再试!');
        $code = AliMessageService::getVerificationCode();
        SmsCode::set(['tel' => $phone, 'code' => md5('is_phone_code' . $code), 'last_time' => time() + 300, 'uid' => $this->uid]);
        Session::set($name, time() + 60, 'web');
        $smsHandle = new Sms();
        $sms_platform_selection = SystemConfigService::get('sms_platform_selection');
        $smsSignName = SystemConfigService::get('smsSignName');//短信签名
        $smsTemplateCode = SystemConfigService::get('smsTemplateCode');//短信模板ID
        if ($sms_platform_selection == 1) {
            if (!$smsSignName || !$smsTemplateCode) return JsonService::fail('系统后台短信没有配置，请稍后在试!');
            $res = AliMessageService::sendmsg($phone, $smsTemplateCode, ['code' => $code]);
        } else {
            if (!(int)$smsTemplateCode) return JsonService::fail('请正确的填写系统后台短信配置!');
            $res = $smsHandle->send($phone, $smsTemplateCode, ['code' => $code]);
        }
        if ($res['Code'] == 'OK') {
            return JsonService::successful('发送成功', $res);
        } else {
            return JsonService::fail($res['Message']);
        }
    }

    /**
     * 用户信息
     */
    public function user_info()
    {
        $user = $this->userInfo;
        if ($user['level'] > 0) {
            $user['overdue_time'] = date('Y-m-d', $user['overdue_time']);
        }
        return JsonService::successful($user);
    }

    /**支付接口
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function create_order()
    {
        list($special_id, $pay_type_num, $payType, $price_id, $event) = UtilService::PostMore([
            ['special_id', 0],
            ['pay_type_num', -1],
            ['payType', 'weixin'],
            ['price_id', 0],
            ['event', []]
        ], $this->request, true);
        switch ($pay_type_num) {
            case 10: //会员支付
                $this->create_member_order($special_id, $payType);
                break;
            case 20://报名支付
                $this->create_activity_order($special_id, $payType, $price_id, $event);
                break;
            case 30: //虚拟币充值
                $this->user_wechat_recharge($special_id, $payType);
                break;
            case 60: //试卷购买
                $this->create_test_paper_order($special_id, $payType);
                break;
            case 70: //资料购买
                $this->create_data_download_order($special_id, $payType);
                break;
            default: //专题支付
                $this->create_special_order($special_id, $pay_type_num, $payType);
        }
    }

    /**会员订单创建
     * @param $id
     * @param $payType
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function create_member_order($id, $payType)
    {
        if (!$id) return JsonService::fail('参数错误!');
        $order = StoreOrder::cacheMemberCreateOrder($this->uid, $id, $payType);
        $orderId = $order['order_id'];
        $info = compact('orderId');
        if ($orderId) {
            $orderInfo = StoreOrder::where('order_id', $orderId)->find();
            if (!$orderInfo || !isset($orderInfo['paid'])) exception('支付订单不存在!');
            if ($orderInfo['paid']) exception('支付已支付!');
            if (bcsub((float)$orderInfo['pay_price'], 0, 2) <= 0) {
                if (StoreOrder::jsPayMePrice($orderId, $this->uid))
                    return JsonService::status('success', '领取成功', $info);
                else
                    return JsonService::status('pay_error', StoreOrder::getErrorInfo());
            } else {
                switch ($payType) {
                    case 'weixin':
                        try {
                            $jsConfig = StoreOrder::nativePayMember($orderId);
                        } catch (\Exception $e) {
                            return JsonService::status('pay_error', $e->getMessage(), $info);
                        }
                        $info['jsConfig'] = $jsConfig;
                        return JsonService::status('wechat_pay', '订单创建成功', $info);
                        break;
                    case 'zhifubao':
                        try {
                            $jsConfig = StoreOrder::alipayAativePayMember($orderId);
                        } catch (\Exception $e) {
                            return JsonService::status('pay_error', $e->getMessage(), $info);
                        }
                        $info['jsConfig'] = $jsConfig;
                        return JsonService::status('zhifubao_pay', '订单创建成功', $info);
                        break;
                }
            }
        } else {
            return JsonService::fail(StoreOrder::getErrorInfo('领取失败!'));
        }
    }

    /**
     * 用户提交报名
     */
    public function create_activity_order($id, $payType, $price_id, $event)
    {
        if (!$id || !$price_id) JsonService::fail('参数有误');
        $order = EventSignUp::userEventSignUp($id, $price_id, json_encode($event), $payType, $this->uid);
        $orderId = $order['order_id'];
        $info = compact('orderId');
        if ($orderId) {
            $orderInfo = EventSignUp::where('order_id', $orderId)->find();
            if (!$orderInfo || !isset($orderInfo['paid'])) return JsonService::status('pay_error', '支付订单不存在!');
            if ($orderInfo['paid']) return JsonService::status('pay_error', '支付已支付!');
            if (bcsub((float)$orderInfo['pay_price'], 0, 2) <= 0) {
                if (EventSignUp::jsPayPrice($orderId, $this->uid))
                    return JsonService::status('success', '支付成功', $info);
                else
                    return JsonService::status('pay_error', EventSignUp::getErrorInfo());
            } else {
                switch ($payType) {
                    case 'weixin':
                        try {
                            $jsConfig = EventSignUp::nativePay($orderId);
                        } catch (\Exception $e) {
                            return JsonService::status('pay_error', $e->getMessage(), $info);
                        }
                        $info['jsConfig'] = $jsConfig;
                        return JsonService::status('wechat_pay', '订单创建成功', $info);
                        break;
                    case 'yue':
                        if (EventSignUp::yuePay($orderId, $this->uid))
                            return JsonService::status('success', '余额支付成功', $info);
                        else
                            return JsonService::status('pay_error', EventSignUp::getErrorInfo());
                        break;
                    case 'zhifubao':
                        try {
                            $jsConfig = EventSignUp::alipayAativePay($orderId);
                        } catch (\Exception $e) {
                            return JsonService::status('pay_error', $e->getMessage(), $info);
                        }
                        $info['jsConfig'] = $jsConfig;
                        return JsonService::status('zhifubao_pay', '订单创建成功', $info);
                        break;
                }
            }
        } else {
            return JsonService::fail(EventSignUp::getErrorInfo('订单生成失败!'));
        }
    }

    /**创建试卷支付订单
     * @param $test_id
     * @param $payType
     */
    public function create_data_download_order($data_id, $payType)
    {
        $data = DataDownload::PreWhere()->find($data_id);
        if (!$data) return JsonService::status('ORDER_ERROR', '购买的资料不存在');
        $order = DataDownloadOrder::createDataDownloadOrder($data, $this->uid, $payType, 1);
        $orderId = $order['order_id'];
        $info = compact('orderId');
        if ($orderId) {
            $orderInfo = DataDownloadOrder::where('order_id', $orderId)->where('is_del', 0)->find();
            if (!$orderInfo || !isset($orderInfo['paid'])) return JsonService::status('pay_error', '支付订单不存在!');
            if ($orderInfo['paid']) return JsonService::status('pay_error', '支付已支付!');
            if (bcsub((float)$orderInfo['pay_price'], 0, 2) <= 0) {
                if (DataDownloadOrder::jsPayDataDownloadPrice($orderId, $this->uid))
                    return JsonService::status('success', '支付成功', $info);
                else
                    return JsonService::status('pay_error', DataDownloadOrder::getErrorInfo());
            } else {
                switch ($payType) {
                    case 'weixin':
                        try {
                            $jsConfig = DataDownloadOrder::nativeDataDownloadPay($orderId);
                        } catch (\Exception $e) {
                            return JsonService::status('pay_error', $e->getMessage(), $info);
                        }
                        $info['jsConfig'] = $jsConfig;
                        return JsonService::status('wechat_pay', '订单创建成功', $info);
                        break;
                    case 'yue':
                        if (DataDownloadOrder::yueDataDownloadPay($orderId, $this->uid))
                            return JsonService::status('success', '余额支付成功', $info);
                        else
                            return JsonService::status('pay_error', DataDownloadOrder::getErrorInfo());
                        break;
                    case 'zhifubao':
                        try {
                            $jsConfig = DataDownloadOrder::alipayAativeDataDownloadPay($orderId);
                        } catch (\Exception $e) {
                            return JsonService::status('pay_error', $e->getMessage(), $info);
                        }
                        $info['jsConfig'] = $jsConfig;
                        return JsonService::status('zhifubao_pay', '订单创建成功', $info);
                        break;
                }
            }
        } else {
            return JsonService::fail(DataDownloadOrder::getErrorInfo('订单生成失败!'));
        }
    }

    /**创建试卷支付订单
     * @param $test_id
     * @param $payType
     */
    public function create_test_paper_order($test_id, $payType)
    {
        $testPaper = TestPaper::PreExercisesWhere()->find($test_id);
        if (!$testPaper) return JsonService::status('ORDER_ERROR', '购买的试卷不存在');
        $order = TestPaperOrder::createTestPaperOrder($testPaper, $this->uid, $payType, 1);
        $orderId = $order['order_id'];
        $info = compact('orderId');
        if ($orderId) {
            $orderInfo = TestPaperOrder::where('order_id', $orderId)->where('is_del', 0)->find();
            if (!$orderInfo || !isset($orderInfo['paid'])) return JsonService::status('pay_error', '支付订单不存在!');
            if ($orderInfo['paid']) return JsonService::status('pay_error', '支付已支付!');
            if (bcsub((float)$orderInfo['pay_price'], 0, 2) <= 0) {
                if (TestPaperOrder::jsPayTestPaperPrice($orderId, $this->uid))
                    return JsonService::status('success', '支付成功', $info);
                else
                    return JsonService::status('pay_error', TestPaperOrder::getErrorInfo());
            } else {
                switch ($payType) {
                    case 'weixin':
                        try {
                            $jsConfig = TestPaperOrder::nativeTestPaperPay($orderId);
                        } catch (\Exception $e) {
                            return JsonService::status('pay_error', $e->getMessage(), $info);
                        }
                        $info['jsConfig'] = $jsConfig;
                        return JsonService::status('wechat_pay', '订单创建成功', $info);
                        break;
                    case 'yue':
                        if (TestPaperOrder::yueTestPaperPay($orderId, $this->uid))
                            return JsonService::status('success', '余额支付成功', $info);
                        else
                            return JsonService::status('pay_error', TestPaperOrder::getErrorInfo());
                        break;
                    case 'zhifubao':
                        try {
                            $jsConfig = TestPaperOrder::alipayAativeTestPaperPay($orderId);
                        } catch (\Exception $e) {
                            return JsonService::status('pay_error', $e->getMessage(), $info);
                        }
                        $info['jsConfig'] = $jsConfig;
                        return JsonService::status('zhifubao_pay', '订单创建成功', $info);
                        break;
                }
            }
        } else {
            return JsonService::fail(TestPaperOrder::getErrorInfo('订单生成失败!'));
        }
    }


    /**虚拟币充值
     * @param int $price
     * @param int $payType
     */
    public function user_wechat_recharge($price = 0, $payType = 0)
    {
        if (!$price || $price <= 0 || !is_numeric($price)) return JsonService::fail('参数错误');
        if (!isset($this->uid) || !$this->uid) return JsonService::fail('用户不存在');
        try {
            //充值记录
            $order = UserRecharge::addRecharge($this->uid, $price, $payType);
            if (!$order) return JsonService::fail('充值订单生成失败!');
            $orderId = $order['order_id'];
            $info = compact('orderId');
            if ($orderId) {
                $orderInfo = UserRecharge::where('order_id', $orderId)->find();
                if (!$orderInfo || !isset($orderInfo['paid'])) return JsonService::status('pay_error', '支付订单不存在!');
                switch ($payType) {
                    case 'weixin':
                        try {
                            $jsConfig = UserRecharge::nativeRechargePay($orderId);
                        } catch (\Exception $e) {
                            return JsonService::status('pay_error', $e->getMessage(), $info);
                        }
                        $info['jsConfig'] = $jsConfig;
                        return JsonService::status('wechat_pay', '订单创建成功', $info);
                        break;
                    case 'yue':
                        if (UserRecharge::yuePay($orderId, $this->userInfo))
                            return JsonService::status('success', '余额支付成功', $info);
                        else
                            return JsonService::status('pay_error', UserRecharge::getErrorInfo());
                        break;
                    case 'zhifubao':
                        try {
                            $jsConfig = UserRecharge::alipayAativeRechargePay($orderId);
                        } catch (\Exception $e) {
                            return JsonService::status('pay_error', $e->getMessage(), $info);
                        }
                        $info['jsConfig'] = $jsConfig;
                        return JsonService::status('zhifubao_pay', '订单创建成功', $info);
                        break;
                }
            } else {
                return JsonService::fail(UserRecharge::getErrorInfo('订单生成失败!'));
            }
        } catch (\Exception $e) {
            return JsonService::fail($e->getMessage());
        }

    }

    /**
     * 创建专题支付订单
     * @param int $special_id 专题id
     * @param int $pay_type 购买类型 1=礼物,2=普通购买,3=开团或者拼团
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function create_special_order($special_id, $pay_type_num, $payType)
    {
        if (!$special_id) return JsonService::fail('缺少购买参数');
        if ($pay_type_num == -1) return JsonService::fail('选择购买方式');
        $special = SpecialModel::PreWhere()->find($special_id);
        if (!$special) return JsonService::status('ORDER_ERROR', '购买的专题不存在');
        $order = StoreOrder::createSpecialOrder($special, $pay_type_num, $this->uid, $payType);
        $orderId = $order['order_id'];
        $info = compact('orderId');
        if ($orderId) {
            $orderInfo = StoreOrder::where('order_id', $orderId)->find();
            if (!$orderInfo || !isset($orderInfo['paid'])) return JsonService::status('pay_error', '支付订单不存在!');
            if ($orderInfo['paid']) return JsonService::status('pay_error', '支付已支付!');
            if (bcsub((float)$orderInfo['pay_price'], 0, 2) <= 0) {
                if (StoreOrder::jsPayPrice($orderId, $this->uid))
                    return JsonService::status('success', '支付成功', $info);
                else
                    return JsonService::status('pay_error', StoreOrder::getErrorInfo());
            } else {
                switch ($payType) {
                    case 'weixin':
                        try {
                            $jsConfig = StoreOrder::nativeSpecialPay($orderId);
                        } catch (\Exception $e) {
                            return JsonService::status('pay_error', $e->getMessage(), $info);
                        }
                        $info['jsConfig'] = $jsConfig;
                        return JsonService::status('wechat_pay', '订单创建成功', $info);
                        break;
                    case 'yue':
                        if (StoreOrder::yuePay($orderId, $this->uid))
                            return JsonService::status('success', '余额支付成功', $info);
                        else
                            return JsonService::status('pay_error', StoreOrder::getErrorInfo());
                        break;
                    case 'zhifubao':
                        try {
                            $jsConfig = StoreOrder::alipayAativeSpecialPay($orderId);
                        } catch (\Exception $e) {
                            return JsonService::status('pay_error', $e->getMessage(), $info);
                        }
                        $info['jsConfig'] = $jsConfig;
                        return JsonService::status('zhifubao_pay', '订单创建成功', $info);
                        break;
                }
            }
        } else {
            return JsonService::fail(StoreOrder::getErrorInfo('订单生成失败!'));
        }
    }

    /**
     * @param string $order_id
     * @param int $type 0=专题 1=资料 2=金币 3=会员 4=报名 5=考试
     */
    public function testing_order_state($order_id = '', $type = 0)
    {
        switch ($type) {
            case 0:
                $paid = StoreOrder::where(['order_id' => $order_id, 'type' => 0])->value('paid');
                break;
            case 1:
                $paid = DataDownloadOrder::where('order_id', $order_id)->value('paid');
                break;
            case 2:
                $paid = UserRecharge::where('order_id', $order_id)->value('paid');
                break;
            case 3:
                $paid = StoreOrder::where(['order_id' => $order_id, 'type' => 1])->value('paid');
                break;
            case 4:
                $paid = EventSignUp::where('order_id', $order_id)->value('paid');
                break;
            case 5:
                $paid = TestPaperOrder::where('order_id', $order_id)->value('paid');
                break;
        }
        return JsonService::successful($paid);
    }


    /**
     * 会员页数据
     */
    public function merber_data()
    {
        $interests = GroupDataService::getData('membership_interests', 3) ?: [];
        $description = GroupDataService::getData('member_description') ?: [];
        $data['interests'] = $interests;
        $data['description'] = $description;
        return JsonService::successful($data);
    }

    /**
     * 会员设置列表
     */
    public function member_ship_lists()
    {
        $meList = MemberShip::get_member_ship_list($this->uid);
        return JsonService::successful($meList);
    }

    /**
     * 会员卡激活
     */
    public function confirm_activation()
    {
        $request = Request::instance();
        if (!$request->isPost()) return JsonService::fail('参数错误!');
        $data = UtilService::postMore([
            ['member_code', ''],
            ['member_pwd', ''],
        ], $request);
        $res = MemberCard::confirmActivation($data, $this->userInfo);
        if ($res)
            return JsonService::successful('激活成功');
        else
            return JsonService::fail(MemberCard::getErrorInfo('激活失败!'));
    }

    /**
     * 余额信息
     */
    public function get_user_balance()
    {
        $user_info = $this->userInfo;
        $data['balance'] = $user_info['now_money'];
        $data['recharge'] = UserBill::getUserbalance($user_info['uid'], 1);
        $data['consumption'] = UserBill::getUserbalance($user_info['uid'], 0);
        return JsonService::successful($data);
    }

    /**余额where处理
     * @param $index
     * @return UserBill
     */
    public function set_where_now_money_model($index)
    {
        $model = UserBill::where('uid', $this->uid)->where('category', 'now_money')->where('number', '<>', 0)
            ->where('type', 'not in', 'gain,deduction,brokerage,extract,extract_fail,brokerage_return,sign,pay_vip,extract_success');
        switch ($index) {
            case 1:
                $model = $model->where('pm', 0);
                break;
            case 2:
                $model = $model->where('pm', 1);
                break;
        }
        return $model;
    }

    /**余额明细
     * @param int $index
     * @param int $first
     * @param int $limit
     */
    public function get_user_balance_list($page, $limit, $index)
    {
        if (!$limit) return [];
        $model = $this->set_where_now_money_model($index)->order('add_time desc')
            ->field('FROM_UNIXTIME(add_time,"%Y-%m-%d %H:%i") as add_time,title,number,pm');
        if ($page) $model = $model->page((int)$page, (int)$limit);
        $list = ($list = $model->select()) ? $list->toArray() : [];
        $data = [];
        $count = $this->set_where_now_money_model($index)->count();
        $data['list'] = $list;
        $data['count'] = $count;
        return JsonService::successful($data);
    }


    /**
     * 获取金币信息
     */
    public function get_gold_coins()
    {
        $user_info = $this->userInfo;
        $gold_info = SystemConfigService::more("gold_name,gold_rate,gold_image");
        $recharge_price_list = [60, 100, 300, 500, 980, 1980, 2980, 5180, 15980];
        $gold_name = SystemConfigService::get('gold_name');//虚拟币名称
        $data['gold_info'] = $gold_info;
        $data['gold_name'] = $gold_name;
        $data['recharge_price_list'] = $recharge_price_list;
        $data['user_gold_num'] = bcsub($user_info['gold_num'], 0, 2);
        $data['recharge'] = UserBill::getUserGoldCoins($user_info['uid'], 'recharge', 1, 1);
        $data['consumption'] = UserBill::getUserGoldConsumption($user_info['uid']);
        return JsonService::successful($data);
    }

    /**金币where条件处理
     * @param $index
     * @return UserBill
     */
    public function set_where_gold_num_model($index)
    {
        $model = UserBill::where('uid', $this->uid)->where('category', 'gold_num')->where('number', '<>', 0);
        switch ($index) {
            case 1:
                $model = $model->where('pm', 0);
                break;
            case 2:
                $model = $model->where('pm', 1);
                break;
        }
        return $model;
    }

    /**金币明细
     * @param int $index
     * @param int $first
     * @param int $limit
     */
    public function user_gold_num_list($index = 0, $page = 0, $limit = 8)
    {
        if (!$limit) return [];
        $model = $this->set_where_gold_num_model($index)->order('add_time desc')
            ->field('FROM_UNIXTIME(add_time,"%Y-%m-%d %H:%i") as add_time,title,number,pm');
        if ($page) $model = $model->page((int)$page, (int)$limit);
        $list = ($list = $model->select()) ? $list->toArray() : [];
        $data = [];
        $count = $this->set_where_gold_num_model($index)->count();
        $data['list'] = $list;
        $data['count'] = $count;
        return JsonService::successful($data);
    }

    /**
     * 获取我购买的课程
     * @param int $active 收藏类型 1=资料 0=课程
     * @param int $page 分页
     * @param int $limit 一页显示多少条
     * @return json
     * */
    public function get_grade_list()
    {
        list($page, $limit, $active) = UtilService::GetMore([
            ['page', 1],
            ['limit', 10],
            ['active', 0]
        ], $this->request, true);
        $is_member = isset($this->userInfo['level']) ? $this->userInfo['level'] : 0;
        return JsonService::successful(SpecialModel::getGradeList((int)$page, (int)$limit, $this->uid, $is_member, $active));
    }

    /**我的课程
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function my_special_list()
    {
        list($page, $limit) = UtilService::GetMore([
            ['page', 1],
            ['limit', 10]
        ], $this->request, true);
        return JsonService::successful(SpecialModel::getMySpecialList((int)$page, (int)$limit, $this->uid));
    }

    /**
     * 我的资料
     */
    public function my_material_list()
    {
        list($page, $limit) = UtilService::PostMore([
            ['page', 1],
            ['limit', 10]
        ], $this->request, true);
        return JsonService::successful(DataDownloadBuy::getUserDataDownload($this->uid, $page, $limit));
    }

    /**
     * 首页课程排行
     */
    public function get_course_ranking()
    {
        $is_member = isset($this->userInfo['level']) ? $this->userInfo['level'] : 0;
        return JsonService::successful(SpecialModel::course_ranking_list($is_member, 'browse_count', 3));
    }

    /**
     * 首页好课推荐
     */
    public function get_good_class_recommend()
    {
        $is_member = isset($this->userInfo['level']) ? $this->userInfo['level'] : 0;
        return JsonService::successful(SpecialModel::good_class_recommend_list($is_member));
    }

    /**
     * 首页新课首推
     */
    public function get_new_course_first()
    {
        $is_member = isset($this->userInfo['level']) ? $this->userInfo['level'] : 0;
        return JsonService::successful(SpecialModel::course_ranking_list($is_member, 'add_time', 2));
    }


    /**详情页讲师信息
     * @param $mer_id
     */
    public function getLecturer($mer_id)
    {
        if (!$mer_id) return JsonService::successful(null);
        $lecturer = Lecturer::information($mer_id);
        return JsonService::successful($lecturer);
    }

    /**
     * 获取讲师详情
     * @param $id
     */
    public function lecturer_details($id)
    {
        if (!$id) return JsonService::successful(null);
        $lecturer = Lecturer::details($id);
        return JsonService::successful($lecturer);
    }

    /**
     * 讲师名下课程
     * @param int $lecturer_id
     */
    public function lecturer_special_list()
    {
        list($lecturer_id, $page, $limit) = UtilService::PostMore([
            ['lecturer_id', 0],
            ['page', 1],
            ['limit', 10]
        ], $this->request, true);
        return JsonService::successful(SpecialModel::getLecturerSpecialList($lecturer_id, $page, $limit));
    }


    /**
     * 首页新闻推荐
     * $type 1=推荐 2=最新资讯
     */
    public function get_article_unifiend_list()
    {
        $where = UtilService::getMore([
            ['limit', 2],
            ['type', 0]
        ]);
        return JsonService::successful(Article::get_article_list($where));
    }

}
