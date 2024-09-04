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

namespace app\web\model\merchant;

use service\SystemConfigService;
use traits\ModelTrait;
use basic\ModelBasic;
use service\PhpSpreadsheetService;

/**
 * 讲师金额明细 model
 * Class UserBill
 * @package app\web\model\merchant
 */
class MerchantBill extends ModelBasic
{
    use ModelTrait;

    protected $insert = ['add_time'];

    protected function setAddTimeAttr()
    {
        return time();
    }


    public static function income($title, $link_id, $mer_id, $category, $type, $number, $balance, $mark = '')
    {
        $pm = 1;
        return self::set(compact('title', 'link_id', 'mer_id', 'category', 'type', 'number', 'balance', 'mark', 'pm'));
    }

    public static function expend($title, $link_id, $mer_id, $category, $type, $number, $balance, $mark = '')
    {
        $pm = 0;
        return self::set(compact('title', 'link_id', 'mer_id', 'category', 'type', 'number', 'balance', 'mark', 'pm'));
    }
}
