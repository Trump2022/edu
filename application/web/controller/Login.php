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
use app\wap\model\user\WechatUser;
use app\web\model\user\PhoneUser;
use app\web\model\user\User;
use basic\WapBasic;
use service\SystemConfigService;
use service\WechatService;
use service\UtilService;
use service\JsonService;
use think\Cookie;
use think\Request;
use think\Session;
use think\Url;

/**pc端登录控制器
 * Class Login
 * @package app\web\controller
 */
class Login extends WapBasic
{

    /**
     * 短信登陆/注册
     * @param Request $request
     */
    public function phone_check(Request $request)
    {
        list($phone, $code) = UtilService::postMore([
            ['phone', ''],
            ['code', ''],
        ], $request, true);
        if (!$phone || !$code) return JsonService::fail('请输入登录账号');
        if (!$code) return JsonService::fail('请输入验证码');
        $code = md5('is_phone_code' . $code);
        if (!SmsCode::CheckCode($phone, $code)) return JsonService::fail('验证码验证失败');
        SmsCode::setCodeInvalid($phone, $code);
        if (($info = PhoneUser::UserLogIn($phone, $request)) !== false)
            return JsonService::successful('登录成功', $info);
        else
            return JsonService::fail(PhoneUser::getErrorInfo('登录失败'));
    }

    /**账号密码登录
     * @param Request $request
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function check(Request $request)
    {
        list($account, $pwd) = UtilService::postMore(['account', 'pwd'], $request, true);
        if (!$account || !$pwd) return JsonService::fail('请输入登录账号');
        if (!PhoneUser::be(['phone' => $account])) return JsonService::fail('登陆账号不存在!');
        $phoneInfo = PhoneUser::where('phone', $account)->find();
        $errorInfo = Session::get('login_error_info', 'web') ?: ['num' => 0];
        $now = time();
        if ($errorInfo['num'] > 5 && $errorInfo['time'] < ($now - 900))
            return JsonService::fail('错误次数过多,请稍候再试!');
        if ($phoneInfo['pwd'] != $pwd) {
            Session::set('login_error_info', ['num' => $errorInfo['num'] + 1, 'time' => $now], 'web');
            return JsonService::fail('账号或密码输入错误!');
        }
        $userinfo = User::where('uid', $phoneInfo['uid'])->find();
        if (!$userinfo) return JsonService::fail('账号异常!');
        if (!$userinfo['status']) return JsonService::fail('账号已被锁定,无法登陆!');
        $this->_logout();
        $name = '__login_phone_number';
        Session::set('loginUid', $userinfo['uid'], 'web');
        $phoneInfo['last_time'] = time();
        $phoneInfo['last_ip'] = $request->ip();
        $phoneInfo->save();
        $userinfo['last_time'] = time();
        $userinfo['last_ip'] = $request->ip();
        $userinfo->save();
        unset($userinfo['pwd']);
        Session::delete('login_error_info', 'web');
        Cookie::set('is_login', 1);
        Cookie::set('__login_phone', 1);
        Session::set($name, $userinfo['phone'], 'web');
        Session::set('__login_phone_num' . $userinfo['uid'], $userinfo['phone'], 'web');
        return JsonService::successful('登录成功', $userinfo);
    }

    /**
     * 账号密码注册/找回密码
     *
     * @param Request $request
     * @return null
     */
    public function register(Request $request)
    {
        return parent::register($request);
    }

    /**
     * 退出登陆
     */
    public function logout()
    {
        $this->_logout();
        $this->successful('退出登陆成功', Url::build('web/index/index'));
    }

    /**
     * 清除缓存
     */
    private function _logout()
    {
        Session::clear('web');
        Cookie::delete('is_login');
        Cookie::delete('__login_phone');
    }

    /**生成唯一值
     * @return string
     */
    public function getUniquid()
    {
        return md5(time());
    }

    /**
     * 获取登录临时二维码
     */
    public function login_pc_qrcode()
    {
        $qrcode = WechatService::qrcodeService();
        $key = $this->getUniquid();
        Session::set('scan_key' . $key, 180,'web');
        $code = $qrcode->temporary($key, 180);
        $code['fail_time'] = $code['expire_seconds'] + time();
        $code['url'] = $qrcode->url($code['ticket']);
        $code['key'] = $key;
        return $code;
    }

    /**获取登录状态
     * @param Request $request
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function setScanLogin(Request $request)
    {
        list($key) = UtilService::postMore([
            ['key', '']
        ], $request, true);
        if (!$key) return JsonService::fail('参数无效');
        $wechatUser = WechatUser::where('scan_key', $key)->field('uid,nickname,subscribe,subscribe_time')->find();
        if (!$wechatUser) return JsonService::fail('尚未扫码');
        if (!$wechatUser['subscribe']) return JsonService::fail('尚未关注');
        $userinfo = User::where('uid', $wechatUser['uid'])->find();
        if (!$userinfo) return JsonService::fail('账号异常!');
        if (!$userinfo['status']) return JsonService::fail('账号已被锁定,无法登陆!');
        $this->_logout();
        Session::set('loginUid', $userinfo['uid'], 'web');
        $userinfo['last_time'] = time();
        $userinfo['last_ip'] = $request->ip();
        $userinfo->save();
        unset($userinfo['pwd']);
        $name = '__login_phone_number';
        Cookie::set('is_login', 1);
        Cookie::set('__login_phone', 1);
        Session::set($name, $userinfo['phone'], 'web');
        Session::set('__login_phone_num' . $userinfo['uid'], $userinfo['phone'], 'web');
        Session::delete('scan_key' . $key);
        return JsonService::successful('登录成功', $userinfo);
    }

}
