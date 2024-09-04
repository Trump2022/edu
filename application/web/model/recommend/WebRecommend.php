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

namespace app\web\model\recommend;

use app\web\model\user\User;
use basic\ModelBasic;
use traits\ModelTrait;

/**首页导航及首页推荐表
 * Class WebRecommend
 * @package app\web\model\recommend
 */
class WebRecommend extends ModelBasic
{
    use ModelTrait;

    /**首页导航
     * @return false|\PDOStatement|string|\think\Collection
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function getWebRecommend()
    {
        return self::where(['is_fixed' => 1, 'is_show' => 1])->order('sort desc,add_time desc')
            ->field(['title', 'type', 'link', 'id'])->limit(0, 8)->select();
    }

    /**
     * @param $uid
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function getRecommendIdAll()
    {
        $all = self::where(['is_show' => 1])->field('id,type')->select();
        $idsAll = [];
        foreach ($all as $item) {
            if (WebRecommendRelation::getRelationCount($item['id'], (int)$item['type'])) array_push($idsAll, $item['id']);
        }
        return $idsAll;
    }

    /**
     * 获取主页推荐列表
     *  $page 分页
     *  $limit
     * */
    public static function getContentRecommend($is_member)
    {
        $idsAll = self::getRecommendIdAll();
        $model = self::where(['is_show' => 1])->where('id', 'in', $idsAll)
            ->field(['id', 'title', 'explain', 'type', 'sort', 'show_count']);
        $recommend = $model->order('sort desc,add_time desc')->select();
        $recommend = count($recommend) ? $recommend->toArray() : [];
        foreach ($recommend as &$item) {
            $item['sum_count'] = WebRecommendRelation::getRelationCount($item['id'], (int)$item['type']);
            $item['list'] = WebRecommendRelation::getRelationList($item['id'], (int)$item['type'], $item['show_count'], $is_member);
            $item['courseIndex'] = 1;
        }
        return compact('recommend');
    }

}
