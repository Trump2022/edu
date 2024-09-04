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
use service\GroupDataService;
use app\web\model\user\Search;
use app\web\model\recommend\WebRecommend;
use app\web\model\merchant\Merchant;
use app\wap\model\store\StoreService;

/**pc端公共接口
 * Class PublicApi
 * @package app\web\controller
 */
class PublicApi
{

    /**
     * pc公共数据
     */
    public function public_data()
    {
        $data['site_name'] = SystemConfigService::get('site_name');//网站名称
        $data['home_logo'] = SystemConfigService::get('home_pc_logo');//pc首页图标
        $data['site_phone'] = SystemConfigService::get('site_phone');//联系电话
        $data['company_address'] = SystemConfigService::get('company_address');//公司地址
        $data['pc_login_diagram'] = SystemConfigService::get('pc_login_diagram');//PC端登录图
        $data['pc_end_bottom_display'] = GroupDataService::getData('pc_end_bottom_display', 4);//pc端底部展示
        $data['pc_keep_on_record'] = GroupDataService::getData('pc_keep_on_record', 4);//pc 底部（公安备案等自定义）
        $customer_service = SystemConfigService::get('customer_service_configuration');//客服配置1=微信客服2=CRMchat客服3=拨打电话
        $data['service_url'] = '';
        $data['kefu_token'] = '';
        if ($customer_service == 2) {
            $data['service_url'] = SystemConfigService::get('service_url');
            $data['kefu_token'] = SystemConfigService::get('kefu_token');
        }
        $data['customer_service'] = $customer_service;//客服配置1=微信客服2=CRMchat客服3=拨打电话
        $data['site_service_phone'] = SystemConfigService::get('site_service_phone');//客服电话
        $data['keep_on_record'] = SystemConfigService::get('keep_on_record');//网站备案信息
        $data['full_name_the_company'] = SystemConfigService::get('full_name_the_company');//公司全称
        return JsonService::successful($data);
    }


    /**获取客服id
     * @param $mer_id
     * @return void
     */
    public function get_kefu_id($mer_id = 0)
    {
        $data['kefu_id'] = StoreService::get_crmeb_random_service_kefu_id($mer_id);
        return JsonService::successful($data);
    }

    /**讲师客服检查
     * @param $mer_id
     * @return void
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function  get_site_service_phone($mer_id = 0)
    {
        $data = [];
        if (!$mer_id) return JsonService::successful($data);
        $merchant = Merchant::where(['id' => $mer_id, 'status' => 1, 'is_del' => 0, 'estate' => 1])->field('id,is_phone_service,service_phone')->find();
        if ($merchant && $merchant['is_phone_service']) {
            $data['customer_service'] = 3;
            $data['site_service_phone'] = $merchant['service_phone'];
        }
        return JsonService::successful($data);
    }

    /**网站统计
     * @return bool|mixed
     */
    public function get_website_statistics()
    {
        return SystemConfigService::get('website_statistics');
    }

    /**
     * 获取热搜词
     */
    public function get_host_search()
    {
        return JsonService::successful(Search::getHostSearch());
    }

    /**用户协议
     * @return mixed
     */
    public function agree()
    {
        $data['title'] = SystemConfigService::get('site_name') . '用户协议';
        $data['content'] = get_config_content('user_agreement');
        return JsonService::successful($data);
    }

    /**获取pc端头部导航
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function get_home_navigation()
    {
        return JsonService::successful(WebRecommend::getWebRecommend());
    }

}
