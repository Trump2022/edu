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

use app\wap\model\user\SmsCode;
use app\wap\model\user\PhoneUser;
use app\wap\model\user\WechatUser;
use app\wap\model\user\User;
use basic\WapBasic;
use service\SystemConfigService;
use service\UtilService;
use service\JsonService;
use think\Cache;
use think\Cookie;
use think\Env;
use think\Request;
use think\Session;
use think\Url;

/**移动端登录控制器
 * Class Login
 * @package app\wap\controller
 */
class Login extends WapBasic
{
    public function index($ref = '', $spread_uid = 0)
    {
        $isWechat = UtilService::isWechatBrowser();
        $appid = SystemConfigService::get('wechat_appid');
        if ($ref) {
            $host_url = $_SERVER['HTTP_HOST'];
            $ref = $this->getController($ref,$host_url);
        }
        $this->assign(['appid' => $appid, 'ref' => $ref, 'spread_uid' => $spread_uid, 'isWechat' => $isWechat, 'Auth_site_name' => SystemConfigService::get('site_name')]);
        return $this->fetch();
    }

    /**
     * 检查控制器是否存在
     * @param $ref
     * @param $host_url
     * @return string
     */
    public function getController($ref, $host_url)
    {
        $site_url = SystemConfigService::get('site_url');
        $ref = htmlspecialchars_decode(base64_decode($ref));
        $url = parse_url($ref);
        if (!in_array($url['scheme'] . '://' . $url['host'], [$host_url, $site_url])) $ref = '';
        $path = explode('/',$url['path']);
        if($path[1] != 'wap') $ref = '';
        if($path[2] == '') $ref = '';
        $name = strtolower($path[2]);
        $nameAll = ['activity','alipay','article','callback','index','live','material','member','merchant','my','service','special','spread','store','topic'];
        if (!in_array($name, $nameAll)) $ref = '';
        if($path[3] == '') $ref = '';
        return $ref;
    }

    /**微信登录操作
     * @param $spread_uid
     * @return void
     */
    public function weixin_check($spread_uid = 0, $code = '')
    {
        Cookie::set('is_bg', 1);
        $this->_logout();
        $openid = $this->oauth($spread_uid, $code);
        if ($openid) {
            Cookie::delete('_oen');
            return JsonService::successful('微信登录成功', $openid);
        } else {
            return JsonService::fail(WechatUser::getErrorInfo());
        }
    }

    /**
     * 短信登陆
     * @param Request $request
     */
    public function phone_check(Request $request)
    {
        list($phone, $code, $spread_uid) = UtilService::postMore([
            ['phone', ''],
            ['code', ''],
            ['spread_uid', 0],
        ], $request, true);
        if (!$phone || !$code) return JsonService::fail('请输入登录账号');
        if (!$code) return JsonService::fail('请输入验证码');
        $code = md5('is_phone_code' . $code);
        if (!SmsCode::CheckCode($phone, $code)) return JsonService::fail('验证码验证失败');
        SmsCode::setCodeInvalid($phone, $code);
        if (($info = PhoneUser::UserLogIn($phone, $request)) !== false) {
            if ($spread_uid) {
                $spreadUserInfo = User::getUserData($spread_uid);
                $uid = $info['userinfo']['uid'];
                $userInfo = $info['userinfo'];
                //绑定推广人
                if ($spread_uid && $spreadUserInfo && $uid != $spread_uid && $spreadUserInfo['spread_uid'] != $uid && $userInfo['spread_uid'] != $spread_uid && !$userInfo['spread_uid']) {
                    $storeBrokerageStatu = SystemConfigService::get('store_brokerage_statu') ?: 1;//获取后台分销类型
                    if ($storeBrokerageStatu == 1) {
                        if ($spreadUserInfo['is_promoter']) User::edit(['spread_uid' => $spread_uid], $uid, 'uid');
                    } else {
                        User::edit(['spread_uid' => $spread_uid], $uid, 'uid');
                    }
                }
            }
            return JsonService::successful('登录成功', $info);
        } else
            return JsonService::fail(PhoneUser::getErrorInfo('登录失败'));
    }

    /**
     * @return mixed
     * @descr: I am on my way to future,where you are there.
     * @date : 9/6/24 10:45 AM
     * @author: Trump
     */
    public function view()
    {
        $account = input('account', '');
        $pwd = input('pwd', '');
        $url = input('url', '');
        $this->assign('account', $account);
        $this->assign('pwd', $pwd);
        $this->assign('spread_uid', 0);
        $this->assign('edu_login_url', '/wap/login/check');
        $this->assign('edu_location_url', $url ?: '/wap/index/index');
        return $this->fetch();
    }

