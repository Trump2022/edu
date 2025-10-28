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

    /**用户注册账号、找回密码
     * @param $account
     * @param $pwd
     * @param $type
     * @param $nickname
     * @param $request
     * @param string $channel
     * @return bool
     */
    public static function userRegister($account, $pwd, $type, $nickname, $request, string $channel = ''): bool
    {
        self::beginTrans();
        try {
            if ($type == 2) {
                $user = self::where('phone', $account)->find();
                if (!$user) return self::setErrorInfo('您需要先注册h5或pc账号', true);
                $userinfo = User::where(['phone' => $account])->find();
                if (!$userinfo) return self::setErrorInfo('您要找回的账号不存在', true);
                if (!$userinfo->status) return self::setErrorInfo('账户已被禁止登录');
                if ($user['pwd'] == $pwd || $userinfo['pwd'] == $pwd) return self::setErrorInfo('新密码和旧密码重复', true);
                $res1 = User::where(['phone' => $account])->update(['pwd' => $pwd]);
                $res2 = self::where(['phone' => $account])->update(['pwd' => $pwd]);
                $res = $res1 && $res2;
                self::checkTrans($res);
                if ($res) {
                    return true;
                } else {
                    return false;
                }
            } else if ($type == 1 || $type == 3) {
                $userinfo = User::where(['phone' => $account])->find();
                if ($userinfo && !$userinfo->status) {
                    return self::setErrorInfo('账户已被禁止登录');
                }

                $nickname = $nickname ?: $account;
                if (!$userinfo) {
                    $userinfo = User::set([
                        'nickname' => $nickname,
                        'pwd' => $pwd,
                        'avatar' => '/system/images/user_log.jpg',
                        'account' => $account,
                        'phone' => $account,
                        'channel' => $channel,
                        'is_h5user' => 2,
                        'is_crm_user' => $type == 3 ? 1 : 0,
                    ]);
                } else {
                    if ($type == 3) {
                        //修改密码和昵称
                        //user表
                        $userinfo['pwd'] = $pwd;
                        if ($nickname != $userinfo['nickname'] || ! $userinfo['nickname']) {
                            $userinfo['nickname'] = $nickname;
                        }
                        $userinfo->save();

                        //phone_user表
                        $upData = [];
                        $upData['pwd'] = $pwd;
                        $user = self::where('phone', $account)->find();
                        if ($nickname != $user['nickname'] || ! $user['nickname']) {
                            $upData['nickname'] = $nickname;
                        }
                        if ($upData) {
                            self::where('phone', $account)->update($upData);
                        }

                        self::commitTrans();
                        return true;
                    }
                }
                if (!$userinfo) {
                    return self::setErrorInfo('用户信息写入失败', true);
                }
                $user = self::where('phone', $account)->find();
                if ($user) {
                    return self::setErrorInfo('账号已被使用', true);
                }
                $user = self::set([
                    'phone' => $account,
                    'avatar' => '/system/images/user_log.jpg',
                    'nickname' => $nickname,
                    'uid' => $userinfo['uid'],
                    'add_ip' => $request->ip(),
                    'add_time' => time(),
                    'pwd' => $pwd,
                ]);
                if (!$user) return self::setErrorInfo('手机用户信息写入失败', true);
                self::commitTrans();
                return true;
            }
        } catch (\Exception $e) {
            return self::setErrorInfo($e->getMessage());
        }
    }
}
