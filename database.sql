-- 红包领取系统数据库结构
-- 微信支付商家转账到零钱 - 用户领取模式


-- 1. 用户表（管理员和员工）
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE COMMENT '登录账号',
    password VARCHAR(255) NOT NULL COMMENT '密码哈希',
    real_name VARCHAR(50) NOT NULL COMMENT '真实姓名（用于红包发放记录）',
    role ENUM('admin', 'staff') NOT NULL DEFAULT 'staff' COMMENT '角色：admin管理员/staff员工',
    phone VARCHAR(20) COMMENT '手机号',
    email VARCHAR(100) COMMENT '邮箱',
    avatar VARCHAR(255) COMMENT '头像URL',
    status TINYINT NOT NULL DEFAULT 1 COMMENT '状态：0禁用 1启用',
    last_login_at DATETIME COMMENT '最后登录时间',
    last_login_ip VARCHAR(50) COMMENT '最后登录IP',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_role (role),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='系统用户表';

-- 2. 红包模板表
CREATE TABLE redpacket_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL COMMENT '模板名称',
    type ENUM('wechat', 'festive', 'business', 'custom') NOT NULL DEFAULT 'wechat' COMMENT '模板类型',
    preview_image VARCHAR(255) COMMENT '预览图',
    bg_color VARCHAR(20) DEFAULT '#FF6B6B' COMMENT '背景主色',
    bg_gradient VARCHAR(100) COMMENT '背景渐变',
    title_color VARCHAR(20) DEFAULT '#FFFFFF' COMMENT '标题颜色',
    amount_color VARCHAR(20) DEFAULT '#FFD700' COMMENT '金额颜色',
    button_color VARCHAR(20) DEFAULT '#FFD700' COMMENT '按钮颜色',
    button_text VARCHAR(50) DEFAULT '立即领取' COMMENT '按钮文字',
    font_family VARCHAR(50) DEFAULT 'system' COMMENT '字体',
    custom_css TEXT COMMENT '自定义CSS',
    animation_type ENUM('none', 'bounce', 'pulse', 'shake', 'fade') DEFAULT 'bounce' COMMENT '动画效果',
    is_default TINYINT DEFAULT 0 COMMENT '是否默认模板',
    status TINYINT DEFAULT 1 COMMENT '状态：0禁用 1启用',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_type (type),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='红包领取页面模板';