    /**账号密码登录
     * @param Request $request
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function check(Request $request)
    {
        $allow_domain = Env::get('AccessControl.Access-Control-Allow-Origin', 'https://lb.fanchenai.com');
        header('Access-Control-Allow-Origin: '.$allow_domain); //设置允许跨域访问
        header("Access-Control-Allow-Credentials: true"); // 允许携带 credentials（cookie）
        list($account, $pwd, $spread_uid) = UtilService::postMore(['account', 'pwd', ['spread_uid', 0]], $request, true);
        if (!$account || !$pwd) return JsonService::fail('请输入登录账号');
        if (!PhoneUser::be(['phone' => $account])) return JsonService::fail('登陆账号不存在!');
        $phoneInfo = PhoneUser::where('phone', $account)->find();
        $errorInfo = Session::get('login_error_info', 'wap') ?: ['num' => 0];
        $now = time();
        if ($errorInfo['num'] > 5 && $errorInfo['time'] < ($now - 900))
            return JsonService::fail('错误次数过多,请稍候再试!');
        if ($phoneInfo['pwd'] != $pwd) {
            Session::set('login_error_info', ['num' => $errorInfo['num'] + 1, 'time' => $now], 'wap');
            return JsonService::fail('账号或密码输入错误!');
        }
        $userinfo = User::where('uid', $phoneInfo['uid'])->find();
        if (!$userinfo) return JsonService::fail('账号异常!');
        if (!$userinfo['status']) return JsonService::fail('账号已被锁定,无法登陆!');
        $this->_logout();
        $name = '__login_phone_number';
        Session::set('loginUid', $userinfo['uid'], 'wap');
        $phoneInfo['last_time'] = time();
        $phoneInfo['last_ip'] = $request->ip();
        $phoneInfo->save();
        $userinfo['last_time'] = time();
        $userinfo['last_ip'] = $request->ip();
        $userinfo->save();
        unset($userinfo['pwd']);
        Session::delete('login_error_info', 'wap');

        // $_o = [];
        // // $options = ['expire' => 60*60*24, 'path' => '/', 'domain' => $_SERVER['HTTP_HOST'], 'secure' => true, 'httponly' => true];
        // $options = ['secure' => true, 'httponly' => true];
        // foreach ($options as $k => $v) {
        //     $_o[] = $k.'='.$v;
        // }
        // $options_str = implode('&', $_o);
        // Cookie::set('is_login', 1, $options_str);
        // Cookie::set('__login_phone', 1, $options_str);

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

        Cookie::set('is_login', 1);
        Cookie::set('__login_phone', 1);

        Session::set($name, $userinfo['phone'], 'wap');
        Session::set('__login_phone_num' . $userinfo['uid'], $userinfo['phone'], 'wap');
        $qrcode_url = SystemConfigService::get('wechat_qrcode');
        if ($spread_uid) {
            $spreadUserInfo = User::getUserData($spread_uid);
            $uid = $userinfo['uid'];
            //绑定推广人
            if ($spread_uid && $spreadUserInfo && $uid != $spread_uid && $spreadUserInfo['spread_uid'] != $uid && $userinfo['spread_uid'] != $spread_uid && !$userinfo['spread_uid']) {
                $storeBrokerageStatu = SystemConfigService::get('store_brokerage_statu') ?: 1;//获取后台分销类型
                if ($storeBrokerageStatu == 1) {
                    if ($spreadUserInfo['is_promoter']) User::edit(['spread_uid' => $spread_uid], $uid, 'uid');
                } else {
                    User::edit(['spread_uid' => $spread_uid], $uid, 'uid');
                }
            }
        }
        $info = ['userinfo' => $userinfo, 'url' => $qrcode_url, 'qcode_id' => 0, 'isfollow' => false];
        return JsonService::successful('登录成功', $info);
    }

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
        $this->successful('退出登陆成功', Url::build('Index/index'));
    }

    /**
     * 清除缓存
     */
    private function _logout()
    {
        Session::clear('wap');
        Cookie::delete('is_bg');
        Cookie::delete('is_login');
        Cookie::delete('__login_phone');
    }

}
