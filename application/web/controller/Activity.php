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

use app\web\model\activity\EventRegistration;
use app\web\model\activity\EventSignUp;
use app\web\model\activity\EventData;
use app\web\model\activity\EventPrice;
use service\JsonService;
use service\SystemConfigService;
use think\Url;

/**
 * 活动控制器
 * Class Activity
 */
class Activity extends AuthController
{

    /**
     * 白名单
     */
    public static function WhiteList()
    {
        return [
            'index',
            'activityList',
            'detail',
            'relatedActivities',
            'activity_details'
        ];
    }

    /**活动列表
     * @return mixed
     */
    public function index()
    {
        return $this->fetch('list');
    }

    /**活动详情
     * @return mixed
     */
    public function detail($id)
    {
        $activity = EventRegistration::oneActivitys($id);
        if (!$activity) $this->failed('您查看的活动不存在', Url::build('activity/index'));
        $this->assign([
            'title'=>$activity['title'],
            'is_member' => isset($this->userInfo['level']) ? $this->userInfo['level'] : 0,
            'activity' => json_encode($activity),
            'mobile_url' => SystemConfigService::get('site_url') . Url::build('wap/special/activity_details') . '?id=' . $id,
            'activity_rules' => htmlspecialchars_decode($activity['activity_rules']),
            'content' => htmlspecialchars_decode($activity['content'])
        ]);
        return $this->fetch();
    }

    /**活动报名
     * @param int $id
     * @return mixed
     * @throws \think\Exception
     */
    public function activity_details($id = 0)
    {
        $activity = EventRegistration::oneActivitys($id);
        $activity['activity_rules'] = htmlspecialchars_decode($activity['activity_rules']);
        $activity['content'] = htmlspecialchars_decode($activity['content']);
        return JsonService::successful($activity);
    }

    /**活动详情
     * @return mixed
     */
    public function form()
    {
        return $this->fetch();
    }

    /**
     * 获取活动列表
     */
    public function activityList($page = 1, $limit = 20, $statu = '')
    {
        $list = EventRegistration::eventRegistrationList($page, $limit, $statu);
        return JsonService::successful($list);
    }

    /**
     *相关活动
     */
    public function relatedActivities($id)
    {
        $list = EventRegistration::getRelatedActivitiesList($id, 1, 3);
        return JsonService::successful($list);
    }

    /**获取活动需要填写的资料
     * @param $id
     */
    public function getActivityEventData($id)
    {
        $event = EventData::eventDataList($id);
        return JsonService::successful($event);
    }

    /**获取活动需要填写的资料
     * @param $id
     */
    public function getActivityEventPrice($id)
    {
        $price = EventPrice::eventPriceList($id);
        return JsonService::successful($price);
    }

    /**
     * 用户报名活动列表
     */
    public function activitySignInList($page = 1, $limit = 20, $navActive = 0)
    {
        $data = EventSignUp::getActivitySignInList($this->uid, $navActive, $page, $limit);
        return JsonService::successful($data);
    }

    /**活动订单详情
     * @param string $order_id
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function activitySignIn($order_id = '')
    {
        if (!$order_id) return JsonService::fail('参数有误');
        $order = EventSignUp::setWhere()->where(['order_id' => $order_id, 'uid' => $this->uid])->find();
        if (!$order) return JsonService::fail('订单不存在');
        if (!$order['activity_id']) return JsonService::fail('订单有误');
        $activity = EventRegistration::where('id', $order['activity_id'])->field('id,title,image,province,city,district,detail,start_time,end_time,signup_start_time,signup_end_time,price')->find();
        if (!$activity) return JsonService::fail('活动不存在');
        $activity = EventRegistration::singleActivity($activity);
        $start_time = date('y/m/d H:i', $activity['start_time']);
        $end_time = date('y/m/d H:i', $activity['end_time']);
        $activity['time'] = $start_time . '~' . $end_time;
        $order['activity'] = $activity;
        $order['pay_time'] = date('y/m/d H:i', $order['pay_time']);
        return JsonService::successful($order);
    }

    /**检测活动状态
     * @param string $order_id
     */
    public function orderStatus($order_id = '')
    {
        if (!$order_id) return JsonService::fail('参数有误');
        $order = EventSignUp::setWhere()->where('order_id', $order_id)->find();
        if (!$order) return JsonService::fail('订单不存在');
        return JsonService::successful($order['status']);
    }
}
