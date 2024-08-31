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

namespace app\wap\model\wap;

use service\sms\storage\Sms;
use app\admin\model\system\SystemMessage;
use app\wap\model\user\User;
use service\SystemConfigService;

/**
 * 发送短信消息
 * Class SmsTemplate
 * @package app\wap\model\wap
 */
class SmsTemplate
{
    /**发送短信
     * @param $uid
     * @param $data
     * @param $template_const
     * @return bool
     * @throws \Exception
     */
    public static function sendSms($uid, $data, $template_const)
    {
        $sms_platform_selection = SystemConfigService::get('sms_platform_selection');
        if ($sms_platform_selection == 1) return true;
        $message = SystemMessage::getSystemMessage($template_const);
        if ($message['is_sms'] && $message['temp_id']) {
            $user = User::where('uid', $uid)->field('phone,nickname')->find();
            if (isset($data['phone']) && $data['phone'] != '') {
                $phone = $data['phone'];
            } else {
                $phone = $user['phone'];
            }
            switch ($template_const) {
                case 'ORDER_POSTAGE_SUCCESS': //订单发货提醒
                    if (isset($data['phone']) && $data['phone'] != '') {
                        unset($data['phone']);
                    }
                    $data['nickname'] = $user['nickname'];
                    break;
                case 'ORDER_TAKE_SUCCESS': //订单收货通知
                    if (isset($data['phone']) && $data['phone'] != '') {
                        unset($data['phone']);
                    }
                    break;
                case 'ORDER_REFUND_STATUS': //退款成功通知
                    if (isset($data['phone']) && $data['phone'] != '') {
                        unset($data['phone']);
                    }
                    break;
                case 'ORDER_USER_GROUPS_SUCCESS': //拼团成功通知
                case 'ORDER_USER_GROUPS_LOSE': //拼团失败通知
                case 'ORDER_PAY_SUCCESS': //订单支付成功通知
                case 'LIVE_START_NOTICE': //开播提醒
                case 'ORDER_USER_SIGN_UP_SUCCESS': //活动报名成功通知
                case 'WITHDRAWAL_STATUS': //提现成功通知
                case 'WITHDRAWAL_FAIL': //提现失败通知
                case 'ORDER_BROKERAGE': //佣金到账通知
                    break;
                case 'ENTRY_FAILED': //讲师入驻未通过通知
                    if (isset($data['phone']) && $data['phone'] != '') {
                        unset($data['phone']);
                    }
                    break;
                case 'SETTLED_THROUGH': //讲师入驻审核通过通知
                    $phone = $data['phone'];
                    break;
            }
            $smsHandle = new Sms();
            $res = $smsHandle->send($phone, $message['temp_id'], $data);
            if ($res['Code'] == 'OK') {
                return true;
            } else {
                return false;
            }
        } else {
            return true;
        }
    }
}
