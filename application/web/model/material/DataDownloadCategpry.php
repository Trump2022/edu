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


namespace app\web\model\material;

use traits\ModelTrait;
use basic\ModelBasic;

/**
 * Class DataDownloadCategpry 二级分类
 * @package app\web\model\material
 */
class DataDownloadCategpry extends ModelBasic
{
    use ModelTrait;

    public function children()
    {
        return $this->hasMany('DataDownloadCategpry', 'pid', 'id')->where(['is_del' => 0, 'is_show' => 1])->order('sort DESC,id DESC');
    }

}
