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

use app\web\model\live\LiveStudio;
use basic\ModelBasic;
use traits\ModelTrait;

/**素材表
 * Class SpecialTask
 * @package app\web\model\special
 */
class SpecialTask extends ModelBasic
{
    use ModelTrait;

    /**素材过滤
     * @return SpecialTask
     */
    public static function defaultWhere()
    {
        return self::where(['is_show' => 1, 'is_del' => 0]);
    }

    /**获取单个素材
     * @param $task_id
     * @return array|bool|false|\PDOStatement|string|\think\Model
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function getSpecialTaskOne($task_id)
    {
        if (!$task_id) {
            return false;
        }
        return self::where(['is_show' => 1, 'is_del' => 0])->field('id,special_id,title,is_del,detail,type,is_pay,image,abstract,sort,play_count,is_show,add_time,live_id,is_try,try_time,try_content')->find($task_id);
    }

}
