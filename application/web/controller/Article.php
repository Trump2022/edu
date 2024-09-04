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

use app\web\model\article\Article as ArticleModel;
use app\web\model\article\ArticleCategory;
use service\SystemConfigService;
use service\GroupDataService;
use service\JsonService;
use service\UtilService;
use think\Db;
use think\Url;

/**
 * 新闻控制器
 * Class Article
 * @package app\web\controller
 */
class Article extends AuthController
{

    /**
     * 白名单
     */
    public static function WhiteList()
    {
        return [
            'get_article_list',
            'get_article_cate',
            'article_details',
            'news_list',
            'news_detail',
            'news_data'
        ];
    }

    /**
     * 新闻列表
     */
    public function get_article_list()
    {
        $where = UtilService::getMore([
            ['page', 1],
            ['limit', 10],
            ['cid', 0],
            ['search', '']
        ]);
        return JsonService::successful(ArticleModel::getUnifiendList($where));
    }

    /**
     * 新闻分类列表
     */
    public function get_article_cate()
    {
        $category = ArticleCategory::where(['status' => 1, 'is_del' => 0])->order('sort DESC,add_time DESC')->select();
        $category = count($category) > 0 ? $category->toArray() : [];
        return JsonService::successful($category);
    }

    /**
     * 新闻列表
     */
    public function news_list()
    {
        return $this->fetch('list');
    }

    /**
     * 新闻详情
     */
    public function news_detail($id = 0)
    {
        $article = ArticleModel::where(['id' => $id, 'is_show' => 1])->find();
        if (!$article) $this->failed('您查看的文章不存在', Url::build('web/article/news_list'));
        $content = Db::name('articleContent')->where('nid', $article['id'])->value('content');
        $article["content"] = htmlspecialchars_decode($content);
        //增加浏览次数
        $article["visit"] = $article["visit"] + 1;
        $article["add_time"] = date('Y-m-d', $article['add_time']);
        ArticleModel::where('id', $id)->setInc('visit');
        $mobile_url = SystemConfigService::get('site_url') . Url::build('wap/article/news_detail') . '?id=' . $id;
        $this->assign(['id' => $id, 'mobile_url' => $mobile_url,'title' => $article['title'], 'article' => json_encode($article)]);
        return $this->fetch('detail');
    }

    /**
     * 新闻广告
     */
    public function news_data()
    {
        $data['pc_news_list_rotation_chart'] = GroupDataService::getData('pc_news_list_rotation_chart');//pc端新闻列表轮播图
        return JsonService::successful($data);
    }
}
