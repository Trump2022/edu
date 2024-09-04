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

use traits\ModelTrait;
use basic\ModelBasic;
use app\wap\model\special\Special;
use app\wap\model\topic\TestPaper;
use app\wap\model\material\DataDownload;

/**关联表
 * Class Relation
 * @package app\web\model\special
 */
class Relation extends ModelBasic
{
    use ModelTrait;

    /**条件处理
     * @param int $relationship
     * @param int $relationship_id
     * @return Relation
     */
    public static function setWhere($relationship = 0, $relationship_id = 0)
    {
        return self::where(['is_del' => 0, 'relationship' => $relationship, 'relationship_id' => $relationship_id]);
    }

    /**关联资料表
     * @param int $relationship
     * @param int $relationship_id
     * @return array|false|\PDOStatement|string|\think\Collection
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function getRelationDataDownload($relationship = 0, $relationship_id = 0)
    {
        $data = self::alias('r')->join('DataDownload d', 'r.relation_id=d.id')
            ->where(['r.is_del' => 0, 'd.is_show' => 1, 'd.is_del' => 0, 'r.relationship' => $relationship, 'r.relationship_id' => $relationship_id])
            ->field('d.id,d.image,d.money,d.title,d.sales,d.ficti,r.id as rid,r.sort')->order('r.sort DESC,rid DESC')->select();
        $data = count($data) > 0 ? $data->toArray() : [];
        return $data;
    }

}
