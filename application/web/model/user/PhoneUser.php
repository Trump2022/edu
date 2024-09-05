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


use basic\ModelBasic;
use service\QrcodeService;
use think\Cookie;
use think\Session;
use traits\ModelTrait;
use service\SystemConfigService;

/**h5用户
 * Class PhoneUser
 * @package app\web\model\user
 */
class PhoneUser extends ModelBasic
{
    use ModelTrait;

    /**获取手机号
     * @param $uid
     * @return mixed
     */
    public static function UidToPhone($uid)
    {
        return self::where('uid', $uid)->value('phone');
    }

    /**手机号登录、注册
     * @param $phone
     * @param $request
     * @return array|bool
     */
    public static function UserLogIn($phone, $request)
    {
        self::startTrans();
        try {
            $name = '__login_phone_number';
            if (self::be(['phone' => $phone])) {
                $user = self::where('phone', $phone)->find();
                $user->last_ip = $request->ip();
                $user->last_time = time();
                if ($user->uid) {
                    Session::set('loginUid', $user->uid, 'web');
                }
                $user->save();
                $userinfo = User::where('uid', $user->uid)->find();
                if (!$userinfo->status) return self::setErrorInfo('账户已被禁止登录');
                $userinfo->last_ip = $request->ip();
                $userinfo->last_time = time();
                $userinfo->save();
            } else {
                $userinfo = User::where(['phone' => $phone])->find();
                if ($userinfo && !$userinfo->status) return self::setErrorInfo('账户已被禁止登录');
                if (!$userinfo) $userinfo = User::set([
                    'nickname' => $phone,
                    'pwd' => md5(123456),
                    'avatar' => '/system/images/user_log.jpg',
                    'account' => $phone,
                    'phone' => $phone,
                    'add_ip' => $request->ip(),
                    'add_time' => time(),
                    'is_h5user' => 2,
                ]);
                if (!$userinfo) return self::setErrorInfo('用户信息写入失败', true);
                Session::set('loginUid', $userinfo['uid'], 'web');
                $user = self::set([
                    'phone' => $phone,
                    'avatar' => '/system/images/user_log.jpg',
                    'nickname' => $phone,
                    'uid' => $userinfo['uid'],
                    'add_ip' => $request->ip(),
                    'add_time' => time(),
                    'pwd' => md5(123456),
                ]);
                if (!$user) return self::setErrorInfo('手机用户信息写入失败', true);
            }
            unset($userinfo['pwd']);
            Cookie::set('__login_phone', 1);
            Cookie::set('is_login', 1);
            Session::set($name, $phone, 'web');
            Session::set('__login_phone_num' . $userinfo['uid'], $phone, 'web');
            self::commit();
            return ['userinfo' => $userinfo];
        } catch (\Exception $e) {
            return self::setErrorInfo($e->getMessage());
        }
    }
}
