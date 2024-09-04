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

namespace app\web\controller;

use service\JsonService;
use service\SystemConfigService;
use think\Url;
use app\web\model\user\User;
use app\web\model\material\DataDownloadCategpry;
use app\web\model\material\DataDownload;
use app\web\model\material\DataDownloadBuy;
use app\web\model\material\DataDownloadRecords;
use service\UtilService;
use app\web\model\special\SpecialRelation;

/**资料控制器
 * Class Material
 * @package app\web\controller
 */
class Material extends AuthController
{

    /**
     * 白名单
     * */
    public static function WhiteList()
    {
        return [
            'details',
            'material_list',
            'get_material_cate',
            'get_material_list',
            'get_data_details'
        ];
    }

    /**资料列表
     * @param int $pid
     * @param int $cate_id
     * @return mixed
     */
    public function material_list($pid = 0, $cate_id = 0)
    {
        $this->assign([
            'homeLogo' => SystemConfigService::get('home_logo'),
            'pid' => (int)$pid,
            'cate_id' => (int)$cate_id
        ]);
        return $this->fetch('list');
    }

    /**我的资料
     * @return mixed
     */
    public function my_material()
    {
        return $this->fetch();
    }

    /**
     * 资料分类
     */
    public function get_material_cate()
    {
        $cateogry = DataDownloadCategpry::with('children')->where(['is_show' => 1, 'is_del' => 0])->order('sort desc,id desc')->where('pid', 0)->select();
        $children = DataDownloadCategpry::where(['is_show' => 1, 'is_del' => 0])->order('sort desc,id desc')->where('pid', '>', 0)->select();
        $children = count($children) > 0 ? $children->toArray() : [];
        $cateogry = count($cateogry) > 0 ? $cateogry->toArray() : [];
        $data['cateogry'] = $cateogry;
        $data['children'] = $children;
        return JsonService::successful($data);
    }

    /**
     * 资料列表
     */
    public function get_material_list()
    {
        list($page, $limit, $pid, $cate_id, $is_pay, $salesOrder, $search) = UtilService::PostMore([
            ['page', 1],
            ['limit', 10],
            ['pid', 0],
            ['cate_id', 0],
            ['is_pay', ''],
            ['salesOrder', ''],
            ['search', '']
        ], $this->request, true);
        return JsonService::successful(DataDownload::getDataDownloadExercisesList($page, $limit, $pid, $cate_id, $is_pay, $salesOrder, $search));
    }

    /**
     * 资料详情
     * @param $id int 资料id
     * @return
     */
    public function details($id = 0)
    {
        if (!$id) $this->failed('缺少参数,无法访问', Url::build('web/material/material_list'));
        $data = DataDownload::getOneDataDownload($this->uid, $id);
        if ($data === false) return JsonService::fail(DataDownload::getErrorInfo('无法访问'));
        $is_member = isset($this->userInfo['level']) ? $this->userInfo['level'] : 0;
        if (in_array($data['money'], [0, 0.00]) || in_array($data['pay_type'], [PAY_NO_MONEY, PAY_PASSWORD])) {
            $isPay = 1;
        } else {
            $isPay = (!$this->uid || $this->uid == 0) ? false : DataDownloadBuy::PayDataDownload($id, $this->uid);
        }
        $site_url = SystemConfigService::get('site_url') . Url::build('web/material/data_details') . '?id=' . $id . '&spread_uid=' . $this->uid;
        $mobile_url = SystemConfigService::get('site_url') . Url::build('wap/special/data_details') . '?id=' . $id;
        $this->assign([
            'data' => json_encode($data),
            'site_url' => $site_url,
            'is_member' => $is_member,
            'isPay' => $isPay,
            'mobile_url' => $mobile_url,
            'title' => $data['title']
        ]);
        return $this->fetch('detail');
    }


    /**获取下载链接
     * @param $id
     * @param $isPay
     */
    public function get_data_download_link($id, $isPay)
    {
        if (!$isPay) return JsonService::fail('无法获取');
        $data = DataDownload::where('id', $id)->field('link,network_disk_link,network_disk_pwd,type')->find();
        return JsonService::successful($data);
    }

    /**
     * 资料收藏
     * @param $id int 资料id
     * @return json
     */
    public function collect($id = 0)
    {
        if (!$id) return JsonService::fail('缺少参数');
        if (SpecialRelation::SetCollect($this->uid, $id, 1))
            return JsonService::successful('收藏成功');
        else
            return JsonService::fail('收藏失败');
    }

    /**用户下载记录
     * @param $id
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function user_download($id)
    {
        if (!$id) return JsonService::fail('缺少参数');
        $res = DataDownloadRecords::addDataDownloadRecords($id, $this->uid);
        if ($res) {
            DataDownload::where('id', $id)->setInc('sales');
            return JsonService::successful('');
        } else
            return JsonService::fail();
    }

}
