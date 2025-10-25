-- ================================================================
-- 笔记功能数据库升级脚本
-- 创建时间：2025-10-25
-- 功能说明：为视频播放页面添加笔记功能
-- 执行方式：在测试服务器上执行此SQL文件
-- ================================================================

-- 创建课程笔记表
CREATE TABLE IF NOT EXISTS `eb_course_notes` (
  `id` int(11) NOT NULL AUTO_INCREMENT COMMENT '笔记ID',
  `uid` int(11) NOT NULL COMMENT '用户ID',
  `special_id` int(11) NOT NULL DEFAULT 0 COMMENT '专栏ID',
  `task_id` int(11) NOT NULL COMMENT '任务ID（具体视频）',
  `video_time` int(11) DEFAULT 0 COMMENT '视频时间点（秒）',
  `note_content` text COMMENT '笔记内容',
  `add_time` int(11) DEFAULT 0 COMMENT '创建时间（时间戳）',
  `is_del` tinyint(1) DEFAULT 0 COMMENT '是否删除 0=否 1=是',
  PRIMARY KEY (`id`),
  KEY `idx_uid` (`uid`),
  KEY `idx_task_id` (`task_id`),
  KEY `idx_uid_task` (`uid`, `task_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='课程笔记表';

-- ================================================================
-- 风险评估：
-- ✅ 仅新建表，不修改现有结构
-- ✅ 有默认值，数据安全
-- ✅ 独立表，易回滚（DROP TABLE即可）
-- ✅ 添加索引，优化查询性能
-- ================================================================

