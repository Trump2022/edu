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


use app\web\model\special\Special;
use basic\ModelBasic;
use traits\ModelTrait;

/**
 * Class UserBill
 * @package app\web\model\user
 */
class UserBill extends ModelBasic
{
    use ModelTrait;

    protected $insert = ['add_time'];

    protected function setAddTimeAttr()
    {
        return time();
    }

    public static function income($title, $uid, $category, $type, $number, $link_id = 0, $balance = 0, $mark = '', $status = 1, $get_uid = 0)
    {
        $pm = 1;
        return self::set(compact('title', 'uid', 'link_id', 'category', 'type', 'number', 'balance', 'mark', 'status', 'pm', 'get_uid'));
    }

    public static function expend($title, $uid, $category, $type, $number, $link_id = 0, $balance = 0, $mark = '', $status = 1)
    {
        $pm = 0;
        return self::set(compact('title', 'uid', 'link_id', 'category', 'type', 'number', 'balance', 'mark', 'status', 'pm'));
    }

    /**
     * 检查是否记录
     * @param $uid
     * @param $category
     * @param $type
     * @param $number
     * @param $link_id
     * @param $pm
     * @return bool
     */
    public static function billDoesDataExist($uid, $category, $type, $number, $link_id, $pm = 1)
    {
        return self::be(['uid' => $uid, 'category' => $category, 'pm' => $pm, 'type' => $type, 'number' => $number, 'link_id' => $link_id, 'status' => 1]);
    }

    /**
     * 获取金币
     * @param $uid
     * @param string $type
     * @param $pm
     * @return float|int
     */
    public static function getUserGoldCoins($uid, $type = 'recharge', $pm, $n)
    {
        $sum = self::where(['uid' => $uid, 'category' => 'gold_num', 'type' => $type, 'pm' => $pm, 'status' => 1])
            ->where('number', '>', 0)->sum('number');
        if ($n) {
            $refund_sum = self::where(['uid' => $uid, 'category' => 'gold_num', 'type' => 'return', 'pm' => 0, 'status' => 1])
                ->where('number', '>', 0)->sum('number');
        } else {
            $refund_sum = 0;
        }
        return bcsub($sum, $refund_sum, 2);
    }

    public static function getUserGoldConsumption($uid)
    {
        $sum = self::where(['uid' => $uid, 'category' => 'gold_num', 'pm' => 0, 'status' => 1])
            ->where('number', '>', 0)->where('type','in','deduction,return,live_reward')->sum('number');
        return $sum;
    }

    public static function getUserbalance($uid, $pm)
    {
        $sum = self::where(['uid' => $uid, 'category' => 'now_money', 'pm' => $pm, 'status' => 1])
            ->where('number', '>', 0)->sum('number');
        return bcsub($sum, 0, 2);
    }

}
