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

use app\web\model\special\StoreOrder;
use basic\ModelBasic;
use service\SystemConfigService;
use traits\ModelTrait;
use app\web\model\user\User;
use app\web\model\special\Special;
use app\web\model\activity\EventRegistration;

/**直播带货
 * Class LiveGoods
 * @package app\web\model\live
 */
class LiveGoods extends ModelBasic
{

    use ModelTrait;


    public static function getLiveGoodsList($where, $is_member = 0, $page = 0, $limit = 10)
    {
        $model = self::alias('g');
        $model = $model->where(['g.is_delete' => 0])->where('g.type', 'in', '0,2');
        if ($where['is_show'] != "" && isset($where['is_show'])) {
            $model = $model->where('g.is_show', $where['is_show']);
        }
        if ($where['live_id'] != 0 && isset($where['live_id'])) {
            $model = $model->where('g.live_id', $where['live_id']);
        }
        $model = $model->field('g.id as live_goods_id,g.special_id, g.sort as gsort, g.fake_sales as gfake_sales,g.type as gfake_type, g.is_show as gis_show, g.sales as gsales');
        $model = $model->order('g.sort desc');
        if ($page && $limit) {
            $list = $model->page((int)$page, (int)$limit)->select();
        } else {
            $list = $model->select();
        }
        $list = count($list) ? $list->toArray() : [];
        foreach ($list as $key => &$item) {
            if ($item['gfake_type'] == 0) {
                $special = Special::PreWhere()->where('id', $item['special_id'])->find();
                if (!$special) {
                    array_splice($list, $key, 1);
                    continue;
                }
                if (!$is_member && $special['is_mer_visible'] == 1) {
                    array_splice($list, $key, 1);
                    continue;
                }

                $item['id'] = $special['id'];
                $item['image'] = $special['image'];
                $item['title'] = $special['title'];
                $item['is_light'] = $special['is_light'];
                $item['money'] = $special['money'];
                $item['pink_end_time'] = $special['pink_end_time'] ? strtotime($special['pink_end_time']) : 0;
                //查看拼团状态,如果已结束关闭拼团
                if ($special['is_pink'] && $special['pink_end_time'] < time()) {
                    self::update(['is_pink' => 0], ['id' => $item['live_goods_id']]);
                    $item['is_pink'] = 0;
                }
            } else if ($item['gfake_type'] == 2) {
                $event = EventRegistration::PreWhere('')->where('id', $item['special_id'])->find();
                if (!$event) {
                    array_splice($list, $key, 1);
                    continue;
                }
                $item['id'] = $event['id'];
                $item['image'] = $event['image'];
                $item['title'] = $event['title'];
                $item['money'] = $event['price'];
            }
        }
        $page++;
        return ['list' => $list, 'page' => $page];
    }


}
