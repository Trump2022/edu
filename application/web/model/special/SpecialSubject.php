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

namespace app\web\model\special;

use basic\ModelBasic;
use traits\ModelTrait;

/**专题分类 二级分类
 * Class SpecialSubject
 * @package app\web\model\special
 */
class SpecialSubject extends ModelBasic
{
    use ModelTrait;

    public function children()
    {
        return $this->hasMany('SpecialSubject', 'grade_id', 'id')->where(['is_del' => 0, 'is_show' => 1])->order('sort DESC,id DESC');
    }

    /**获取全部分类
     * @param int $type
     * @return array|false|\PDOStatement|string|\think\Collection
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function pcSpecialCategoryAll($type = 0)
    {
        $model = self::setWhere();
        if ($type == 1) $model = $model->where('grade_id', 0);
        $list = $model->order('sort desc,add_time desc')->field('id,name')->select();
        $list = count($list) > 0 ? $list->toArray() : [];
        return $list;
    }

    /**字段过滤
     * @return SpecialSubject
     */
    public static function setWhere()
    {
        return self::where(['is_del' => 0, 'is_show' => 1]);
    }

    /**获取一级分类下的所以二级分类
     * @param int $grade_id
     */
    public static function subjectId($grade_id = 0)
    {
        return self::setWhere()->where(['grade_id' => $grade_id])->order('sort desc,add_time desc')->column('id');
    }
}
