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


namespace app\wap\controller;

use app\wap\model\activity\EventRegistration;
use app\wap\model\user\User;
use app\wap\model\user\WechatUser;
use basic\WapBasic;
use service\JsonService;
use service\SystemConfigService;
use service\UtilService;
use think\Cache;
use think\cache\driver\Redis;
use think\Cookie;
use think\Session;
use think\Url;
use app\wap\model\user\MemberShip;
use service\GroupDataService;
use think\Request;

class AuthController extends WapBasic
{
    /**
     * 用户ID
     * @var int
     */
    protected $uid = 0;
    /**
     * 用户信息
     * @var
     */
    protected $userInfo;

    protected $phone;

    protected $force_binding;

    protected $isWechat = false;

    protected $redisModel;

    protected $subjectUrl = '';

    protected function _initialize()
    {
        parent::_initialize();
        $pc_on_display = SystemConfigService::get('pc_on_display');
        if (!request()->isMobile() && is_dir(APP_PATH . 'web') && $pc_on_display) {
            $path = $this->getJumpPath();
            return $this->redirect($path);
        }
        try {
            $this->redisModel = new Redis();
        } catch (\Exception $e) {
            parent::serRedisPwd($e->getMessage());
        }
        $this->isWechat = UtilService::isWechatBrowser();
        $spread_uid = Request::instance()->param('spread_uid', 0);
        $NoWechantVisitWhite = $this->NoWechantVisitWhite();
        $subscribe = false;
        $site_url = SystemConfigService::get('site_url');
        $this->subjectUrl = getUrlToDomain();
        EventRegistration::updateActivityCount();
        try {
            $uid = User::getActiveUid();
            if (!empty($uid)) {
                $this->userInfo = User::getUserInfo($uid);
                MemberShip::memberExpiration($uid);
                if ($spread_uid) $spreadUserInfo = User::getUserData($spread_uid);
                $this->uid = $this->userInfo['uid'];
                $this->phone = User::getLogPhone($uid);
                //绑定推广人
                if ($spread_uid && $spreadUserInfo && $this->uid != $spread_uid && $spreadUserInfo['spread_uid'] != $this->uid && $this->userInfo['spread_uid'] != $spread_uid && !$this->userInfo['spread_uid']) {
                    $storeBrokerageStatu = SystemConfigService::get('store_brokerage_statu') ?: 1;//获取后台分销类型
                    if ($storeBrokerageStatu == 1) {
                        if ($spreadUserInfo['is_promoter']) User::edit(['spread_uid' => $spread_uid], $this->uid, 'uid');
                    } else {
                        User::edit(['spread_uid' => $spread_uid], $this->uid, 'uid');
                    }
                }
                if (!isset($this->userInfo['uid'])) $this->userInfo['uid'] = 0;
                if (!isset($this->userInfo['is_promoter'])) $this->userInfo['is_promoter'] = 0;
                if (!isset($this->userInfo['avatar'])) $this->userInfo['avatar'] = '';
                if (!isset($this->userInfo['nickname'])) $this->userInfo['nickname'] = '';
                //是否关注公众号
                $subscribe = WechatUser::where('uid', $this->uid)->value('subscribe');
                if (!$NoWechantVisitWhite) {
                    if (!$this->userInfo || !isset($this->uid)) $this->failed('读取用户信息失败!');
                    if (!$this->userInfo['status']) $this->failed('已被禁止登陆!');
                }
            }
        } catch (\Exception $e) {
            Session::clear('wap');
            Cookie::delete('is_login');
            Cookie::delete('__login_phone');
            $url = $this->request->url(true);
            if (!$NoWechantVisitWhite) {
                if ($this->request->isAjax()) {
                    return JsonService::fail('请登录再进行访问');
                } else {
                    return $this->redirect(Url::build('Login/index', ['spread_uid' => $spread_uid]) . '?ref=' . base64_encode(htmlspecialchars($url)));
                }
            }
        }
        if (Cache::has('__SYSTEM__')) {
            $overallShareWechat = Cache::get('__SYSTEM__');
        } else {
            $overallShareWechat = SystemConfigService::more(['wechat_share_img', 'wechat_share_title', 'wechat_share_synopsis']);
            Cache::set('__SYSTEM__', $overallShareWechat, 800);
        }

        $codeUrl = SystemConfigService::get('wechat_qrcode');
        $balance_switch = SystemConfigService::get('balance_switch');//余额开关
        $alipay_switch = SystemConfigService::get('alipay_switch');//支付宝开关
        $h5_wechat_payment_switch = SystemConfigService::get('h5_wechat_payment_switch');//h5端微信支付开关
        $official_account_switch = SystemConfigService::get('official_account_switch');//关注公众号开关
        $this->force_binding = SystemConfigService::get('force_binding');//微信端是否强制绑定手机号
        $share_display_switch = SystemConfigService::get('share_display_switch');//分享显示开关
        $now_money = isset($this->userInfo['now_money']) ? $this->userInfo['now_money'] : 0;
        $this->assign([
            'callback_url' => $site_url . '/wap/callback/pay_success_synchro',
            'code_url' => $codeUrl,
            'is_yue' => $balance_switch,
            'is_alipay' => $alipay_switch,
            'is_h5_wechat_payment_switch' => $h5_wechat_payment_switch,
            'is_official_account_switch' => $official_account_switch,
            'is_share_display_switch' => $share_display_switch,
            'subscribe' => $subscribe,
            'subscribeQrcode' => SystemConfigService::get('wechat_qrcode'),
            'userInfo' => $this->userInfo,
            'uid' => isset($this->userInfo['uid']) ? $this->userInfo['uid'] : 0,
            'business' => isset($this->userInfo['business']) ? $this->userInfo['business'] : 0,//是否是讲师
            'now_money' => $now_money,
            'phone' => $this->phone,
            'isWechat' => $this->isWechat,
            'overallShareWechat' => json_encode($overallShareWechat),
            'Auth_site_name' => SystemConfigService::get('site_name'),
            'menus' => GroupDataService::getData('bottom_navigation')
        ]);
    }

