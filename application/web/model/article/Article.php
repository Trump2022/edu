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

namespace app\web\model\article;

use traits\ModelTrait;
use basic\ModelBasic;

/**新闻model
 * Class Article
 * @package app\web\model
 */
class Article extends ModelBasic
{
    use ModelTrait;

    /**字段过滤
     * @param string $alias
     * @param null $model
     * @return Article
     */
    public static function PreWhere($alias = '', $model = null)
    {
        if (is_null($model)) $model = new self();
        if ($alias) {
            $model->alias($alias);
            $alias .= '.';
        }
        return $model->where(["{$alias}is_show" => 1, "{$alias}hide" => 0]);
    }

    /**Label 字段处理
     * @param $value
     * @return mixed
     */
    public static function getLabelAttr($value)
    {
        return is_string($value) ? json_decode($value, true) : $value;
    }

    /**条件处理
     * @param $where
     * @return Article
     */
    public static function setWhere($where)
    {
        $model = self::PreWhere();
        if (isset($where['cid']) && $where['cid']) {
            $model = $model->where('cid', $where['cid']);
        }
        if (isset($where['search']) && $where['search']) {
            $model = $model->where('title|synopsis', 'LIKE', "%$where[search]%");
        }
        $model = $model->order('sort DESC,add_time DESC');
        return $model;
    }

    /**
     * 活动列表
     */
    public static function getUnifiendList($where)
    {
        $model = self::setWhere($where);
        $data = $model->page((int)$where['page'], (int)$where['limit'])->select();
        $data = count($data) > 0 ? $data->toArray() : [];
        foreach ($data as &$item) {
            $item['add_time'] = date('Y-m-d', $item['add_time']);
            $item['visit'] = (int)$item['visit'];
        }
        $count = self::setWhere($where)->count();
        return compact('data', 'count');
    }

    /**首页新闻资讯
     * @param $where
     * @return array
     */
    public static function get_article_list($where)
    {
        $model = self::PreWhere();
        if ($where['type'] == 1) {
            $model = $model->order('visit DESC');
        } else {
            $model = $model->order('add_time DESC');
        }
        $list = $model->limit($where['limit'])->select();
        $list = count($list) > 0 ? $list->toArray() : [];
        if ($where['type'] == 1) {
            foreach ($list as &$item) {
                $item['add_time'] = date('Y-m-d', $item['add_time']);
                $item['synopsis'] = mb_substr($item['synopsis'], 0, 60, 'utf-8');
            }
        }
        return $list;
    }
}
