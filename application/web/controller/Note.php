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
use think\Db;

/**
 * 课程笔记控制器（PC端）
 * Class Note
 * @package app\web\controller
 */
class Note extends AuthController
{
    /**
     * 保存笔记
     * @return json
     */
    public function save()
    {
        $uid = $this->userInfo['uid'];
        $task_id = input('task_id', 0);
        $special_id = input('special_id', 0);
        $video_time = input('video_time', 0);
        $note_content = input('note_content', '');

        // 参数验证
        if (empty($task_id)) {
            return JsonService::fail('任务ID不能为空');
        }
        if (empty($note_content)) {
            return JsonService::fail('笔记内容不能为空');
        }
        if (mb_strlen($note_content) > 500) {
            return JsonService::fail('笔记内容不能超过500字');
        }

        try {
            // 插入笔记
            $data = [
                'uid' => $uid,
                'special_id' => $special_id,
                'task_id' => $task_id,
                'video_time' => intval($video_time),
                'note_content' => trim($note_content),
                'add_time' => time(),
                'is_del' => 0
            ];
            
            $note_id = Db::name('course_notes')->insertGetId($data);
            
            if ($note_id) {
                return JsonService::successful('保存成功', ['note_id' => $note_id]);
            } else {
                return JsonService::fail('保存失败，请重试');
            }
        } catch (\Exception $e) {
            return JsonService::fail('系统错误：' . $e->getMessage());
        }
    }

    /**
     * 获取笔记列表
     * @return json
     */
    public function getList()
    {
        $uid = $this->userInfo['uid'];
        $task_id = input('task_id', 0);

        if (empty($task_id)) {
            return JsonService::fail('任务ID不能为空');
        }

        try {
            // 查询笔记列表
            $list = Db::name('course_notes')
                ->where([
                    'uid' => $uid,
                    'task_id' => $task_id,
                    'is_del' => 0
                ])
                ->field('id, video_time, note_content, add_time')
                ->order('video_time', 'asc')
                ->select();

            // 格式化时间
            foreach ($list as &$item) {
                $item['time_str'] = $this->formatTime($item['video_time']);
                $item['add_time_str'] = date('Y-m-d H:i', $item['add_time']);
            }

            return JsonService::successful('获取成功', $list);
        } catch (\Exception $e) {
            return JsonService::fail('系统错误：' . $e->getMessage());
        }
    }

    /**
     * 删除笔记
     * @return json
     */
    public function delete()
    {
        $uid = $this->userInfo['uid'];
        $note_id = input('note_id', 0);

        if (empty($note_id)) {
            return JsonService::fail('笔记ID不能为空');
        }

        try {
            // 软删除（只能删除自己的笔记）
            $result = Db::name('course_notes')
                ->where([
                    'id' => $note_id,
                    'uid' => $uid
                ])
                ->update(['is_del' => 1]);

            if ($result) {
                return JsonService::successful('删除成功');
            } else {
                return JsonService::fail('删除失败或笔记不存在');
            }
        } catch (\Exception $e) {
            return JsonService::fail('系统错误：' . $e->getMessage());
        }
    }

    /**
     * 格式化时间（秒转为 mm:ss）
     * @param int $seconds
     * @return string
     */
    private function formatTime($seconds)
    {
        $minutes = floor($seconds / 60);
        $secs = $seconds % 60;
        return sprintf('%02d:%02d', $minutes, $secs);
    }
}