-- 3. 红包表
CREATE TABLE redpackets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(32) NOT NULL UNIQUE COMMENT '红包唯一编码（用于生成二维码）',
    name VARCHAR(100) NOT NULL COMMENT '红包名称',
    description TEXT COMMENT '红包描述',
    transfer_remark VARCHAR(255) DEFAULT '课程推广返现' COMMENT '微信转账备注',
    template_id INT NOT NULL COMMENT '使用的模板ID',
    type ENUM('single', 'batch', 'multiple') NOT NULL DEFAULT 'single' COMMENT '类型：single一次性 batch批量 multiple多次领取',
    total_amount DECIMAL(10,2) NOT NULL COMMENT '总金额',
    amount_per_packet DECIMAL(10,2) NOT NULL COMMENT '单个红包金额',
    total_quantity INT NOT NULL DEFAULT 1 COMMENT '总数量',
    remaining_quantity INT NOT NULL DEFAULT 1 COMMENT '剩余数量',
    max_claims_per_user INT DEFAULT 1 COMMENT '每个用户最多领取次数',
    valid_hours INT DEFAULT 24 COMMENT '有效期（小时）',
    valid_start DATETIME COMMENT '有效期开始',
    valid_end DATETIME COMMENT '有效期结束',
    status ENUM('active', 'paused', 'expired', 'completed', 'cancelled') DEFAULT 'active' COMMENT '状态',
    created_by INT NOT NULL COMMENT '创建人ID',
    qrcode_path VARCHAR(255) COMMENT '二维码图片路径',
    poster_path VARCHAR(255) COMMENT '海报图片路径',
    remark TEXT COMMENT '备注',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_code (code),
    INDEX idx_status (status),
    INDEX idx_type (type),
    INDEX idx_created_by (created_by),
    FOREIGN KEY (template_id) REFERENCES redpacket_templates(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='红包表';

-- 4. 红包领取记录表
CREATE TABLE redpacket_claims (
    id INT AUTO_INCREMENT PRIMARY KEY,
    redpacket_id INT NOT NULL COMMENT '红包ID',
    redpacket_code VARCHAR(32) NOT NULL COMMENT '红包编码',
    openid VARCHAR(100) NOT NULL COMMENT '用户微信openid',
    unionid VARCHAR(100) COMMENT '用户微信unionid',
    nickname VARCHAR(100) COMMENT '用户昵称',
    avatar VARCHAR(255) COMMENT '用户头像',
    amount DECIMAL(10,2) NOT NULL COMMENT '领取金额',
    claim_status ENUM('pending', 'success', 'failed', 'processing', 'cancelled') DEFAULT 'pending' COMMENT '领取状态',
    transfer_bill_no VARCHAR(64) COMMENT '商户转账单号',
    wechat_bill_no VARCHAR(64) COMMENT '微信转账单号',
    transfer_result VARCHAR(50) COMMENT '转账结果',
    fail_reason VARCHAR(255) COMMENT '失败原因',
    notify_data JSON COMMENT '微信回调数据',
    client_ip VARCHAR(50) COMMENT '用户IP',
    user_agent TEXT COMMENT '用户浏览器信息',
    device_type VARCHAR(50) COMMENT '设备类型',
    claimed_at DATETIME COMMENT '领取时间',
    completed_at DATETIME COMMENT '完成时间',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_redpacket_id (redpacket_id),
    INDEX idx_openid (openid),
    INDEX idx_claim_status (claim_status),
    INDEX idx_claimed_at (claimed_at),
    INDEX idx_transfer_bill_no (transfer_bill_no),
    FOREIGN KEY (redpacket_id) REFERENCES redpackets(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='红包领取记录表';

-- 5. 领取日志审计表（详细记录每一步操作）
CREATE TABLE claim_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    claim_id INT COMMENT '关联的领取记录ID',
    redpacket_id INT NOT NULL COMMENT '红包ID',
    openid VARCHAR(100) COMMENT '用户openid',
    action VARCHAR(50) NOT NULL COMMENT '操作类型：view/click/claim/submit/transfer/success/fail',
    action_desc VARCHAR(255) COMMENT '操作描述',
    request_data JSON COMMENT '请求数据',
    response_data JSON COMMENT '响应数据',
    client_ip VARCHAR(50) COMMENT '用户IP',
    user_agent TEXT COMMENT '用户代理',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_claim_id (claim_id),
    INDEX idx_redpacket_id (redpacket_id),
    INDEX idx_action (action),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (claim_id) REFERENCES redpacket_claims(id) ON DELETE SET NULL,
    FOREIGN KEY (redpacket_id) REFERENCES redpackets(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='领取日志审计表';

-- 6. 系统配置表
CREATE TABLE settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    `key` VARCHAR(100) NOT NULL UNIQUE COMMENT '配置键',
    `value` TEXT COMMENT '配置值',
    description VARCHAR(255) COMMENT '配置说明',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='系统配置表';

-- 7. 操作日志表
CREATE TABLE operation_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT COMMENT '操作用户ID',
    action VARCHAR(50) NOT NULL COMMENT '操作类型',
    target_type VARCHAR(50) COMMENT '操作对象类型',
    target_id INT COMMENT '操作对象ID',
    details JSON COMMENT '操作详情',
    ip_address VARCHAR(50) COMMENT 'IP地址',
    user_agent TEXT COMMENT '用户代理',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_action (action),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='操作日志表';

-- 8. 领取记录备注表（追加式）
CREATE TABLE claim_remarks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    claim_id INT NOT NULL COMMENT '领取记录ID',
    content TEXT NOT NULL COMMENT '备注内容',
    created_by INT COMMENT '创建人ID',
    created_by_name VARCHAR(50) COMMENT '创建人姓名',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_claim_id (claim_id),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (claim_id) REFERENCES redpacket_claims(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='领取记录备注表（追加式）';

-- 插入默认管理员账号（密码：admin123）
INSERT INTO users (username, password, real_name, role, status) VALUES 
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '系统管理员', 'admin', 1);

-- 插入默认红包模板
INSERT INTO redpacket_templates (name, type, bg_color, bg_gradient, title_color, amount_color, button_color, button_text, animation_type, is_default) VALUES
('微信经典', 'wechat', '#FF6B6B', 'linear-gradient(180deg, #FF6B6B 0%, #EE5A5A 50%, #D32F2F 100%)', '#333333', '#FF6B6B', '#FFD700', '开', 'bounce', 1),
('喜庆红金', 'festive', '#DC143C', 'linear-gradient(135deg, #DC143C 0%, #B71C1C 50%, #8B0000 100%)', '#8B0000', '#DC143C', '#FFD700', '开', 'pulse', 0),
('商务蓝金', 'business', '#1E3A8A', 'linear-gradient(135deg, #1E3A8A 0%, #3B82F6 50%, #60A5FA 100%)', '#1E3A8A', '#3B82F6', '#FCD34D', '领取', 'fade', 0),
('简约现代', 'custom', '#10B981', 'linear-gradient(135deg, #059669 0%, #10B981 50%, #34D399 100%)', '#065F46', '#059669', '#FEF3C7', '领取', 'shake', 0);

-- 插入默认系统配置
INSERT INTO settings (`key`, `value`, `description`) VALUES
('wechat_appid', '', '微信公众号AppID'),
('wechat_appsecret', '', '微信公众号AppSecret'),
('wechat_mchid', '', '微信支付商户号'),
('wechat_apikey', '', '微信支付API密钥'),
('wechat_apiv3key', '', '微信支付APIv3密钥'),
('wechat_serial_no', '', '微信支付证书序列号'),
('wechat_private_key_path', '', '微信支付私钥证书路径'),
('wechat_public_key_path', '', '微信支付公钥证书路径'),
('site_name', '红包领取系统', '网站名称'),
('site_logo', '', '网站Logo'),
('contact_phone', '', '联系电话'),
('contact_email', '', '联系邮箱'),
('claim_success_message', '红包已发放到您的微信零钱，请查收！', '领取成功提示消息'),
('claim_fail_message', '领取失败，请稍后重试或联系客服', '领取失败提示消息');
