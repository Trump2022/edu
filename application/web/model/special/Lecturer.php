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
use app\web\model\merchant\Merchant;
use app\web\model\merchant\MerchantFollow;

/**讲师 model
 * Class Lecturer
 * @package app\web\model\special
 */
class Lecturer extends ModelBasic
{
    use ModelTrait;

    public static function setWhere()
    {
        return self::where(['is_del' => 0, 'is_show' => 1]);
    }

    /**讲师列表
     * @param int $page
     * @param int $limit
     * @return array
     */
    public static function getLecturerList($uid, $page = 1, $limit = 10)
    {
        $data = self::setWhere()->where('mer_id','>',0)->order('sort DESC,id DESC')->page((int)$page, (int)$limit)
            ->field('id,mer_id,lecturer_name,lecturer_head,label,curriculum,explain,study,sort,is_show,is_del')
            ->select();
        $data = count($data) > 0 ? $data->toArray() : [];
        foreach ($data as $key => &$value) {
            $value['is_follow'] = $uid > 0 ? MerchantFollow::isFollow($uid, $value['mer_id']) : false;
            $value['label'] = json_decode($value['label']);
        }
        $count = self::setWhere()->where('mer_id','>',0)->count();
        return compact('data', 'count');
    }

    /**讲师详情
     * @param int $id
     */
    public static function details($id = 0)
    {
        $details = self::setWhere()->where('id', $id)->find();
        if ($details) {
            $details['label'] = json_decode($details['label']);
            $details['introduction'] = htmlspecialchars_decode($details['introduction']);
            return $details;
        } else {
            return null;
        }
    }

    public static function information($mer_id)
    {
        $lecturer_id = Merchant::where('id', $mer_id)->value('lecturer_id');
        $details = self::where(['is_del' => 0, 'is_show' => 1])->where('id', $lecturer_id)
            ->field('id,lecturer_name,lecturer_head,label,explain')->find();
        if ($details) {
            $details['label'] = json_decode($details['label']);
            return $details;
        } else {
            return null;
        }
    }
}
