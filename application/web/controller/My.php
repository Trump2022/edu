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
use app\web\model\user\PhoneUser;
use app\web\model\user\User;
use app\web\model\user\UserBill;
use service\CacheService;
use service\GroupDataService;
use service\JsonService;
use service\SystemConfigService;
use service\UtilService;
use think\Cookie;
use think\Request;
use think\Session;
use think\Url;

/**my控制器
 * Class My
 * @package app\web\controller
 */
class My extends AuthController
{

    /**
     * 白名单
     * */
    public static function WhiteList()
    {
        return [
            'index',
            'about_us',
        ];
    }

    /**
     * 个人中心
     * @return mixed
     * @throws \think\Exception
     */
    public function index()
    {
        return $this->fetch();
    }

    /**用户信息
     * @return mixed
     */
    public function user_info()
    {
        return $this->fetch();
    }

    /**
     * 验证手机号
     */
    public function validate_code()
    {
        list($phone, $code,) = UtilService::getMore([
            ['phone', ''],
            ['code', ''],
        ], $this->request, true);
        if (!$phone) return JsonService::fail('请输入手机号码');
        if (!$code) return JsonService::fail('请输入验证码');
        $code = md5('is_phone_code' . $code);
        if (!SmsCode::CheckCode($phone, $code)) return JsonService::fail('验证码验证失败');
        SmsCode::setCodeInvalid($phone, $code);
        return JsonService::successful('验证成功');
    }

    /**
     * 用户数据保存
     */
    public function save_user_info()
    {
        $data = UtilService::postMore([
            ['avatar', ''],
            ['nickname', '']
        ], $this->request);
        if ($data['nickname'] != strip_tags($data['nickname'])) {
            $data['nickname'] = htmlspecialchars($data['nickname']);
        }
        if (!$data['nickname']) return JsonService::fail('用户昵称不能为空');
        if (User::update($data, ['uid' => $this->uid]))
            return JsonService::successful('保存成功');
        else
            return JsonService::fail('保存失败');
    }

    /**
     * 保存新手机号码
     * @return mixed|void
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function save_phone()
    {
        list($phone, $code, $type) = UtilService::getMore([
            ['phone', ''],
            ['code', ''],
            ['type', 0],
        ], $this->request, true);
        if (!$phone) return JsonService::fail('请输入手机号码');
        if (!$code) return JsonService::fail('请输入验证码');
        $code = md5('is_phone_code' . $code);
        if (!SmsCode::CheckCode($phone, $code)) return JsonService::fail('验证码验证失败');
        SmsCode::setCodeInvalid($phone, $code);
        $user = User::where(['phone' => $phone, 'is_h5user' => 0])->find();
        if ($type && $user) {
            if ($user['uid'] == $this->uid) {
                return JsonService::fail('不能绑定相同手机号');
            } else if ($user['uid'] != $this->uid) {
                return JsonService::fail('当前手机号码已绑定微信用户');
            }
        } else if ($type == 0 && $user) {
            if ($user) return JsonService::fail('当前手机号码已绑定微信用户');
        }
        //查找H5手机号码账户
        $phoneUser = PhoneUser::where(['phone' => $phone])->find();
        //H5页面有注册过
        if ($phoneUser && $phoneUser['uid'] != $this->uid) {
            //检测当前用户是否是H5用户
            if (User::where('uid', $phoneUser['uid'])->value('is_h5user')) {
                $res = User::setUserRelationInfos($phone, $phoneUser['uid'], $this->uid);
                if ($res === false) return JsonService::fail(User::getErrorInfo());
            }
        } else if ($phoneUser && $phoneUser['uid'] == $this->uid) {
            return JsonService::fail('不能绑定相同手机号');
        }
        if (!isset($res)) {
            $nickname = User::where('uid', $this->uid)->value('nickname');
            $nick = ['phone' => $phone, 'account' => $phone];
            if(check_phone($nickname)) $nick['nickname'] = $phone;
            User::update($nick, ['uid' => $this->uid]);
            PhoneUser::update(['phone' => $phone, 'nickname' => $phone], ['uid' => $this->uid]);
        }
        return JsonService::successful('绑定成功');
    }

    /**
     * 关于我们
     * @return mixed
     */
    public function about_us()
    {
        $this->assign([
            'content' => get_config_content('about_us'),
            'title' => '关于我们'
        ]);
        return $this->fetch('index/agree');
    }
}
