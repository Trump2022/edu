
CREATE TABLE IF NOT EXISTS `eb_spread_url_clicks` (
                                               id BIGINT AUTO_INCREMENT PRIMARY KEY,
                                               url VARCHAR(255) NOT NULL,
    special_id INT DEFAULT 0,
    lb_spread_uid INT DEFAULT 0,
    visitor_id CHAR(36) NOT NULL,
    ip VARCHAR(45) DEFAULT '',
    user_agent VARCHAR(255) DEFAULT '',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_click (special_id, lb_spread_uid, visitor_id),
    KEY `idx_special_uid` (`special_id`, `lb_spread_uid`) USING BTREE,
    KEY `idx_ip_time` (`ip`, `created_at`) USING BTREE,
    KEY `created_at` (`created_at`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 ROW_FORMAT=DYNAMIC COMMENT='推广链接点击记录表';


