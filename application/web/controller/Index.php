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

use app\web\model\recommend\WebRecommend;
use app\web\model\special\Special;
use app\web\model\material\DataDownload;
use app\web\model\topic\TestPaper;
use app\web\model\user\User;
use service\UtilService;
use service\GroupDataService;
use service\JsonService;
use service\SystemConfigService;
use service\UploadService as Upload;
use app\web\model\recommend\WebRecommendRelation;
use think\Cookie;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\Env;
use think\Exception;
use think\exception\DbException;
use think\Url;

/**首页控制器
 * Class Index
 * @package app\web\controller
 */
class Index extends AuthController
{

    /**
     * 白名单
     * */
    public static function WhiteList()
    {
        return [
            'index',
            'get_content_recommend',
            'user_login',
            'get_unifiend_list',
            'get_recommend_info',
            'more_list',
            'index_data',
            'payment'
        ];
    }

    /**
     * 主页
     * @return mixed
     */
    public function index()
    {
        $arr = explode('/', $this->request->path());
        if (count($arr) > 3) {
            $pathUrl = $arr[0] . '/' . $arr[1] . '/' . $arr[2];
        } else {
            $pathUrl = $this->request->path();
        }
        if ($pathUrl == 'web/index/index' || $pathUrl == '/') {
            $url = Cookie::get('lb_spread_url');
            $this->redirect($url);
        }
        $this->assign([
            'title' => SystemConfigService::get('site_name')
        ]);
        return $this->fetch();
    }

    /**
     * 首页固定数据
     */
    public function index_data()
    {
        $data['pc_rotation_diagram'] = GroupDataService::getData('pc_rotation_diagram');//pc端首页轮播图
        // $data['pc_home_page_advertisement'] = GroupDataService::getData('pc_home_page_advertisement', 3);//pc端首页广告
        $data['pc_home_page_advertisement'] = [];
        $data['pc_home_bottom_img'] = SystemConfigService::get('pc_home_bottom_img');//pc端首页底部图
        $data['pc_home_bottom_figure_link'] = SystemConfigService::get('pc_home_bottom_figure_link');//pc端首页底部图链接
        $data['pc_automatic_recommend'] = SystemConfigService::get('pc_automatic_recommend');//pc端首页课程自动推荐模块
        $data['pc_news_recommend'] = SystemConfigService::get('pc_news_recommend');//pc端新闻推荐模块
        return JsonService::successful($data);
    }

    /**更多
     * @return mixed
     */
    public function more_list()
    {
        return $this->fetch('more');
    }

    /**
     * 首页推荐更多
     * */
    public function get_unifiend_list()
    {
        $where = UtilService::getMore([
            ['page', 1],
            ['limit', 10],
            ['recommend_id', 0],
            ['type', 0]
        ]);
        $is_member = isset($this->userInfo['level']) ? $this->userInfo['level'] : 0;
        return JsonService::successful(WebRecommendRelation::getUnifiendList($where, $is_member));
    }

    /**
     * 上传图片
     * @return \think\response\Json
     */
    public function upload($name = 'file', $link = 'master')
    {
        $res = Upload::image($name, $link);
        $thumbPath = Upload::thumb($res->dir);
        if ($res->status == 200)
            return JsonService::successful('图片上传成功!', ['name' => $res->fileInfo->getSaveName(), 'url' => Upload::pathToUrl($thumbPath)]);
        else
            return JsonService::fail($res->error);
    }

    /**
     * 获取手机号码登录状态
     * */
    public function user_login()
    {
        if ($this->phone) {
            return JsonService::successful('登录中');
        } else {
            return JsonService::fail('请先登录!');
        }
    }

    /**
     * 用户登录状态
     */
    public function login_user()
    {
        if ($this->uid || $this->phone)
            return JsonService::successful('登录中');
        else
            return JsonService::fail('请先登录!');
    }

    /**
     * 获取主页推荐列表
     * @param int $page
     * @param int $limit
     */
    public function get_content_recommend()
    {
        try {
            //获取推荐列表
            $exists_recommend_reids = $this->redisModel->HEXISTS($this->subjectUrl . "web_index_has", "recommend_list");
            if (!$exists_recommend_reids) {
                $is_member = isset($this->userInfo['level']) ? $this->userInfo['level'] : 0;
                $recommend_list = json_encode(WebRecommend::getContentRecommend($is_member));
                $this->redisModel->hset($this->subjectUrl . "web_index_has", "recommend_list", $recommend_list);
                $this->redisModel->expire($this->subjectUrl . "web_index_has", 120);
            } else {
                $recommend_list = $this->redisModel->hget($this->subjectUrl . "web_index_has", "recommend_list");
            }
            return JsonService::successful(json_decode($recommend_list, true));
        } catch (\Exception $e) {
            return JsonService::fail(parent::serRedisPwd($e->getMessage()));
        }
    }

    /**
     * 学员端获取我的课程
     *
     * @return null
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException|Exception
     * @descr: I am on my way to future,where you are there.
     * @date : 6/9/25 4:30 PM
     * @author: Trump
     */
    public function student_my_course()
    {
        header('Access-Control-Allow-Origin: *');
        if ( ! request()->isAjax()) {
            return $this->fetch();
        }
        $school_id = input('school_id', '', 'intval');
        $labels = input('labels', '', 'trim');
        $labels = parse_comma_input($labels);
        $is_member = $this->userInfo['level'] ?? 0;
        $recommend_list = json_encode(WebRecommend::getContentRecommendStudentMyCourse($is_member, $labels, $school_id));
        return JsonService::successful(json_decode($recommend_list, true));
    }


    /**
     * @param int $recommend_id
     * @throws DbException
     */
    public function get_recommend_info($recommend_id = 0)
    {
        return JsonService::successful(WebRecommend::get($recommend_id));
    }

    /**专题/资料支付数据
     * @param int $id 专题、资料、考试ID
     * @param int $type 0=资料 1=专题  5=考试
     */
    public function pay_data($id = 0, $type = 0)
    {
        $is_member = isset($this->userInfo['level']) ? $this->userInfo['level'] : 0;
        if ($type == 1) {
            $data = Special::where('id', $id)->field('title,is_light,money,member_money')->find();
        } else if($type == 5) {
            $data = TestPaper::where('id', $id)->field('title,money,member_money')->find();
        } else{
            $data = DataDownload::where('id', $id)->field('title,money,member_money')->find();
        }
        $data['is_member'] = $is_member;
        return JsonService::successful($data);
    }

    /**
     * 清空缓存
     */
    public function date_empty()
    {
        \think\Session::clear();
        \think\Cookie::clear();
    }

    /**
     * 付款页
     */
    public function payment($id = 0, $type = 0)
    {
        $this->assign(['id' => $id, 'type' => $type]);
        return $this->fetch();
    }
}
