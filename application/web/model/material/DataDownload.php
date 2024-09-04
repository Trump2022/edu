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

use think\Db;
use traits\ModelTrait;
use basic\ModelBasic;
use app\web\model\material\DataDownloadCategpry;

/**资料表
 * Class DataDownload
 * @package app\web\model\material
 */
class DataDownload extends ModelBasic
{
    use ModelTrait;

    /**字段过滤
     * @param string $alias
     * @param null $model
     * @return DataDownload
     */
    public static function PreWhere($alias = '', $model = null)
    {
        if (is_null($model)) $model = new self();
        if ($alias) {
            $model = $model->alias($alias);
            $alias .= '.';
        }
        return $model->where([$alias . 'is_show' => 1, $alias . 'status' => 1, $alias . 'is_del' => 0]);
    }

    /**条件处理
     * @param $pid
     * @param $cate_id
     * @param $is_pay
     * @param $salesOrder
     * @param $search
     * @return DataDownload
     */
    public static function setWhere($pid, $cate_id, $is_pay, $salesOrder, $search)
    {
        $model = self::PreWhere();
        if ($cate_id) {
            $model = $model->where(['cate_id' => $cate_id]);
        } else if ($pid && !$cate_id) {
            $cate_ids = DataDownloadCategpry::where('pid', $pid)->column('id');
            $model = $model->where('cate_id', 'in', $cate_ids);
        }
        if ($is_pay != '') $model = $model->where(['pay_type' => $is_pay]);
        if ($search) $model = $model->where('title', 'LIKE', "%$search%");
        $model = $model -> field('id,cate_id,title,image,poster_image,pay_type,money,member_pay_type,member_money,IFNULL(ficti,0) + IFNULL(sales,0) as sales,is_show,is_del,add_time,mer_id,status');
        $baseOrder = '';
        if ($salesOrder) $baseOrder = $salesOrder == 'desc' ? 'sales DESC' : 'sales ASC';//下载量
        if ($baseOrder) $baseOrder .= ', ';
        return $model->order($baseOrder . 'sort DESC, add_time DESC');
    }

    /**列表
     * @param int $page
     * @param int $limit
     * @param $tid
     * @return array
     */
    public static function getDataDownloadExercisesList($page, $limit, $pid, $cate_id, $is_pay, $salesOrder, $search)
    {
        $model = self::setWhere($pid, $cate_id, $is_pay, $salesOrder, $search);
        $data = $model->page($page, $limit)->select();
        $data = count($data) ? $data->toArray() : [];
        $count = self::setWhere($pid, $cate_id, $is_pay, $salesOrder, $search)->count();
        return compact('data', 'count');
    }

    /**
     * 获取单个资料的详细信息
     * @param $uid 用户id
     * @param $id 资料id
     * */
    public static function getOneDataDownload($uid, $id)
    {
        $data = self::PreWhere()->field('id,mer_id,cate_id,title,image,pay_type,money,member_pay_type,member_money,abstract,ficti,sales,is_show')->find($id);
        if (!$data) return self::setErrorInfo('您要查看的资料不存在!');
        if ($data->is_show == 0) return self::setErrorInfo('您要查看的资料已下架!');
        $data->collect = self::getDb('special_relation')->where(['link_id' => $id, 'type' => 1, 'uid' => $uid, 'category' => 1])->count() ? true : false;
        $data->abstract = htmlspecialchars_decode($data->abstract);
        return $data;
    }

    /**讲师名下资料
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function getLecturerDownloadList($mer_id = 0, $page = 1, $limit = 10)
    {
        $data = [];
        $count = 0;
        if ($mer_id) {
            $model = self::PreWhere();
            $model = $model->where(['mer_id' => $mer_id])->field('id,cate_id,title,image,poster_image,pay_type,money,member_pay_type,member_money,IFNULL(ficti,0) + IFNULL(sales,0) as sales,is_show,is_del,add_time,mer_id,status')->order('sort desc,id desc');
            $data = $model->page($page, $limit)->select();
            $data = count($data) ? $data->toArray() : [];
            $count = self::PreWhere()->where(['mer_id' => $mer_id])->count();
        }
        return compact('data', 'count');
    }
    /**获取资料标题
     * @param $id
     * @return float|mixed|string
     */
    public static function getName($id = 0)
    {
        if (!$id) return '';
        return self::where(['id' => $id])->value('title');
    }
}
