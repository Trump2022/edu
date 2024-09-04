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

namespace app\web\model\live;

use basic\ModelBasic;
use traits\ModelTrait;
use think\Db;
use app\web\model\special\LearningRecords;

/**直播信息表
 * Class LiveStudio
 * @package app\web\model\live
 */
class LiveStudio extends ModelBasic
{

    use ModelTrait;

    /**列表获取
     * @param $limit
     * @param $is_member
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function getLiveList($limit, $is_member)
    {
        $model = self::where(['l.is_del' => 0, 's.is_show' => 1, 's.is_del' => 0])->alias('l')
            ->join('special s', 's.id = l.special_id');
        if (!$is_member) $model = $model->where(['s.is_mer_visible' => 0]);
        $list = $model->field(['s.title', 's.image', 's.browse_count', 's.fake_sales', 's.is_mer_visible', 'l.is_play', 's.id', 'l.playback_record_id', 'l.start_play_time'])
            ->limit($limit)->order('s.sort DESC,l.sort DESC,l.add_time DESC')->select();
        $list = count($list) > 0 ? $list->toArray() : [];
        foreach ($list as $key => &$item) {
            if ($item['playback_record_id'] && !$item['is_play']) {
                $item['status'] = 2;//没在直播 有回放
            } else if ($item['is_play']) {
                $item['status'] = 1;//正在直播
            } else if (!$item['playback_record_id'] && !$item['is_play'] && strtotime($item['start_play_time']) > time()) {
                $item['status'] = 3;//等待直播
            } else {
                $item['status'] = 4;//直播结束
            }
            if ($item['start_play_time']) {
                $item['start_play_time'] = date('m-d H:i', strtotime($item['start_play_time']));
            }
            $count = LearningRecords::where(['special_id' => $item['id']])->count();
            $item['records'] = bcadd($count, $item['fake_sales'], 0);
        }
        return $list;
    }

    /**获取单个直播
     * @param $live_one_id
     * @return array|false|\PDOStatement|string|\think\Model
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function getLiveOne($live_one_id)
    {
        return self::where(['l.is_del' => 0, 's.is_show' => 1, 's.is_del' => 0])->alias('l')
            ->join('special s', 's.id = l.special_id')
            ->field(['s.title', 's.image', 'l.is_play', 's.id'])
            ->where('l.is_play', 1)->where('s.id', $live_one_id)
            ->order('l.sort DESC,l.add_time DESC')->find();
    }

    public function getStartPlayTimeAttr($time)
    {
        return $time;//返回create_time原始数据，不进行时间戳转换。
    }

}
