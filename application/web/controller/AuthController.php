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

use app\web\model\user\User;
use basic\WapBasic;
use service\JsonService;
use service\SystemConfigService;
use app\web\model\user\MemberShip;
use service\UtilService;
use think\Cache;
use think\cache\driver\Redis;
use think\Cookie;
use think\Session;
use think\Url;

/**全局调用控制器
 * Class AuthController
 * @package app\web\controller
 */
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

    protected $redisModel;

    protected $subjectUrl = '';

    protected function _initialize()
    {
        parent::_initialize();

        $config = config('redis');
        var_dump($config, 11111);
        exit;
        
        $pc_on_display = SystemConfigService::get('pc_on_display');
        if (request()->isMobile() || $pc_on_display == 0) {
            $path = $this->getJumpPath();
            return $this->redirect($path);
        }
        try {
            // $this->redisModel = new Redis();
            $config = config('redis');
            $this->redisModel = (new Redis($config))->handler();;
        } catch (\Exception $e) {
            parent::serRedisPwd($e->getMessage());
        }
        $NoWechantVisitWhite = $this->NoWechantVisitWhite();
        $this->subjectUrl = getUrlToDomain();
        try {
            $uid = User::getActiveUid();
            if (!empty($uid)) {
                $this->userInfo = User::getUserInfo($uid);
                MemberShip::memberExpiration($uid);
                $this->uid = $this->userInfo['uid'];
                $this->phone = User::getLogPhone($uid);
                if (!isset($this->userInfo['uid'])) $this->userInfo['uid'] = 0;
                if (!isset($this->userInfo['avatar'])) $this->userInfo['avatar'] = '';
                if (!isset($this->userInfo['nickname'])) $this->userInfo['nickname'] = '';
                if (!$NoWechantVisitWhite) {
                    if (!$this->userInfo || !isset($this->uid)) $this->failed('读取用户信息失败!');
                    if (!$this->userInfo['status']) $this->failed('已被禁止登陆!');
                }
            }
        } catch (\Exception $e) {
            Cookie::delete('is_login');
            Cookie::delete('__login_phone');
            Session::clear('web');
            if (!$NoWechantVisitWhite) {
                if ($this->request->isAjax())
                    return JsonService::fail('请登录再进行访问');
                else
                    return $this->redirect(Url::build('web/index/index'));
            }
        }
        $codeUrl = SystemConfigService::get('wechat_qrcode');
        $balance_switch = SystemConfigService::get('balance_switch');//余额开关
        $is_alipay = SystemConfigService::get('pc_alipay_code_scanning_payment_switch');//pc端支付宝扫码开关
        $is_wechat = SystemConfigService::get('pc_wechat_code_scanning_payment_switch');//pc端微信扫码支付开关
        $instructor_application_switch = SystemConfigService::get('instructor_application_switch');//pc讲师申请开关
        $official_account_switch = SystemConfigService::get('official_account_switch');//关注公众号开关
        $seo_title = SystemConfigService::get('seo_title');//SEO标题
        $seo_description = SystemConfigService::get('description');//SEO内容
        $now_money = isset($this->userInfo['now_money']) ? $this->userInfo['now_money'] : 0;
        $business = isset($this->userInfo['business']) ? $this->userInfo['business'] : 0;//是否是讲师
        $this->assign([
            'is_yue' => $balance_switch,
            'is_alipay' => $is_alipay,
            'code_url' => $codeUrl,
            'is_wechat' => $is_wechat,
            'phone' => $this->phone,
            'userInfo' => $this->userInfo,
            'seo_title' => $seo_title,
            'seo_description' => $seo_description,
            'instructor_application_switch' => $instructor_application_switch,
            'is_official_account_switch' => $official_account_switch,
            'business' => $business,
            'now_money' => $now_money,
            'Auth_site_name' => SystemConfigService::get('site_name')
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

    /**pc端页面链接转移动端页面链接
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
            case 'web/special/special_cate':
            case 'web/live/special_live':
            case 'web/index/more_list':
                $path = '/wap/special/special_cate';
                break;
            case 'web/special/details':
                $url = $this->request->url();
                $path = str_replace('web/special/details', 'wap/special/details', $url);
                break;
            case 'web/special/single_details':
                $url = $this->request->url();
                $path = str_replace('web/special/single_details', 'wap/special/single_details', $url);
                break;
            case 'web/article/news_list':
                $path = '/wap/article/news_list';
                break;
            case 'web/article/news_detail':
                $url = $this->request->url();
                $path = str_replace('web/article/news_detail', 'wap/article/news_detail', $url);
                break;
            case 'web/material/material_list':
                $path = '/wap/material/material_list';
                break;
            case 'web/material/details':
                $url = $this->request->url();
                $path = str_replace('web/material/details', 'wap/special/data_details', $url);
                break;
            case 'web/activity/index':
                $path = '/wap/activity/index';
                break;
            case 'web/activity/detail':
                $url = $this->request->url();
                $path = str_replace('web/activity/detail', 'wap/special/activity_details', $url);
                break;
            case 'web/topic/question_category':
                $type = 2;
                if ($this->request->has('type') && $this->request->get('type')) {
                    $type = $this->request->get('type');
                }
                $path = '/wap/topic/question_category?type=' . $type;
                break;
            case 'web/topic/question_index':
                $url = $this->request->url();
                $path = str_replace('web/topic/question_index', 'wap/special/question_index', $url);
                break;
            case 'web/topic/question_detail':
                $path = '/wap/topic/question_category?type=2';
                break;
            case 'web/topic/problem_index':
                $url = $this->request->url();
                $path = str_replace('web/topic/problem_index', 'wap/topic/problem_index', $url);
                break;
            case 'web/topic/problem_detail':
                $path = '/wap/topic/question_category?type=1';
                break;
            case 'web/merchant/teacher_list':
                $path = '/wap/merchant/teacher_list';
                break;
            case 'web/merchant/teacher_detail':
                $url = $this->request->url();
                $path = str_replace('web/merchant/teacher_detail', 'wap/merchant/teacher_detail', $url);
                break;
            case 'web/my/index':
                $path = '/wap/my/index';
                break;
            case 'web/my/about_us':
                $path = '/wap/my/about_us';
                break;
            default:
                $path = '/wap/index/index';
        }
        return $path;
    }
}
