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

namespace app\wap\model\special;


use basic\ModelBasic;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\exception\DbException;
use think\model\relation\HasOne;
use traits\ModelTrait;
use think\Db;
use app\wap\model\user\User;
use app\wap\model\special\SpecialTask;

/**专题观看记录
 * Class SpecialWatch
 * @package app\wap\model\special
 */
class SpecialWatch extends ModelBasic
{
    use ModelTrait;

    /**
     * 素材观看时间
     */
    public static function materialViewing($uid, $data)
    {
        $viewing = self::where(['uid' => $uid, 'special_id' => $data['special_id'], 'task_id' => $data['task_id']])->find();
        if ($data['percentage'] < 0) $data['percentage'] = 0;
        if ($data['percentage'] > 100) $data['percentage'] = 100;
        if ($viewing) {
            $dat['viewing_time'] = $data['viewing_time'];
            $dat['percentage'] = $data['percentage'];
            $dat['total'] = $data['total'];
            if ($data['percentage'] > $viewing['percentage']) {
                return self::edit($dat, $viewing['id']);
            } else {
                return true;
            }
        } else {
            $data['uid'] = $uid;
            $data['add_time'] = time();
            return self::set($data);
        }
    }

    /**
     * 查看素材是否观看
     */
    public static function whetherWatch($uid, $special_id = 0, $task_id = 0)
    {
        return self::where(['uid' => $uid, 'special_id' => $special_id, 'task_id' => $task_id])->find();
    }

    /**
     * @param $special_id
     * @return array
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     * @descr: I am on my way to future,where you are there.
     * @date : 9/14/24 4:15 PM
     * @author: Trump
     */
    public static function viewList($special_id = 0)
    {
        return self::where(['special_id' => $special_id])->where('percentage', '>=', 100)->field('uid,special_id, task_id, add_time')->order('add_time desc')->with(['user', 'specialTask'])->select()->toArray();
    }

    /**
     * @return HasOne
     * @descr: I am on my way to future,where you are there.
     * @date : 9/14/24 3:53 PM
     * @author: Trump
     */
    public function user(): HasOne
    {
        return $this->hasOne(User::class, 'uid', 'uid')->field('uid, nickname')->bind([
            'nickname' => 'nickname'
        ]);
    }


    /**
     * @return HasOne
     * @descr: I am on my way to future,where you are there.
     * @date : 9/14/24 3:53 PM
     * @author: Trump
     */
    public function specialTask(): HasOne
    {
        return $this->hasOne(SpecialTask::class, 'id', 'task_id')->field('id, title')->bind([
            'task_title' => 'title'
        ]);
    }
}
