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

use app\web\model\live\LiveStudio;
use app\web\model\special\Lecturer;
use app\web\model\special\Special as SpecialModel;
use app\web\model\special\LearningRecords;
use app\web\model\special\SpecialBuy;
use app\web\model\special\SpecialContent;
use app\web\model\special\SpecialCourse;
use app\web\model\special\SpecialRecord;
use app\web\model\special\SpecialRelation;
use app\web\model\special\SpecialSource;
use app\web\model\special\SpecialSubject;
use app\web\model\special\SpecialTask;
use app\web\model\special\SpecialWatch;
use app\web\model\special\SpecialReply;
use app\web\model\special\SpecialExchange;
use app\web\model\special\SpecialBatch;
use app\web\model\special\StoreOrder;
use app\web\model\material\DataDownload;
use app\web\model\special\Relation;
use app\web\model\user\User;
use service\VodService;
use service\JsonService;
use service\SystemConfigService;
use service\UtilService;
use think\Cookie;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\exception\DbException;
use think\response\Json;
use think\Session;
use think\Url;
use think\Db;
use think\Request;
use think\Config;

/**专题控制器
 * Class Special
 * @package app\web\controller
 */
class Special extends AuthController
{
    /**
     * 白名单
     * */
    public static function WhiteList()
    {
        return [
            'details',
            'single_details',
            'get_special_details',
            'get_special_single_details',
            'get_course_list',
            'play_num',
            'special_cate',
            'get_grade_cate',
            'get_all_special_cate',
            'get_special_list',
            'get_live_special_list',
            'get_cloumn_task',
            'isMember',
            'learningRecords',
            'numberCourses',
            'addLearningRecords',
            'recommended_courses',
            'get_video_playback_credentials',
            'special_reply_list',
            'special_reply_data',
            'special_data_download',
            'teacher_detail',
            'teacher_list',
            'exchange',
            'view_list'
        ];
    }

    /**获取视频上传地址和凭证
     * @param string $videoId
     * @param int $type
     */
    public function get_video_playback_credentials($type = 1, $videoId = '')
    {
        $url = VodService::videoUploadAddressVoucher('', $type, $videoId);
        return JsonService::successful($url);
    }

    /**获取专题价格
     * @param $id
     * @return void
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     */
    public function getSpecialPrice($id = 0)
    {
        if (!$id) return JsonService::fail('缺少参数');
        $special_money = SpecialModel::where('id', $id)->field('money,pay_type,member_money,member_pay_type')->find();
        if (!$special_money) return JsonService::fail('专题价格获取失败');
        return JsonService::successful($special_money);
    }

