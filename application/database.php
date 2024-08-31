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

use think\Env;

return [
    // 数据库类型
    'type'            => Env::get('database.type', 'mysql'),
    // 服务器地址
    'hostname'        => Env::get('database.hostname', 'localhost'),
    // 数据库名
    'database'        => Env::get('database.database', 'lb_edu'),
    // 用户名
    'username'        => Env::get('database.username', 'lb_edu'),
    // 密码
    'password'        => Env::get('database.password', 'ZkPkALFjKbymYKdm'),

    // 端口
    'hostport'        => Env::get('database.hostport', '3306'),

    // 连接dsn
    'dsn'             => '',
    // 数据库连接参数
    'params'          => [],
    // 数据库编码默认采用utf8
    'charset'         => Env::get('database.charset', 'utf8mb4'),

    // 数据库表前缀
    'prefix'          => Env::get('database.prefix', 'eb_'),

    // 数据库调试模式
    'debug'           => Env::get('database.debug', false),

    // 数据库部署方式:0 集中式(单一服务器),1 分布式(主从服务器)
    'deploy'          => 0,
    // 数据库读写是否分离 主从式有效
    'rw_separate'     => false,
    // 读写分离后 主服务器数量
    'master_num'      => 1,
    // 指定从服务器序号
    'slave_no'        => '',
    // 是否严格检查字段是否存在
    'fields_strict'   => false,
    // 数据集返回类型
    'resultset_type'  => '\\think\\Collection',
    // 自动写入时间戳字段
    'auto_timestamp'  => false,
    // 时间字段取出后的默认时间格式
    'datetime_format' => 'Y-m-d H:i:s',
    // 是否需要进行SQL性能分析
    'sql_explain'     => false,
];
