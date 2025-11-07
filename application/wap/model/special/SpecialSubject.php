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
use think\model\relation\HasMany;
use think\model\relation\HasOne;
use traits\ModelTrait;

/**专题分类
 * Class SpecialSubject
 * @package app\wap\model\special
 */
class SpecialSubject extends ModelBasic
{
    use ModelTrait;

    /**
     * @return HasOne
     * @descr: I am on my way to future,where you are there.
     * @date : 2025/11/07 14:37
     * @author: Trump
     */
    public function firstSubject(): HasOne
    {
        return $this->hasOne('SpecialSubject', 'id', 'grade_id')->field('id,name,grade_id')->bind([
            'grade_name' => 'name'
        ]);
    }

    /**获取全部分类
     * @param int $type
     * @return array
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     */
    public static function wapSpecialCategoryAll($type = 0)
    {
        $model = self::where(['is_del' => 0, 'is_show' => 1]);
        if ($type == 1) {
            $model = $model->where('grade_id', 0);
        }
        $list = $model->order('sort desc,add_time desc')->field('id,name')->select();
        $list = count($list) > 0 ? $list->toArray() : [];
        return $list;
    }

    /**获取一级分类下的所以二级分类
     * @param int $grade_id
     */
    public static function subjectId($grade_id = 0)
    {
        return self::where(['is_del' => 0, 'is_show' => 1, 'grade_id' => $grade_id])->order('sort desc,add_time desc')->column('id');
    }
}