    /**
     * 专题详情
     * @param $id int 专题id
     * @return
     */
    public function details($id = 0)
    {
        if (!$id) $this->failed('缺少参数,无法访问', Url::build('web/index/index'));
        // ↓↓↓ 新增：记录渠道来源到Session（用于登录时写入数据库）
        $channel = input('channel', '');
        if (!empty($channel)) {
            Session::set('channel_source', $channel);
        }
        // ↑↑↑ 新增结束
        $comment_switch = SystemConfigService::get('special_comment_switch');//专题评论开关
        $special = SpecialModel::getOneSpecial($this->uid, $id);
        if ($special === false) $this->failed(SpecialModel::getErrorInfo('无法访问'), Url::build('web/index/index'));
        $is_member = isset($this->userInfo['level']) ? $this->userInfo['level'] : 0;
        $liveInfo = [];
        if (!isset($special['special'])) $this->failed('专题信息未获得', Url::build('index/index'));
        $specialinfo = $special['special'];
        $specialinfo = is_string($specialinfo) ? json_decode($specialinfo, true) : $specialinfo;
        if (!$is_member && $specialinfo['is_mer_visible'] == 1) $this->failed('专题仅会员可以获得，请充值会员', Url::build('web/my/index'));
        $validity = -1;
        if (in_array($specialinfo['money'], [0, 0.00]) || in_array($specialinfo['pay_type'], [PAY_NO_MONEY, PAY_PASSWORD])) {
            $isPay = 1;
            $validity = 0;
        } else {
            $isPay = (!$this->uid || $this->uid == 0) ? false : SpecialBuy::PaySpecial($id, $this->uid);
            if ($isPay) $validity = SpecialBuy::getSpecialEndTime($id, $this->uid);
        }
        if (in_array($specialinfo['member_money'], [0, 0.00]) || in_array($specialinfo['member_pay_type'], [PAY_NO_MONEY])) {
            if ($validity == -1 && $is_member) {
                $validity = bcsub($this->userInfo['overdue_time'], time(), 0);
            }
        }
        if ($specialinfo['type'] == SPECIAL_LIVE) {
            $liveInfo = LiveStudio::where('special_id', $specialinfo['id'])->find();
            if (!$liveInfo) $this->failed('直播间尚未查到！', Url::build('web/index/index'));
            if ($liveInfo->is_del) $this->failed('直播间已经删除！', Url::build('web/index/index'));
        }
        $count = SpecialModel::learning_records($id);
        $recordCoujnt = processingData(bcadd($specialinfo['fake_sales'], $count, 0));
        $isBatch = SpecialBatch::isBatch($id);//专题是否开启兑换活动
        if ($isPay && $this->uid && $specialinfo['type'] == SPECIAL_COLUMN) SpecialBuy::update_column($id, $this->uid);
        // $mobile_url = SystemConfigService::get('site_url') . Url::build('wap/special/details') . '?id=' . $id;
        $account = Cookie::get('account');
        $pwd = Cookie::get('pwd');
        // var_dump($account, $pwd, 111111);exit;
        $mobile_url = SystemConfigService::get('site_url') . Url::build('wap/login/view') . '?account=' . $account.'&pwd='.$pwd.'&url='.urlencode('/wap/special/details?id='.$id);
        $cookie = Config::get('cookie', '');

        $this->assign([
            'id' => $id,
            'title' => $special['title'],
            'prefix' => $cookie['prefix'],
            'isBatch' => $isBatch,
            'isPay' => $isPay,
            'validity' => $validity,
            'is_member' => $is_member,
            'mobile_url' => $mobile_url,
            'recordCoujnt' => $recordCoujnt,
            'comment_switch' => $comment_switch,
            'liveInfo' => json_encode($liveInfo),
            'special' => json_encode($special)
        ]);

        $lb_spread_uid = input('lb_spread_uid', 0, 'intval');
        if ($lb_spread_uid) {
            $url = request()->url(true);   // 输出：https://example.com/wap/user/detail?id=5&page=2  ← 带完整域名
            Cookie::set('lb_spread_url', $url);
        }

        // ↓↓↓ 记录推广链接点击
        if ($lb_spread_uid) {
            try {
                $visitor_id = Cookie::get('visitor_id');
                if (!$visitor_id) {
                    $visitor_id = $this->getUuid(); // 生成访客ID
                    Cookie::set('visitor_id', $visitor_id, 86400 * 365); // 保存一年
                }

                $data = [
                    'url'           => $url,
                    'special_id'    => $id,
                    'lb_spread_uid' => $lb_spread_uid,
                    'visitor_id'    => $visitor_id,
                    'ip'            => request()->ip(),
                    'user_agent'    => substr(request()->header('user-agent'), 0, 250),
                ];

                // 写入数据库（忽略重复）
                Db::name('eb_spread_url_clicks')
                    ->insertOrIgnore($data);

            } catch (\Throwable $e) {
                // 可以选择性记录日志，不影响主流程
                trace('推广点击记录失败：' . $e->getMessage(), 'error');
            }
        }
        // ↑↑↑ 记录推广链接点击结束

        // === 新增：点击量统计 ===
        $special_total = Db::name('spread_url_clicks')
            ->where('special_id', $id)
            ->count();

        $spread_total = Db::name('spread_url_clicks')
            ->where('special_id', $id)
            ->where('lb_spread_uid', $lb_spread_uid)
            ->count();

        // 分配到模板变量
        $this->assign(compact('special_total', 'spread_total'));

        return $this->fetch('detail');
    }

