<?php
/**
 * 后台管理首页
 */
require_once '../config.php';
checkAuth();

$db = Database::getInstance();
$currentUser = User::getCurrentUser();

// 获取统计数据
$today = date('Y-m-d');
$thisMonth = date('Y-m-01');

// 员工只能看到自己的数据
$userFilter = '';
$userParams = [];
if ($currentUser['role'] !== 'admin') {
    $userFilter = ' AND r.created_by = ?';
    $userParams = [$currentUser['id']];
}

// 今日统计
$todayStats = $db->fetch("SELECT 
    COUNT(*) as claim_count,
    SUM(CASE WHEN c.claim_status = 'success' THEN c.amount ELSE 0 END) as claim_amount
    FROM redpacket_claims c
    LEFT JOIN redpackets r ON c.redpacket_id = r.id
    WHERE DATE(c.created_at) = ?{$userFilter}", array_merge([$today], $userParams));

// 本月统计
$monthStats = $db->fetch("SELECT 
    COUNT(*) as claim_count,
    SUM(CASE WHEN c.claim_status = 'success' THEN c.amount ELSE 0 END) as claim_amount
    FROM redpacket_claims c
    LEFT JOIN redpackets r ON c.redpacket_id = r.id
    WHERE c.created_at >= ?{$userFilter}", array_merge([$thisMonth], $userParams));

// 红包统计 - 已领取个数（按实际领取记录）和剩余个数
$redpacketWhere = $currentUser['role'] !== 'admin' ? 'WHERE r.created_by = ?' : '';
$redpacketParams = $currentUser['role'] !== 'admin' ? [$currentUser['id']] : [];

// 已领取个数 - 按实际领取记录计算
$claimedCountSql = "SELECT COUNT(*) as claimed_count 
    FROM redpacket_claims c 
    LEFT JOIN redpackets r ON c.redpacket_id = r.id 
    {$redpacketWhere}";
$claimedCount = $db->fetch($claimedCountSql, $redpacketParams)['claimed_count'] ?? 0;

// 剩余个数 - 红包表中剩余数量总和
$remainingCountSql = "SELECT SUM(r.remaining_quantity) as remaining_count 
    FROM redpackets r 
    {$redpacketWhere}";
$remainingCount = $db->fetch($remainingCountSql, $redpacketParams)['remaining_count'] ?? 0;

$redpacketStats = [
    'claimed_count' => $claimedCount,
    'remaining_count' => $remainingCount
];

// 最近领取记录
$recentClaims = $db->fetchAll("SELECT c.*, r.name as redpacket_name, u.real_name as sender_name
    FROM redpacket_claims c
    LEFT JOIN redpackets r ON c.redpacket_id = r.id
    LEFT JOIN users u ON r.created_by = u.id
    WHERE 1=1{$userFilter}
    ORDER BY c.created_at DESC
    LIMIT 10", $userParams);

// 获取默认模板ID
$defaultTemplate = $db->fetch("SELECT id FROM redpacket_templates WHERE is_default = 1 LIMIT 1");
$defaultTemplateId = $defaultTemplate['id'] ?? 1;
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理后台 - <?php echo SITE_NAME; ?></title>
    <script src="https://cdn.bootcdn.net/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdn.bootcdn.net/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'PingFang SC', 'Hiragino Sans GB', 'Microsoft YaHei', sans-serif;
            background: #f5f7fa;
            min-height: 100vh;
        }

        /* 侧边栏 */
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            bottom: 0;
            width: 260px;
            background: linear-gradient(180deg, #1a1f37 0%, #252b48 100%);
            color: white;
            z-index: 100;
            overflow-y: auto;
        }

        .sidebar-header {
            padding: 30px 24px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }

        .sidebar-header .logo {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .sidebar-header .logo-icon {
            width: 45px;
            height: 45px;
            background: linear-gradient(135deg, #FF6B6B 0%, #FF8E53 100%);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .sidebar-header .logo-icon svg {
            width: 28px;
            height: 28px;
            fill: white;
        }

        .sidebar-header .logo-text {
            font-size: 20px;
            font-weight: 700;
        }

        .sidebar-header .logo-subtext {
            font-size: 12px;
            color: rgba(255,255,255,0.6);
            margin-top: 2px;
        }

        .nav-menu {
            padding: 20px 0;
        }

        .nav-item {
            display: flex;
            align-items: center;
            padding: 14px 24px;
            color: rgba(255,255,255,0.7);
            text-decoration: none;
            transition: all 0.3s;
            cursor: pointer;
        }

        .nav-item:hover,
        .nav-item.active {
            background: rgba(255,255,255,0.1);
            color: white;
        }

        .nav-item.active {
            border-left: 3px solid #FF6B6B;
        }

        .nav-item svg {
            width: 22px;
            height: 22px;
            margin-right: 12px;
            fill: currentColor;
        }

        .nav-section-title {
            padding: 20px 24px 10px;
            font-size: 12px;
            color: rgba(255,255,255,0.4);
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        /* 主内容区 */
        .main-content {
            margin-left: 260px;
            min-height: 100vh;
        }

        /* 顶部栏 */
        .topbar {
            background: white;
            padding: 16px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            position: sticky;
            top: 0;
            z-index: 99;
        }

        .topbar-title {
            font-size: 20px;
            font-weight: 600;
            color: #333;
        }

        .topbar-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            padding: 8px 16px;
            border-radius: 8px;
            transition: background 0.3s;
        }

        .user-info:hover {
            background: #f5f5f5;
        }

        .user-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 14px;
        }

        .user-name {
            font-size: 14px;
            color: #333;
            font-weight: 500;
        }

        .user-role {
            font-size: 12px;
            color: #999;
        }

        /* 内容区 */
        .content {
            padding: 30px;
        }

        /* 快速创建区域 */
        .quick-create-section {
            background: white;
            border-radius: 16px;
            padding: 24px 30px;
            margin-bottom: 24px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.04);
        }

        .quick-create-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
        }

        .quick-create-header h3 {
            font-size: 16px;
            font-weight: 600;
            color: #333;
        }

        .quick-create-header .tag {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 12px;
        }

        .quick-create-form {
            display: flex;
            gap: 16px;
            align-items: flex-end;
            flex-wrap: wrap;
        }

        .quick-create-form .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .quick-create-form label {
            font-size: 13px;
            color: #666;
            font-weight: 500;
        }

        .quick-create-form input {
            padding: 12px 16px;
            border: 1px solid #e8e8e8;
            border-radius: 10px;
            font-size: 14px;
            width: 160px;
            transition: all 0.3s;
        }

        .quick-create-form input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .quick-create-form .btn-quick {
            padding: 12px 28px;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            background: linear-gradient(135deg, #52c41a 0%, #73d13d 100%);
            color: white;
            transition: all 0.3s;
            height: 46px;
        }

        .quick-create-form .btn-quick:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(82, 196, 26, 0.4);
        }

        .quick-create-form .btn-quick:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }

        /* 统计卡片 */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 24px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.04);
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.08);
        }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 16px;
        }

        .stat-title {
            font-size: 14px;
            color: #666;
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .stat-icon.blue {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .stat-icon.green {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
        }

        .stat-icon.orange {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }

        .stat-icon.purple {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }

        .stat-icon svg {
            width: 26px;
            height: 26px;
            fill: white;
        }

        .stat-value {
            font-size: 32px;
            font-weight: 700;
            color: #333;
            margin-bottom: 8px;
        }

        .stat-change {
            font-size: 13px;
            color: #52c41a;
        }

        /* 面板 */
        .panel {
            background: white;
            border-radius: 16px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.04);
            margin-bottom: 24px;
        }

        .panel-header {
            padding: 20px 24px;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .panel-title {
            font-size: 16px;
            font-weight: 600;
            color: #333;
        }

        .panel-body {
            padding: 0;
        }

        /* 表格 */
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table th,
        .data-table td {
            padding: 16px 24px;
            text-align: left;
            border-bottom: 1px solid #f0f0f0;
        }

        .data-table th {
            font-weight: 600;
            color: #666;
            font-size: 13px;
            background: #fafafa;
        }

        .data-table td {
            font-size: 14px;
            color: #333;
        }

        .data-table tr:hover td {
            background: #fafafa;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }

        .status-badge.success {
            background: #f6ffed;
            color: #52c41a;
        }

        .status-badge.pending {
            background: #fff7e6;
            color: #fa8c16;
        }

        .status-badge.failed {
            background: #fff2f0;
            color: #ff4d4f;
        }

        .amount {
            font-weight: 600;
            color: #ff6b6b;
        }

        /* 弹窗 */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.6);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }

        .modal-overlay.active {
            display: flex;
        }

        .modal-content {
            background: white;
            border-radius: 20px;
            padding: 40px;
            max-width: 500px;
            width: 90%;
            text-align: center;
        }

        .modal-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 24px;
            background: linear-gradient(135deg, #52c41a 0%, #73d13d 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-icon svg {
            width: 45px;
            height: 45px;
            fill: white;
        }

        .modal-title {
            font-size: 24px;
            font-weight: 700;
            color: #333;
            margin-bottom: 12px;
        }

        .modal-message {
            color: #666;
            margin-bottom: 24px;
        }

        .qrcode-result {
            margin: 20px 0;
        }

        .qrcode-result img {
            max-width: 200px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .qrcode-code {
            font-family: monospace;
            background: #f5f5f5;
            padding: 8px 16px;
            border-radius: 6px;
            margin-top: 12px;
            font-size: 14px;
        }

        /* 海报样式 */
        .poster-modal-content {
            max-width: 800px;
            width: 95%;
        }

        .poster-flex-container {
            display: flex;
            gap: 30px;
            align-items: flex-start;
            flex-wrap: wrap;
            justify-content: center;
        }

        .poster-column {
            flex: 1;
            min-width: 320px;
            max-width: 380px;
        }

        .share-column {
            flex: 1;
            min-width: 280px;
            max-width: 320px;
            text-align: center;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 12px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }

        .share-title {
            font-size: 16px;
            font-weight: 600;
            color: #333;
            margin-bottom: 15px;
        }

        .share-tip {
            font-size: 13px;
            color: #666;
            margin-top: 15px;
            line-height: 1.6;
        }

        .poster-container {
            padding: 20px;
            background: #f8f9fa;
            border-radius: 12px;
        }

        .poster {
            background: linear-gradient(135deg, #FF6B6B 0%, #FF8E53 100%);
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 10px 40px rgba(255, 107, 107, 0.3);
        }

        .poster-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 20px;
            background: rgba(255,255,255,0.15);
            backdrop-filter: blur(10px);
            border-radius: 16px 16px 0 0;
        }

        .poster-logo {
            display: flex;
            align-items: center;
            gap: 8px;
            color: white;
            font-weight: 600;
            font-size: 15px;
        }

        .poster-badge {
            background: rgba(255,255,255,0.9);
            color: #FF6B6B;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .poster-body {
            padding: 30px 20px;
            text-align: center;
        }

        .poster-amount-section {
            margin-bottom: 24px;
        }

        .poster-amount-label {
            color: rgba(255,255,255,0.9);
            font-size: 14px;
            margin-bottom: 8px;
        }

        .poster-amount {
            font-size: 48px;
            font-weight: 700;
            color: white;
            text-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 4px;
        }

        .poster-amount-desc {
            color: rgba(255,255,255,0.8);
            font-size: 13px;
        }

        .poster-qrcode-section {
            background: white;
            border-radius: 12px;
            padding: 20px;
            display: inline-block;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }

        .poster-qrcode-section img {
            width: 180px;
            height: 180px;
            border-radius: 8px;
            display: block;
        }

        .poster-qrcode-tip {
            margin-top: 12px;
            color: #666;
            font-size: 13px;
            font-weight: 500;
        }

        .poster-footer {
            background: rgba(255,255,255,0.95);
            padding: 16px 20px;
            text-align: center;
            border-radius: 0 0 16px 16px;
        }

        .poster-info {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-bottom: 10px;
        }

        .poster-info-item {
            display: flex;
            align-items: center;
            gap: 4px;
            font-size: 12px;
            color: #666;
        }

        .poster-slogan {
            font-size: 11px;
            color: #999;
        }

        .btn {
            padding: 12px 32px;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
        }

        .btn-secondary {
            background: #f5f5f5;
            color: #666;
        }

        .btn-secondary:hover {
            background: #e8e8e8;
        }
    </style>
</head>
<body>
    <?php require_once 'includes/sidebar.php'; ?>

    <!-- 主内容区 -->
    <main class="main-content">
        <!-- 顶部栏 -->
        <header class="topbar">
            <h1 class="topbar-title">首页概览</h1>
        </header>

        <!-- 内容区 -->
        <div class="content">
            <!-- 快速创建一次性红包 -->
            <div class="quick-create-section">
                <div class="quick-create-header">
                    <h3>快速创建一次性红包</h3>
                    <span class="tag">快捷</span>
                </div>
                <form class="quick-create-form" id="quickCreateForm">
                    <div class="form-group">
                        <label>红包金额（元）</label>
                        <input type="number" name="amount" step="0.01" min="0.01" placeholder="例如：10.00" required autofocus>
                    </div>
                    <div class="form-group">
                        <label>红包名称（可选）</label>
                        <input type="text" name="name" placeholder="默认：课程推广返现">
                    </div>
                    <div class="form-group">
                        <label>转账备注（可选）</label>
                        <input type="text" name="transfer_remark" placeholder="默认：课程推广返现">
                    </div>
                    <div class="form-group">
                        <label>内部备注 <span style="color: #ff4d4f;">*</span></label>
                        <input type="text" name="remark" placeholder="请输入内部备注，用于后台识别" required>
                    </div>
                    <button type="submit" class="btn-quick" id="quickSubmitBtn">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="white" style="margin-right: 6px; vertical-align: middle;">
                            <path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/>
                        </svg>
                        一键创建
                    </button>
                </form>
            </div>

            <!-- 统计卡片 -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-title">今日领取金额</div>
                            <div class="stat-value">¥<?php echo number_format($todayStats['claim_amount'] ?? 0, 2); ?></div>
                            <div class="stat-change">今日领取 <?php echo $todayStats['claim_count'] ?? 0; ?> 笔</div>
                        </div>
                        <div class="stat-icon blue">
                            <svg viewBox="0 0 24 24"><path d="M11.8 10.9c-2.27-.59-3-1.2-3-2.15 0-1.09 1.01-1.85 2.7-1.85 1.78 0 2.44.85 2.5 2.1h2.21c-.07-1.72-1.12-3.3-3.21-3.81V3h-3v2.16c-1.94.42-3.5 1.68-3.5 3.61 0 2.31 1.91 3.46 4.7 4.13 2.5.6 3 1.48 3 2.41 0 .69-.49 1.79-2.7 1.79-2.06 0-2.87-.92-2.98-2.1h-2.2c.12 2.19 1.76 3.42 3.68 3.83V21h3v-2.15c1.95-.37 3.5-1.5 3.5-3.55 0-2.84-2.43-3.81-4.7-4.4z"/></svg>
                        </div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-title">本月领取金额</div>
                            <div class="stat-value">¥<?php echo number_format($monthStats['claim_amount'] ?? 0, 2); ?></div>
                            <div class="stat-change">本月领取 <?php echo $monthStats['claim_count'] ?? 0; ?> 笔</div>
                        </div>
                        <div class="stat-icon green">
                            <svg viewBox="0 0 24 24"><path d="M16 6l2.29 2.29-4.88 4.88-4-4L2 16.59 3.41 18l6-6 4 4 6.3-6.29L22 12V6z"/></svg>
                        </div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-title">已领取红包</div>
                            <div class="stat-value"><?php echo intval($redpacketStats['claimed_count'] ?? 0); ?></div>
                            <div class="stat-change">累计已领取个数</div>
                        </div>
                        <div class="stat-icon purple">
                            <svg viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.41 1.41L9 19 21 7l-1.41-1.41z"/></svg>
                        </div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-title">剩余红包</div>
                            <div class="stat-value"><?php echo intval($redpacketStats['remaining_count'] ?? 0); ?></div>
                            <div class="stat-change">可领取红包个数</div>
                        </div>
                        <div class="stat-icon orange">
                            <svg viewBox="0 0 24 24"><path d="M20 4H4c-1.11 0-1.99.89-1.99 2L2 18c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V6c0-1.11-.89-2-2-2zm0 14H4v-6h16v6zm0-10H4V6h16v2z"/></svg>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 最近领取记录 -->
            <div class="panel">
                <div class="panel-header">
                    <h2 class="panel-title">最近领取记录</h2>
                    <a href="/admin/claims.php" style="color: #667eea; text-decoration: none; font-size: 14px;">查看全部 →</a>
                </div>
                <div class="panel-body">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>红包名称</th>
                                <?php if ($currentUser['role'] === 'admin'): ?>
                                <th>发放人</th>
                                <?php endif; ?>
                                <th>用户</th>
                                <th>金额</th>
                                <th>状态</th>
                                <th>时间</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentClaims as $claim): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($claim['redpacket_name']); ?></td>
                                <?php if ($currentUser['role'] === 'admin'): ?>
                                <td><?php echo htmlspecialchars($claim['sender_name']); ?></td>
                                <?php endif; ?>
                                <td><?php echo htmlspecialchars($claim['nickname'] ?: substr($claim['openid'], 0, 10) . '...'); ?></td>
                                <td class="amount">¥<?php echo number_format($claim['amount'], 2); ?></td>
                                <td>
                                    <?php
                                    $statusClass = [
                                        'success' => 'success',
                                        'pending' => 'pending',
                                        'processing' => 'pending',
                                        'failed' => 'failed'
                                    ][$claim['claim_status']] ?? 'pending';
                                    $statusText = [
                                        'success' => '成功',
                                        'pending' => '待处理',
                                        'processing' => '处理中',
                                        'failed' => '失败'
                                    ][$claim['claim_status']] ?? $claim['claim_status'];
                                    ?>
                                    <span class="status-badge <?php echo $statusClass; ?>"><?php echo $statusText; ?></span>
                                </td>
                                <td><?php echo date('m-d H:i', strtotime($claim['created_at'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <!-- 海报弹窗 -->
    <div class="modal-overlay" id="posterModal">
        <div class="modal-content poster-modal-content">
            <div class="modal-header" style="justify-content: center;">
                <h3 class="modal-title">红包创建成功</h3>
            </div>
            
            <div class="poster-flex-container">
                <!-- 左侧：海报预览 -->
                <div class="poster-column">
                    <div class="poster-container" id="posterContainer">
                        <div class="poster">
                            <div class="poster-header">
                                <div class="poster-logo">
                                    <svg width="32" height="32" viewBox="0 0 24 24" fill="#FF6B6B">
                                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                                    </svg>
                                    <span><?php echo SITE_NAME; ?></span>
                                </div>
                                <div class="poster-badge">微信红包</div>
                            </div>
                            <div class="poster-body">
                                <div class="poster-amount-section">
                                    <div class="poster-amount-label">扫码领取现金红包</div>
                                    <div class="poster-amount" id="posterAmount">¥0.00</div>
                                    <div class="poster-amount-desc">固定金额</div>
                                </div>
                                <div class="poster-qrcode-section">
                                    <div id="posterQrcode" style="width: 180px; height: 180px; margin: 0 auto;"></div>
                                    <div class="poster-qrcode-tip">微信扫一扫领取</div>
                                </div>
                            </div>
                            <div class="poster-footer">
                                <div class="poster-info">
                                    <div class="poster-info-item">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="#52c41a">
                                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                                        </svg>
                                        <span>安全可信</span>
                                    </div>
                                    <div class="poster-info-item">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="#52c41a">
                                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                                        </svg>
                                        <span>即时到账</span>
                                    </div>
                                    <div class="poster-info-item">
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="#52c41a">
                                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                                        </svg>
                                        <span>官方认证</span>
                                    </div>
                                </div>
                                <div class="poster-slogan"><?php echo SITE_NAME; ?> · 扫码即领 · 秒到零钱</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 右侧：手机分享二维码 -->
                <div class="share-column">
                    <div class="share-title">手机扫码获取海报</div>
                    <div id="mobileShareQrcode" style="padding: 10px; background: white; border-radius: 8px;"></div>
                    <div class="share-tip">
                        员工使用手机微信扫码<br>
                        即可进入海报预览页<br>
                        支持长按发送给用户或保存
                    </div>
                </div>
            </div>

            <div style="display: flex; gap: 12px; margin-top: 25px;">
                <a href="/admin/redpackets.php" class="btn btn-secondary" style="flex: 1; justify-content: center;">查看列表</a>
                <button class="btn btn-primary" onclick="closePosterAndContinue()" style="flex: 1; justify-content: center;">关闭并继续</button>
            </div>
        </div>
    </div>

    <script>
        // 快速创建表单提交
        document.getElementById('quickCreateForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            const btn = document.getElementById('quickSubmitBtn');
            btn.disabled = true;
            btn.innerHTML = '创建中...';

            const formData = new FormData(this);

            // 构建创建红包的参数
            const data = {
                type: 'single',
                name: formData.get('name') || '课程推广返现',
                amount_per_packet: formData.get('amount'),
                total_quantity: 1,
                max_claims_per_user: 1,
                valid_hours: 24,
                template_id: <?php echo $defaultTemplateId; ?>,
                transfer_remark: formData.get('transfer_remark') || '课程推广返现',
                remark: formData.get('remark')
            };

            try {
                const response = await fetch('/api/redpackets.php?action=create', {
                    method: 'POST',
                    body: new URLSearchParams(data)
                });

                const result = await response.json();

                if (result.success) {
                    // 显示海报弹窗
                    const claimUrl = window.location.origin + '/claim.php?code=' + result.data.code;

                    // 清空并生成海报二维码
                    const qrcodeContainer = document.getElementById('posterQrcode');
                    qrcodeContainer.innerHTML = '';
                    new QRCode(qrcodeContainer, {
                        text: claimUrl,
                        width: 180,
                        height: 180,
                        colorDark: "#000000",
                        colorLight: "#ffffff",
                        correctLevel: QRCode.CorrectLevel.H
                    });

                    // 转换 Canvas 为图片
                    setTimeout(() => {
                        const canvas = qrcodeContainer.querySelector('canvas');
                        if (canvas) {
                            const img = document.createElement('img');
                            img.src = canvas.toDataURL('image/png');
                            img.style.width = '180px';
                            img.style.height = '180px';
                            qrcodeContainer.innerHTML = '';
                            qrcodeContainer.appendChild(img);
                        }
                    }, 50);

                    // 生成手机分享二维码（指向 share.php）
                    const shareUrl = window.location.origin + '/share.php?code=' + result.data.code;
                    const mobileShareContainer = document.getElementById('mobileShareQrcode');
                    mobileShareContainer.innerHTML = '';
                    new QRCode(mobileShareContainer, {
                        text: shareUrl,
                        width: 200,
                        height: 200,
                        colorDark: "#000000",
                        colorLight: "#ffffff",
                        correctLevel: QRCode.CorrectLevel.H
                    });

                    document.getElementById('posterAmount').textContent = '¥' + parseFloat(formData.get('amount')).toFixed(2);
                    document.getElementById('posterModal').classList.add('active');

                    // 重置表单
                    this.reset();
                } else {
                    alert('创建失败: ' + (result.message || '未知错误'));
                }
            } catch (error) {
                console.error('创建异常:', error);
                alert('创建失败，请重试');
            } finally {
                btn.disabled = false;
                btn.innerHTML = `
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="white" style="margin-right: 6px; vertical-align: middle;">
                        <path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/>
                    </svg>
                    一键创建
                `;
            }
        });

        // 图片转 Base64 工具函数
        function imgToBase64(url) {
            return new Promise((resolve, reject) => {
                const img = new Image();
                img.crossOrigin = 'Anonymous';
                img.onload = () => {
                    const canvas = document.createElement('canvas');
                    canvas.width = img.width;
                    canvas.height = img.height;
                    const ctx = canvas.getContext('2d');
                    ctx.drawImage(img, 0, 0);
                    resolve(canvas.toDataURL('image/png'));
                };
                img.onerror = reject;
                img.src = url;
            });
        }

        function closePosterAndContinue() {
            document.getElementById('posterModal').classList.remove('active');
            // 聚焦到金额输入框
            document.querySelector('input[name="amount"]').focus();
        }

        // 下载海报
        async function downloadPoster() {
            const poster = document.querySelector('.poster');
            const btn = document.querySelector('#posterModal .btn-primary');
            const qrcodeImg = document.getElementById('posterQrcode');

            // 显示加载状态
            const originalText = btn.textContent;
            btn.textContent = '生成中...';
            btn.disabled = true;

            try {
                // 强制等待图片解码完成
                if (qrcodeImg.src.startsWith('data:')) {
                    if (qrcodeImg.decode) {
                        await qrcodeImg.decode();
                    }
                } else if (!qrcodeImg.complete || qrcodeImg.naturalWidth === 0) {
                    await new Promise((resolve, reject) => {
                        qrcodeImg.onload = resolve;
                        qrcodeImg.onerror = reject;
                        setTimeout(reject, 5000);
                    });
                }

                await new Promise(resolve => setTimeout(resolve, 500));

                // 使用 html2canvas 生成图片
                const canvas = await html2canvas(poster, {
                    scale: 3,
                    useCORS: false,
                    allowTaint: true,
                    backgroundColor: null,
                    logging: false,
                    imageTimeout: 0
                });

                // 转换为图片数据
                const image = canvas.toDataURL('image/png');

                // 创建下载链接
                const link = document.createElement('a');
                link.download = `红包海报_${new Date().getTime()}.png`;
                link.href = image;
                link.click();
            } catch (error) {
                console.error('生成海报失败:', error);
                alert('生成海报失败，请重试');
            } finally {
                btn.textContent = originalText;
                btn.disabled = false;
            }
        }
    </script>
</body>
</html>
