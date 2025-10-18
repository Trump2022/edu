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
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\exception\DbException;
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
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     */
    public static function getWebRecommend()
    {
        return self::where(['is_fixed' => 1, 'is_show' => 1])->order('sort desc,add_time desc')
            ->field(['title', 'type', 'link', 'id'])->limit(0, 8)->select();
    }

    /**
     * @param $uid
     * @return array
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
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

    /**
     * 学员PC端获取我的课程
     *
     * @param $is_member
     * @param array $curriculum_short_names
     * @return array
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     * @descr: I am on my way to future,where you are there.
     * @date : 6/9/25 3:54 PM
     * @author: Trump
     */
    public static function getContentRecommendStudentMyCourse($is_member, array $curriculum_short_names = []): array
    {
        // import('');

        // $idsAll = self::getRecommendIdAll();
        $model = self::where(['title' => '我的课程'])
            // ->where('id', 'in', $idsAll)
            ->field(['id', 'title', 'explain', 'type', 'sort', 'show_count']);
        $recommend = $model->order('sort desc,add_time desc')->select();
        $recommend = count($recommend) ? $recommend->toArray() : [];
        foreach ($recommend as &$item) {
            $item['sum_count'] = WebRecommendRelation::getRelationCount($item['id'], (int)$item['type']);
            $item['list'] = WebRecommendRelation::getRelationList($item['id'], (int)$item['type'], $item['show_count'], $is_member, $curriculum_short_names);
            $item['courseIndex'] = 1;
        }
        return compact('recommend');
    }
}