    /**
     * 生成UUID（兼容无uuid扩展环境）
     * @return string
     */
    private function getUuid()
    {
        // 优先使用系统自带函数
        if (function_exists('uuid_create')) {
            return uuid_create(UUID_TYPE_RANDOM);
        }

        // 否则用随机算法生成
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40); // 版本号 4
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80); // 变体标志 10
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    /**轻专题获取播放记录
     * @param int $id
     */
    public function single_viewing($id = 0)
    {
        $viewing_time = 0;
        if ($this->uid && $id) {
            $watch = SpecialWatch::whetherWatch($this->uid, $id, 0);
            if ($watch) $viewing_time = $watch['viewing_time'];
        }
        return JsonService::successful(['viewing_time' => $viewing_time]);
    }

    /**
     * 轻专题详情
     * @param int $id
     */
    public function single_details($id = 0)
    {
        if (!$id) $this->failed('缺少参数,无法访问', Url::build('index/index'));
        $comment_switch = SystemConfigService::get('special_comment_switch');//专题评论开关
        $is_member = isset($this->userInfo['level']) ? $this->userInfo['level'] : 0;
        $special = SpecialModel::getSingleOneSpecial($this->uid, $id);
        if ($special === false) $this->failed('无法访问', Url::build('index/index'));
        if (!isset($special['special'])) $this->failed('专题信息未获得', Url::build('index/index'));
        $specialinfo = $special['special'];
        $specialinfo = is_string($specialinfo) ? json_decode($specialinfo, true) : $specialinfo;
        if (!$is_member && $specialinfo['is_mer_visible'] == 1) $this->failed('专题仅会员可以获得，请充值会员', Url::build('my/index'));
        $validity = -1;
        if (in_array($specialinfo['money'], [0, 0.00]) || in_array($specialinfo['pay_type'], [PAY_NO_MONEY, PAY_PASSWORD])) {
            $isPay = 1;
            $validity = 0;
        } else {
            $isPay = (!$this->uid || $this->uid == 0) ? false : SpecialBuy::PaySpecial($id, $this->uid);
            if ($isPay) $validity = SpecialBuy::getSpecialEndTime($id, $this->uid);
        }
        if (in_array($specialinfo['member_money'], [0, 0.00]) || in_array($specialinfo['member_pay_type'], [PAY_NO_MONEY])) {
            if ($validity == -1 && $is_member) {
                $validity = bcsub($this->userInfo['overdue_time'], time(), 0);
            }
        }
        $count = SpecialModel::learning_records($id);
        $recordCoujnt = processingData(bcadd($specialinfo['fake_sales'], $count, 0));
        $isBatch = SpecialBatch::isBatch($id);//专题是否开启兑换活动
        // $mobile_url = SystemConfigService::get('site_url') . Url::build('wap/special/single_details') . '?id=' . $id;
        $mobile_url = SystemConfigService::get('site_url') . Url::build('wap/special/single_details') . '?id=' . $id;
        $account = Cookie::get('account');
        $pwd = Cookie::get('pwd');
        // var_dump($account, $pwd, 22222);exit;
        $mobile_url = SystemConfigService::get('site_url') . Url::build('wap/login/view') . '?account=' . $account.'&pwd='.$pwd.'&url='.urlencode('/wap/special/single_details?id='.$id);
        $this->assign([
            'id' => $id,
            'title' => $special['title'],
            'isBatch' => $isBatch,
            'isPay' => $isPay,
            'validity' => $validity,
            'mobile_url' => $mobile_url,
            'is_member' => $is_member,
            'recordCoujnt' => $recordCoujnt,
            'comment_switch' => $comment_switch,
            'special' => json_encode($special)
        ]);
        return $this->fetch('single');
    }

    /**专题下课程数量
     * @param $id
     */
    public function numberCourses($id)
    {
        $special = SpecialModel::PreWhere()->find($id);
        $count = 0;
        if ($special) {
            $count = SpecialModel::numberChapters($special->type, $id);
            if($special['quantity']!=$count) {
                SpecialModel::PreWhere()->where(['id' => $id, 'type' => $special->type])->update(['quantity' => $count]);
            }
        }
        return JsonService::successful($count);
    }


    /**
     * 素材详情
     * @param $task_id 素材ID
     * @return mixed|void
     * @throws \think\Exception
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     */
    public function task_info($task_id = 0, $specialId = 0)
    {
        $title = SpecialModel::PreWhere()->where('id', $specialId)->value('title');
        $this->assign(['title' => $title, 'specialId' => $specialId, 'task_id' => $task_id]);
        return $this->fetch('task');
    }

    /**获取素材内容
     * @throws \think\Exception
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     * special_id 专题ID task_id素材ID
     */
    public function getTaskInfo()
    {
        $data = UtilService::PostMore([
            ['special_id', 0],
            ['task_id', 0]
        ], $this->request);
        $special = SpecialModel::PreWhere()->where('id', $data['special_id'])->field('money,pay_type,member_pay_type,is_light,is_mer_visible,link')->find();
        if (!$special) return JsonService::fail('您查看的专题不存在');
        $user_level = !$this->uid ? 0 : $this->userInfo;
        if ($special['is_light']) {
            if (in_array($special['money'], [0, 0.00]) || $special['pay_type'] == 0 || ($user_level['level'] > 0 && $special['member_pay_type'] == 0)) {
                $isPay = 1;
            } else {
                $isPay = (!$this->uid || $this->uid == 0) ? false : SpecialBuy::PaySpecial($data['special_id'], $this->uid);
            }
            $taskInfo = SpecialModel::getSingleSpecialContent($data['special_id']);
            if ($isPay == false && $taskInfo['singleProfile']['is_try']) {
                unset($taskInfo['content'], $taskInfo['singleProfile']['content']);
            } else if ($isPay == false && !$taskInfo['singleProfile']['is_try']) {
                unset($taskInfo['content'], $taskInfo['singleProfile']['content'], $taskInfo['singleProfile']['videoId'], $taskInfo['singleProfile']['link']);
            }
            if ($isPay) $isSourcePay = true;
            else $isSourcePay = false;
        } else {
            $taskInfo = SpecialTask::defaultWhere()->where('id', $data['task_id'])->find();
            if (!$taskInfo) return JsonService::fail('课程信息不存在无法观看');
            if ($taskInfo['is_show'] == 0) return JsonService::fail('该课程已经下架');
            $isPay = SpecialBuy::PaySpecial($data['special_id'], $this->uid);
            if ($taskInfo['type'] == 1) {
                $content = htmlspecialchars_decode($taskInfo->content ? $taskInfo->content : "");
            } else {
                $special_content = SpecialContent::where('special_id', $data['special_id'])->value("content");
                $content = htmlspecialchars_decode($taskInfo->detail ? $taskInfo->detail : $special_content);
            }
            $taskInfo->content = $content;
            if ($isPay || $special->pay_type == 0 || ($user_level['level'] > 0 && $special->member_pay_type == 0)) {
                $isPay = true;
                $isSourcePay = true;
            } else {
                $isPay = false;
                $special_source = SpecialSource::where(['special_id' => $data['special_id'], 'source_id' => $data['task_id'], 'pay_status' => 0])->find();
                if (!$special_source) {
                    $isSourcePay = false;
                    if ($isPay == false && $taskInfo['is_try']) {
                        unset($taskInfo['content']);
                    } else if ($isPay == false && !$taskInfo['is_try']) {
                        unset($taskInfo['content'], $taskInfo['videoId'], $taskInfo['link']);
                    }
                } else {
                    $isSourcePay = true;
                    $special_source = $special_source->toArray();
                    $taskInfo = SpecialTask::defaultWhere()->where('id', $special_source['source_id'])->find();
                    if (!$taskInfo) return JsonService::fail('该素材无法观看');
                    $taskInfo->content = $content;
                }
            }
        }

        $array['taskInfo'] = $taskInfo ? $taskInfo->toArray() : [];
        $array['specialInfo'] = $special->toArray();
        $array['isPay'] = $isPay;
        $array['isSourcePay'] = $isSourcePay;
        return JsonService::successful($array);
    }


    /**记录专题浏览人
     * @param $id
     * @throws \think\Exception
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     */
    public function addLearningRecords($id)
    {
        $special = SpecialModel::PreWhere()->find($id);
        SpecialModel::where('id', $id)->setInc('browse_count');
        if ($this->uid) {
            SpecialRecord::record($id, $this->uid);
            $time = strtotime('today');
            LearningRecords::recordLearning($id, $this->uid, $time);
            if ($special->lecturer_id) {
                Lecturer::where('id', $special->lecturer_id)->setInc('study');
            }
        }
        return JsonService::successful('ok');
    }

    /**用户专题评价
     * @param int $special_id
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     */
    public function user_comment_special($special_id = 0)
    {
        if (!$special_id) return JsonService::fail('参数错误!');
        if (SpecialReply::be(['special_id' => $special_id, 'uid' => $this->uid, 'is_del' => 0])) return JsonService::fail('该专题已评价!');
        $group = UtilService::postMore([
            ['comment', ''], ['pics', []], ['satisfied_score', 5]
        ]);
        if ($group['comment'] == '') return JsonService::fail('请填写评价内容');
        $group['comment'] = htmlspecialchars(trim($group['comment']));
        if (sensitive_words_filter($group['comment'])) return JsonService::fail('请注意您的用词，谢谢！！');
        if ($group['satisfied_score'] < 1) return JsonService::fail('请为专题满意度评分');
        $group = array_merge($group, [
            'uid' => $this->uid,
            'special_id' => $special_id
        ]);
        SpecialReply::beginTrans();
        $res = SpecialReply::reply($group);
        if (!$res) {
            SpecialReply::rollbackTrans();
            return JsonService::fail('评价失败!');
        }
        SpecialReply::uodateScore($special_id);
        SpecialReply::commitTrans();
        return JsonService::successful('评价成功!');
    }

    /**获取专题评价列表
     * @param string $special_id
     * @param int $page
     * @param int $limit
     * @param string $filter
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     */
    public function special_reply_list($special_id = '', $page = 1, $limit = 8, $filter = 'all')
    {
        if (!$special_id || !is_numeric($special_id)) return JsonService::fail('参数错误!');
        $list = SpecialReply::getSpecialReplyList($special_id, $page, $limit, $filter);
        return JsonService::successful($list);
    }

    /**
     * 评价数据
     */
    public function special_reply_data($special_id = '')
    {
        if (!$special_id || !is_numeric($special_id)) return JsonService::fail('参数错误!');
        $data = SpecialReply::getSpecialReplyData($special_id);
        return JsonService::successful($data);
    }

    /**
     * 专题收藏
     * @param $id int 专题id
     * @return json
     */
    public function collect($id = 0)
    {
        if (!$id) return JsonService::fail('缺少参数');
        if (SpecialRelation::SetCollect($this->uid, $id))
            return JsonService::successful('成功');
        else
            return JsonService::fail('失败');
    }

    /**
     * 获取某个专题的素材列表
     * @return json
     * */
    public function get_course_list()
    {
        list($page, $limit, $special_id) = UtilService::getMore([
            ['page', 1],
            ['limit', 10],
            ['special_id', 0],
        ], null, true);
        $is_member = isset($this->userInfo['level']) ? $this->userInfo['level'] : 0;
        //不登录也能查看
        $task_list = SpecialCourse::getSpecialSourceList($special_id, $limit, $page, $this->uid, $is_member);
        if (!$task_list['list']) return JsonService::successful([]);
        foreach ($task_list['list'] as $k => $v) {
            $task_list['list'][$k]['type_name'] = SPECIAL_TYPE[$v['type']];
            if (!isset($task_list['list'][$k]['special_task'])) {
                $watch = SpecialWatch::whetherWatch($this->uid, $special_id, $v['id']);
                if ($watch && $watch['percentage'] > 100) {
                    $watch['percentage'] = 100;
                }
                $task_list['list'][$k]['watch'] = $watch;
            }
        }
        return JsonService::successful($task_list);
    }

    /**
     * 获取专栏套餐 专栏关联的专题
     */
    public function get_cloumn_task()
    {
        list($page, $limit, $special_id, $source_id) = UtilService::getMore([
            ['page', 1],
            ['limit', 10],
            ['special_id', 0],
            ['source_id', 0],
        ], null, true);
        $is_member = isset($this->userInfo['level']) ? $this->userInfo['level'] : 0;
        $task_list = SpecialCourse::get_cloumn_special($special_id, $source_id, $limit, $page, $this->uid, $is_member);
        if (!$task_list['list']) return JsonService::successful([]);
        foreach ($task_list['list'] as $k => $v) {
            $task_list['list'][$k]['type_name'] = SPECIAL_TYPE[$v['type']];
        }
        return JsonService::successful($task_list);
    }

    /**
     * 播放数量增加
     * @param int $task_id 任务id
     * @return json
     * */
    public function play_num($task_id = 0, $special_id = 0)
    {
        if ($task_id == 0 || $special_id == 0) return JsonService::fail('缺少参数');
        try {
            $add_task_play_count = SpecialTask::bcInc($task_id, 'play_count', 1);
            if ($add_task_play_count) {
                $special_source = SpecialSource::getSpecialSource((int)$special_id, [$task_id]);
                if ($special_source) {
                    SpecialSource::where(['special_id' => $special_id, 'source_id' => $task_id])->setInc('play_count', 1);
                }
                return JsonService::successful('ok');
            } else {
                return JsonService::fail('err');
            }
        } catch (\Exception $e) {
            return JsonService::fail('err');
        }
    }


    /**
     * 购买失败删除订单
     * @param string $orderId 订单id
     * @return json
     * */
    public function del_order($orderId = '')
    {
        if (StoreOrder::where('order_id', $orderId)->update(['is_del' => 1]))
            return JsonService::successful();
        else
            return JsonService::fail();
    }


    /**
     * 专题分类
     * @return mixed
     */
    public function special_cate($cate_id = 0, $subject_id = 0)
    {
        $url = Cookie::get('lb_spread_url');
        $userInfo = $this->userInfo;
        if ( ! $userInfo || $userInfo['identity_type'] != 1 ) {
            if ($url) {
                $this->redirect($url);
            }  else {
                return $this->failed('请求页面不存在!');
            }
        }

        $this->assign([
            'homeLogo' => SystemConfigService::get('home_logo'),
            'cate_id' => (int)$cate_id,
            'subject_id' => (int)$subject_id
        ]);
        return $this->fetch('list');
    }

    /**
     * 获取课程分类
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     */
    public function get_grade_cate()
    {
        $cateogry = SpecialSubject::with(['children'])->where(['is_show' => 1, 'is_del' => 0])->order('sort desc,id desc')->where('grade_id', 0)->select();
        $cateogry = count($cateogry) > 0 ? $cateogry->toArray() : [];
        $is_member = isset($this->userInfo['level']) ? $this->userInfo['level'] : 0;
        foreach ($cateogry as $key => &$item) {
            $cateId = SpecialSubject::subjectId($item['id']);
            $item['list'] = SpecialModel::cate_special_recommen_list($is_member, $cateId);
        }
        return JsonService::successful($cateogry);
    }

    /**获取分类
     * @param int $cate_id
     * @param int $subject_id
     */
    public function get_all_special_cate()
    {
        $cateogry = SpecialSubject::with('children')->where(['is_show' => 1, 'is_del' => 0])->order('sort desc,id desc')->where('grade_id', 0)->select();
        $cateogry = count($cateogry) > 0 ? $cateogry->toArray() : [];
        $children = SpecialSubject::where(['is_show' => 1, 'is_del' => 0])->order('sort desc,id desc')->where('grade_id', '>', 0)->select();
        $children = count($children) > 0 ? $children->toArray() : [];
        $data['cateogry'] = $cateogry;
        $data['children'] = $children;
        return JsonService::successful($data);
    }

    /**
     * 获取所有专题
     * @param int $grade_id 一级分类ID
     * @param int $subject_id 二级分类ID
     * @param string $search
     * @param int $page
     * @param int $limit
     * @param int $type 专题类型
     * @param int $is_pay 专题性质 0=免费 1=付费
     */
    public function get_special_list()
    {
        $data = UtilService::GetMore([
            ['cate_id', 0],
            ['subject_id', 0],
            ['page', 1],
            ['limit', 12],
            ['type', ''],
            ['is_pay', ''],
            ['salesOrder', ''],
            ['scoreOrder', ''],
            ['search', '']
        ], $this->request);
        $is_member = isset($this->userInfo['level']) ? $this->userInfo['level'] : 0;
        return JsonService::successful(SpecialModel::get_special_list($data, $is_member));
    }

    /**
     * 获取所有直播课
     * @param int $grade_id 一级分类ID
     * @param int $subject_id 二级分类ID
     * @param string $search
     * @param int $page
     * @param int $limit
     * @param int $type 专题类型
     * @param int $is_pay 专题性质 0=免费 1=付费 2=密码
     */
    public function get_live_special_list()
    {
        $data = UtilService::GetMore([
            ['cate_id', 0],
            ['subject_id', 0],
            ['page', 1],
            ['limit', 12],
            ['is_pay', ''],
            ['salesOrder', ''],
            ['search', '']
        ], $this->request);
        $data['type'] = 4;
        $is_member = isset($this->userInfo['level']) ? $this->userInfo['level'] : 0;
        return JsonService::successful(SpecialModel::get_live_special_list_all($data, $is_member));
    }

    /**
     * 学习记录
     * @return mixed
     */
    public function record()
    {
        $this->assign(['homeLogo' => SystemConfigService::get('home_logo')]);
        return $this->fetch();
    }

    /**
     * 是否可以播放
     * @param int $task_id 任务id
     * @return string
     * */
    public function get_task_link($task_id = 0, $special_id = 0)
    {
        if (!$special_id || !$task_id) return JsonService::fail('参数错误');
        $special_source = SpecialSource::getSpecialSource($special_id, [$task_id]);
        $tash = $special_source ? $special_source->toArray() : [];
        if (!$tash) {
            return JsonService::fail('您查看的视频已经下架');
        } else {
            return JsonService::successful($tash);
        }
    }

    /**检测用户身份
     * @throws \Exception
     */
    public function isMember()
    {
        $user_level = !$this->uid ? 0 : $this->userInfo;
        $data['is_member'] = isset($user_level['level']) ? $user_level['level'] : 0;
        $data['now_money'] = isset($user_level['now_money']) ? $user_level['now_money'] : 0;
        return JsonService::successful($data);
    }


    /**
     * 储存素材观看时间
     */
    public function viewing()
    {
        $data = UtilService::PostMore([
            ['special_id', 0],
            ['task_id', 0],
            ['viewing_time', 0],
            ['percentage', 0],
            ['total', 0]
        ], $this->request);
        $res = SpecialWatch::materialViewing($this->uid, $data);
        return JsonService::successful($res);
    }

    /**
     * @return null
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     * @descr: I am on my way to future,where you are there.
     * @date : 9/14/24 3:57 PM
     * @author: Trump
     */
    public function view_list()
    {
        list($special_id) = UtilService::getMore([
            ['special_id', 0]
        ], $this->request, true);
        $res = SpecialWatch::viewList($special_id);

        $identity_type = 0;
        $userInfo = $this->userInfo;
        if ($userInfo) {
            $identity_type = $userInfo['identity_type'];
        }

        $time = time();
        foreach ($res as &$item) {
            $item['add_time'] = friendlyDate($item['add_time'],'mohu',$time);
            $item['task_title'] = $item['task_title'] ?? '';

            //当前用户不是员工
            if ($identity_type != 1) {
                $item['nickname'] = substr_replace($item['phone'],'*********',1,9);
            }
        }
        return JsonService::successful($res);
    }

    /**专题推荐课程
     * @param int $id 专题ID
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     */
    public function recommended_courses($id)
    {
        if (!$id) return JsonService::fail('缺少参数');
        $is_member = isset($this->userInfo['level']) ? $this->userInfo['level'] : 0;
        $field = ['browse_count', 'fake_sales', 'IFNULL(browse_count,0) + IFNULL(fake_sales,0) as sales', 'image', 'title', 'type', 'money', 'pink_money', 'is_light', 'light_type', 'is_mer_visible', 'subject_id', 'pay_type', 'label', 'id', 'is_show', 'is_del'];
        $model = SpecialModel::PreWhere()->where('id', '<>', $id);
        if (!$is_member) $model = $model->where(['is_mer_visible' => 0]);
        $specialList = $model->field($field)->order('sales desc,sort desc')
            ->limit(4)->select();
        $specialList = count($specialList) > 0 ? $specialList->toArray() : [];

        foreach ($specialList as $key => &$item) {
            $item['count'] = SpecialModel::numberChapters($item['type'], $item['id']);
            $count = SpecialModel::learning_records($item['id']);
            $item['sales'] = bcadd($item['fake_sales'], $count, 0);
            $item['browse_count'] = processingData($item['sales']);
        }
        $browse = array_column($specialList, 'sales');
        array_multisort($browse, SORT_DESC, $specialList);
        return JsonService::successful($specialList);
    }

    /**专题关联的资料
     * @param int $special_id 专题ID
     */
    public function special_data_download($special_id = 0)
    {
        if (!$special_id) return JsonService::fail('缺少参数,无法访问');
        $data = Relation::getRelationDataDownload(4, $special_id);
        return JsonService::successful($data);
    }


    /**
     * 兑换码提交兑换
     */
    public function exchange_submit()
    {
        list($special_id, $code) = UtilService::PostMore([
            ['special_id', 0],
            ['code', '']
        ], $this->request, true);
        if (!$special_id || !$code) return JsonService::fail('缺少参数');
        $data = SpecialExchange::userExchangeSubmit($this->uid, $special_id, $code);
        if ($data)
            return JsonService::successful($data);
        else
            return JsonService::fail(SpecialExchange::getErrorInfo('兑换失败!'));
    }
}
