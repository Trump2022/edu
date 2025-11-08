
ALTER TABLE `eb_user` ADD `is_crm_user` TINYINT UNSIGNED DEFAULT 0 COMMENT '是否是CRM系统的用户(员工),1-是,0-否' AFTER `is_h5user`;
ALTER TABLE `eb_user` ADD `is_crm_customer` TINYINT UNSIGNED DEFAULT 0 COMMENT '是否是CRM系统的客户,1-是,0-否' AFTER `is_crm_user`;

ALTER TABLE `eb_user` ADD  identity_type TINYINT(1) NOT NULL DEFAULT 0 COMMENT '身份类型:0-普通用户,1-员工,2-学员' AFTER `is_h5user`;
ALTER TABLE `eb_user` DROP `is_crm_user`;
ALTER TABLE `eb_user` DROP `is_crm_customer`;


