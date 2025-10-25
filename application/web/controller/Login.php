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
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\exception\DbException;
use think\Request;
use think\Session;
use think\Url;
use think\Env;

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
        if (($info = PhoneUser::UserLogIn($phone, $request)) !== false) {
            // ↓↓↓ 新增：记录渠道来源
            $channel = input('channel', '');
            if (empty($channel)) $channel = Session::get('channel_source', 'web');
            if (!empty($channel)) {
                User::where('uid', $info['userinfo']['uid'])->update(['channel_source' => $channel]);
            }
            // ↑↑↑ 新增结束
            return JsonService::successful('登录成功', $info);
        } else
            return JsonService::fail(PhoneUser::getErrorInfo('登录失败'));
    }

    /**
     * @return mixed
     * @descr: I am on my way to future,where you are there.
     * @date : 9/6/24 10:13 AM
     * @author: Trump
     */
    public function view()
    {
        header('Access-Control-Allow-Origin: *');
        $account = input('account', '');
        $pwd = input('pwd', '');
        $this->assign('account', $account);
        $this->assign('pwd', $pwd);
        $url = trim(input('url', ''));
        $this->assign('edu_login_url', '/web/login/check');
        $this->assign('edu_location_url', $url ?: '/web/index/index');
        return $this->fetch();
    }

    /**账号密码登录
     * @param Request $request
     * @return null
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     */
    public function check(Request $request)
    {
        // $allow_domain = Env::get('AccessControl.Access-Control-Allow-Origin', 'https://lb.fanchenai.com');
        // header('Access-Control-Allow-Origin: '.$allow_domain); //设置允许跨域访问
        // header('Access-Control-Allow-Origin: https://lb.fanchenai.com'); //设置允许跨域访问
        // header('Access-Control-Allow-Origin: https://lb.fanxinghy.com'); //设置允许跨域访问
        // header("Access-Control-Allow-Credentials: true"); // 允许携带 credentials（cookie）

        // $allowed_origins = explode(',', $allow_domain);
        // if (is_array($allowed_origins) && isset($_SERVER['HTTP_ORIGIN']) && in_array($_SERVER['HTTP_ORIGIN'], $allowed_origins)) {
        //     header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
        //     header('Access-Control-Allow-Credentials: true');
        //     header('Vary: Origin');
        // }
        header('Access-Control-Allow-Origin: *');
        list($account, $pwd) = UtilService::postMore(['account', 'pwd'], $request, true);
        if (!$account || !$pwd) return JsonService::fail('请输入登录账号');
        if (!PhoneUser::be(['phone' => $account])) return JsonService::fail('登陆账号不存在!');

        $phoneInfo = PhoneUser::where('phone', $account)->find();
        $errorInfo = Session::get('login_error_info', 'web') ?: ['num' => 0];
        $now = time();
        if ($errorInfo['num'] > 5 && $errorInfo['time'] < ($now - 60))
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

        // $_o = [];
        // // $options = ['expire' => 60*60*24, 'path' => '/', 'domain' => $_SERVER['HTTP_HOST'], 'secure' => true, 'httponly' => true];
        // $options = ['secure' => true, 'httponly' => true];
        // foreach ($options as $k => $v) {
        //     $_o[] = $k.'='.$v;
        // }
        // $options_str = implode('&', $_o);
        // Cookie::set('is_login', 1, $options_str);
        // Cookie::set('__login_phone', 1, $options_str);

        // setcookie(Cookie::prefix().'is_login', 1, time() + (86400 * 7), "/", $_SERVER['HTTP_HOST'], true, true); // Secure 和 HttpOnly
        // setcookie(Cookie::prefix().'__login_phone', 1, time() + (86400 * 7), "/", $_SERVER['HTTP_HOST'], true, true); // Secure 和 HttpOnly

        setcookie(Cookie::prefix().'is_login', 1, [
            'expires' => time() + 86400,  // 1天后过期
            'path' => '/',
            'domain' => $_SERVER['HTTP_HOST'],    // 确保是目标域名
            'secure' => true,             // 仅在 HTTPS 中有效
            'httponly' => true,           // 仅通过 HTTP 传输，防止 JavaScript 访问
            'samesite' => 'None'          // 允许跨域传输
        ]);

        setcookie(Cookie::prefix().'__login_phone', 1, [
            'expires' => time() + 86400,  // 1天后过期
            'path' => '/',
            'domain' => $_SERVER['HTTP_HOST'],    // 确保是目标域名
            'secure' => true,             // 仅在 HTTPS 中有效
            'httponly' => true,           // 仅通过 HTTP 传输，防止 JavaScript 访问
            'samesite' => 'None'          // 允许跨域传输
        ]);
        // Cookie::set('is_login', 1);
        // Cookie::set('__login_phone', 1);

        setcookie(Cookie::prefix().'account', $account, [
            'expires' => time() + 86400,  // 1天后过期
            'path' => '/',
            'domain' => $_SERVER['HTTP_HOST'],    // 确保是目标域名
            'secure' => true,             // 仅在 HTTPS 中有效
            'httponly' => true,           // 仅通过 HTTP 传输，防止 JavaScript 访问
            'samesite' => 'None'          // 允许跨域传输
        ]);
        setcookie(Cookie::prefix().'pwd', $pwd, [
            'expires' => time() + 86400,  // 1天后过期
            'path' => '/',
            'domain' => $_SERVER['HTTP_HOST'],    // 确保是目标域名
            'secure' => true,             // 仅在 HTTPS 中有效
            'httponly' => true,           // 仅通过 HTTP 传输，防止 JavaScript 访问
            'samesite' => 'None'          // 允许跨域传输
        ]);



        Session::set($name, $userinfo['phone'], 'web');
        Session::set('__login_phone_num' . $userinfo['uid'], $userinfo['phone'], 'web');
        // ↓↓↓ 新增：记录渠道来源
        $channel = input('channel', '');
        if (empty($channel)) $channel = Session::get('channel_source', 'web');
        if (!empty($channel)) {
            User::where('uid', $userinfo['uid'])->update(['channel_source' => $channel]);
        }
        // ↑↑↑ 新增结束
        return JsonService::successful('登录成功', $userinfo);
    }

    /**
     * 账号密码注册/找回密码
     *
     * @param Request $request
     * @return null
     */

    /**
     * 账号密码注册/找回密码
     *
     * @param Request $request
     * @return null
     */
    public function register(Request $request)
    {
        list($account, $pwd, $code, $type, $nickname) = UtilService::postMore([
            ['account', ''],
            ['pwd', ''],
            ['code', ''],
            ['type', 1],//1-常规注册,2-找回密码,3-来自领播用户的注册
            ['nickname', ''],
        ], $request, true);
        if (!$code) return JsonService::fail('请输入验证码');
        if (!$account || !$pwd) return JsonService::fail('参数有误！');
        if ($type != 3) {
            $code = md5('is_phone_code' . $code);
            if (!SmsCode::CheckCode($account, $code)) return JsonService::fail('验证码验证失败');
            SmsCode::setCodeInvalid($account, $code);
        }
        $msg = $type == 1 || $type == 3 ? '注册' : '找回密码';
        if ((PhoneUser::userRegister($account, $pwd, $type, $nickname, $request)) !== false)
            return JsonService::successful($msg . '成功');
        else
            return JsonService::fail(PhoneUser::getErrorInfo(PhoneUser::getErrorInfo($msg . '失败')));
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
