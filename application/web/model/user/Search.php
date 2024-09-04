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

namespace app\web\model\user;

use app\web\model\special\Special;
use service\SystemConfigService;
use think\cache\driver\Redis;
use traits\ModelTrait;
use basic\ModelBasic;
use think\Db;

/**搜索表
 * Class Search
 * @package app\wap\model
 */
class Search extends ModelBasic
{
    use ModelTrait;

    public static function getHostSearch()
    {
        return self::order('add_time desc')->column('name');
    }

}