    /**
     * 检查白名单控制器方法 存在带名单返回 true 不存在则进行登录
     * @return bool
     */
    protected function NoWechantVisitWhite()
    {
        list($module, $controller, $action, $className) = $this->getCurrentController();
        if (method_exists($className, 'WhiteList')) {
            $whitelist = $className::WhiteList();
            if (!is_array($whitelist)) return false;
            foreach ($whitelist as $item) {
                if (strtolower($module . '\\' . $controller . '\\' . $item) == strtolower($module . '\\' . $controller . '\\' . $action)) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * 获取当前的控制器名,模块名,方法名,类名并返回
     * @return array
     */
    protected function getCurrentController()
    {
        $module = $this->request->module();
        $controller = $this->request->controller();
        $action = $this->request->action();
        if (strstr($controller, '.'))
            $controllerv1 = str_replace('.', '\\', $controller);
        else
            $controllerv1 = $controller;
        $className = 'app\\' . $module . '\\controller\\' . $controllerv1;
        return [$module, $controller, $action, $className];
    }

    /**移动端页面链接转pc端页面链接
     * @return array|string|string[]
     */
    protected function getJumpPath()
    {
        $arr = explode('/', $this->request->path());
        if (count($arr) > 3) {
            $pathUrl = $arr[0] . '/' . $arr[1] . '/' . $arr[2];
        } else {
            $pathUrl = $this->request->path();
        }
        switch ($pathUrl) {
            case 'wap/special/special_cate':
            case 'wap/index/unified_list':
            case 'wap/index/search':
                $path = '/web/special/special_cate';
                break;
            case 'wap/special/details':
                if ($this->request->has('id') && $this->request->get('id')) {
                    $id = $this->request->get('id');
                    $path = '/web/special/details.html?from=special_cate&id=' . $id;
                } else {
                    $path = '/web/special/special_cate';
                }
                break;
            case 'wap/special/single_details':
                if ($this->request->has('id') && $this->request->get('id')) {
                    $id = $this->request->get('id');
                    $path = '/web/special/single_details.html?from=special_cate&id=' . $id;
                } else {
                    $path = '/web/special/special_cate';
                }
                break;
            case 'wap/article/news_list':
                $path = '/web/article/news_list';
                break;
            case 'wap/article/news_detail':
                $url = $this->request->url();
                $path = str_replace('wap/article/news_detail', 'web/article/news_detail', $url);
                break;
            case 'wap/material/material_list':
                $path = '/web/material/material_list';
                break;
            case 'wap/special/data_details':
                $url = $this->request->url();
                $path = str_replace('wap/special/data_details', 'web/material/details', $url);
                break;
            case 'wap/activity/index':
                $path = '/web/activity/index';
                break;
            case 'wap/special/activity_details':
                $url = $this->request->url();
                $path = str_replace('wap/special/activity_details', 'web/activity/detail', $url);
                break;
            case 'wap/topic/question_category':
                $type = 2;
                if ($this->request->has('type') && $this->request->get('type')) {
                    $type = $this->request->get('type');
                }
                $path = '/web/topic/question_category?type=' . $type;
                break;
            case 'wap/special/question_index':
                $url = $this->request->url();
                $path = str_replace('wap/special/question_index', 'web/topic/question_index', $url);
                break;
            case 'wap/topic/question_detail':
                $path = '/web/topic/question_category?type=2';
                break;
            case 'wap/topic/problem_index':
                $url = $this->request->url();
                $path = str_replace('wap/topic/problem_index', 'web/topic/problem_index', $url);
                break;
            case 'wap/topic/problem_detail':
                $path = '/web/topic/question_category?type=1';
                break;
            case 'wap/merchant/teacher_list':
                $path = '/web/merchant/teacher_list';
                break;
            case 'wap/merchant/teacher_detail':
                $url = $this->request->url();
                $path = str_replace('wap/merchant/teacher_detail', 'web/merchant/teacher_detail', $url);
                break;
            case 'wap/my/index':
            case 'wap/my/user_info':
            case 'wap/merchant/income':
                $path = '/web/my/index';
                break;
            case 'wap/my/bill_detail':
                $path = '/web/my/index.html?activeName=balance';
                break;
            case 'wap/my/sign_list':
                $path = '/web/my/index.html?activeName=activity';
                break;
            case 'wap/special/recharge_index':
                $path = '/web/my/index.html?activeName=currency';
                break;
            case 'wap/my/follow_lecturer':
                $path = '/web/my/index.html?activeName=lecturer';
                break;
            case 'wap/special/grade_list':
                $path = '/web/my/index.html?activeName=favorite';
                break;
            case 'wap/material/my_material':
                $path = '/web/my/index.html?activeName=material';
                break;
            case 'wap/topic/question_wrong':
                $path = '/web/my/index.html?activeName=wrong';
                break;
            case 'wap/topic/question_user':
                $path = '/web/my/index.html?activeName=question';
                break;
            case 'wap/special/grade_special':
                $path = '/web/my/index.html?activeName=special';
                break;
            case 'wap/special/member_recharge':
                $path = '/web/my/index.html?activeName=member';
                break;
            case 'wap/my/about_us':
                $path = '/web/my/about_us';
                break;
            default:
                $path = '/web/index/index';
        }
        return $path;
    }
}
