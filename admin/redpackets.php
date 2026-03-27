<?php
/**
 * 红包列表页面
 */
require_once '../config.php';
checkAuth();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>红包列表 - <?php echo SITE_NAME; ?></title>
    <script src="https://cdn.bootcdn.net/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdn.bootcdn.net/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'PingFang SC', 'Hiragino Sans GB', 'Microsoft YaHei', sans-serif;
            background: #f5f7fa;
            min-height: 100vh;
        }

        /* 复用侧边栏样式 */
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

        .nav-menu { padding: 20px 0; }

        .nav-item {
            display: flex;
            align-items: center;
            padding: 14px 24px;
            color: rgba(255,255,255,0.7);
            text-decoration: none;
            transition: all 0.3s;
            cursor: pointer;
        }

        .nav-item:hover, .nav-item.active {
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

        .main-content {
            margin-left: 260px;
            min-height: 100vh;
        }

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

        .user-info:hover { background: #f5f5f5; }

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

        .content { padding: 30px; }

        /* 筛选栏 */
        .filter-bar {
            background: white;
            border-radius: 12px;
            padding: 20px 24px;
            margin-bottom: 24px;
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
            align-items: center;
        }

        .filter-bar input, .filter-bar select {
            padding: 10px 16px;
            border: 1px solid #e8e8e8;
            border-radius: 8px;
            font-size: 14px;
            outline: none;
        }

        .filter-bar input:focus, .filter-bar select:focus {
            border-color: #667eea;
        }

        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            border: none;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }

        .btn-secondary {
            background: #f5f5f5;
            color: #666;
        }

        .btn-secondary:hover { background: #e8e8e8; }

        .btn-danger {
            background: #ff4d4f;
            color: white;
        }

        .btn-danger:hover { background: #ff7875; }

        .btn-warning {
            background: #faad14;
            color: white;
        }

        .btn-warning:hover { background: #ffc53d; }

        .btn-success {
            background: #52c41a;
            color: white;
        }

        .btn-success:hover { background: #73d13d; }

        .btn-sm { padding: 6px 12px; font-size: 12px; }

        /* 表格 */
        .panel {
            background: white;
            border-radius: 16px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.04);
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table th, .data-table td {
            padding: 16px 20px;
            text-align: left;
            border-bottom: 1px solid #f0f0f0;
        }

        .data-table th {
            font-weight: 600;
            color: #666;
            font-size: 13px;
            background: #fafafa;
        }

        .data-table td { font-size: 14px; color: #333; }

        .data-table tr:hover td { background: #fafafa; }

        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }

        .status-badge.active { background: #f6ffed; color: #52c41a; }
        .status-badge.paused { background: #fff7e6; color: #fa8c16; }
        .status-badge.expired { background: #f5f5f5; color: #999; }
        .status-badge.completed { background: #e6f7ff; color: #1890ff; }
        .status-badge.cancelled { background: #fff2f0; color: #ff4d4f; }

        .amount { font-weight: 600; color: #ff6b6b; }

        .actions { display: flex; gap: 8px; flex-wrap: wrap; }

        /* 分页 */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 8px;
            padding: 20px;
        }

        .pagination a, .pagination span {
            padding: 8px 14px;
            border-radius: 8px;
            font-size: 14px;
            text-decoration: none;
            color: #666;
            background: #f5f5f5;
            transition: all 0.3s;
        }

        .pagination a:hover { background: #667eea; color: white; }

        .pagination .current {
            background: #667eea;
            color: white;
        }

        /* 弹窗样式 */
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

        .modal-overlay.active { display: flex; }

        .modal-content {
            background: white;
            border-radius: 16px;
            padding: 30px;
            max-width: 450px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .modal-title {
            font-size: 18px;
            font-weight: 600;
        }

        .modal-close-btn {
            background: none;
            border: none;
            font-size: 24px;
            color: #999;
            cursor: pointer;
            padding: 0;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }

        .modal-close-btn:hover {
            background: #f5f5f5;
            color: #333;
        }

        .qrcode-img {
            max-width: 100%;
            border-radius: 8px;
            margin-bottom: 20px;
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
            background: rgba(255,255,255,0.2);
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

        .modal-footer {
            display: flex;
            gap: 12px;
            margin-top: 20px;
        }

        .modal-footer .btn {
            flex: 1;
            justify-content: center;
        }

        /* 编辑表单 */
        .form-group {
            margin-bottom: 16px;
        }

        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-size: 14px;
            color: #333;
            font-weight: 500;
        }

        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid #e8e8e8;
            border-radius: 8px;
            font-size: 14px;
            outline: none;
            box-sizing: border-box;
        }

        .form-group input:focus,
        .form-group textarea:focus {
            border-color: #667eea;
        }

        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }

        .form-hint {
            font-size: 12px;
            color: #999;
            margin-top: 4px;
        }

        /* 统计信息 */
        .stats-row {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .stat-item {
            background: #f8f9fa;
            padding: 12px 20px;
            border-radius: 8px;
            font-size: 13px;
        }

        .stat-label { color: #666; }
        .stat-value { color: #333; font-weight: 600; margin-left: 8px; }
    </style>
</head>
<body>
    <?php require_once 'includes/sidebar.php'; ?>

    <main class="main-content">
        <header class="topbar">
            <h1 class="topbar-title">红包列表</h1>
        </header>

        <div class="content">
            <!-- 筛选栏 -->
            <div class="filter-bar">
                <input type="text" id="keyword" placeholder="搜索红包名称或编码">
                <select id="status">
                    <option value="">全部状态</option>
                    <option value="active">有效</option>
                    <option value="paused">暂停</option>
                    <option value="expired">过期</option>
                    <option value="completed">已完成</option>
                    <option value="cancelled">已作废</option>
                </select>
                <select id="type">
                    <option value="">全部类型</option>
                    <option value="single">一次性</option>
                    <option value="batch">批量</option>
                    <option value="multiple">多次领取</option>
                </select>
                <button class="btn btn-primary" onclick="loadRedpackets()">搜索</button>
                <button class="btn btn-secondary" onclick="resetFilter()">重置</button>
            </div>

            <!-- 红包列表 -->
            <div class="panel">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>红包编码</th>
                            <th>名称</th>
                            <th>类型</th>
                            <th>金额/个</th>
                            <th>数量</th>
                            <th>已领/剩余</th>
                            <th>状态</th>
                            <th>有效期</th>
                            <th>微信转账备注</th>
                            <th>内部备注</th>
                            <th>创建人</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody id="redpacketList">
                        <!-- 动态加载 -->
                    </tbody>
                </table>

                <div class="pagination" id="pagination">
                    <!-- 动态加载 -->
                </div>
            </div>
        </div>
    </main>

    <!-- 二维码弹窗 -->
    <div class="modal-overlay" id="qrcodeModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">红包二维码</h3>
                <button class="modal-close-btn" onclick="closeQrcodeModal()">&times;</button>
            </div>
            <img src="" alt="二维码" class="qrcode-img" id="qrcodeImg">
            <p style="color: #666; font-size: 14px; text-align: center; margin-bottom: 15px;">微信扫一扫即可领取</p>
            <div class="stats-row" id="qrcodeStats"></div>
            <div class="modal-footer">
                <a href="" class="btn btn-primary" id="downloadQrBtn" target="_blank">下载二维码</a>
                <button class="btn btn-secondary" onclick="closeQrcodeModal()">关闭</button>
            </div>
        </div>
    </div>

    <!-- 海报弹窗 -->
    <div class="modal-overlay" id="posterModal">
        <div class="modal-content poster-modal-content">
            <div class="modal-header">
                <h3 class="modal-title">红包海报</h3>
                <button class="modal-close-btn" onclick="closePosterModal()">&times;</button>
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
                                    <div class="poster-amount-desc" id="posterAmountDesc">金额随机</div>
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
                    <div id="posterMobileShareQrcode" style="padding: 10px; background: white; border-radius: 8px;"></div>
                    <div class="share-tip">
                        员工使用手机微信扫码<br>
                        即可进入海报预览页<br>
                        支持长按发送给用户或保存
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closePosterModal()">关闭</button>
            </div>
        </div>
    </div>

    <!-- 编辑弹窗 -->
    <div class="modal-overlay" id="editModal">
        <div class="modal-content" style="max-width: 600px; max-height: 85vh; overflow-y: auto;">
            <div class="modal-header">
                <h3 class="modal-title">编辑红包</h3>
                <button class="modal-close-btn" onclick="closeEditModal()">&times;</button>
            </div>
            <form id="editForm">
                <input type="hidden" id="editId" name="id">

                <!-- 基本信息 -->
                <div style="margin-bottom: 20px;">
                    <h4 style="font-size: 14px; color: #666; margin-bottom: 12px; border-bottom: 1px solid #eee; padding-bottom: 8px;">基本信息</h4>
                    <div class="form-group">
                        <label>红包名称 <span style="color: #ff4d4f;">*</span></label>
                        <input type="text" id="editName" name="name" required>
                    </div>
                    <div class="form-group">
                        <label>红包描述</label>
                        <textarea id="editDescription" name="description" rows="2"></textarea>
                        <div class="form-hint">用户领取页面显示的描述信息</div>
                    </div>
                </div>

                <!-- 金额设置 -->
                <div style="margin-bottom: 20px;">
                    <h4 style="font-size: 14px; color: #666; margin-bottom: 12px; border-bottom: 1px solid #eee; padding-bottom: 8px;">金额设置</h4>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                        <div class="form-group">
                            <label>单个红包金额 <span style="color: #ff4d4f;">*</span></label>
                            <input type="number" id="editAmount" name="amount_per_packet" step="0.01" min="0.01" required>
                            <div class="form-hint">单位：元</div>
                        </div>
                        <div class="form-group">
                            <label>红包数量 <span style="color: #ff4d4f;">*</span></label>
                            <input type="number" id="editQuantity" name="total_quantity" min="1" required>
                            <div class="form-hint">可领取的总次数</div>
                        </div>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-top: 12px;">
                        <div class="form-group">
                            <label>每人限领次数</label>
                            <input type="number" id="editMaxClaims" name="max_claims_per_user" min="1">
                        </div>
                        <div class="form-group">
                            <label>有效期（小时）</label>
                            <input type="number" id="editValidHours" name="valid_hours" min="0">
                            <div class="form-hint">填0表示长期有效</div>
                        </div>
                    </div>
                </div>

                <!-- 其他设置 -->
                <div style="margin-bottom: 20px;">
                    <h4 style="font-size: 14px; color: #666; margin-bottom: 12px; border-bottom: 1px solid #eee; padding-bottom: 8px;">其他设置</h4>
                    <div class="form-group">
                        <label>微信转账备注</label>
                        <input type="text" id="editTransferRemark" name="transfer_remark">
                        <div class="form-hint">显示在用户微信零钱账单中的备注</div>
                    </div>
                    <div class="form-group">
                        <label>内部备注</label>
                        <input type="text" id="editRemark" name="remark">
                        <div class="form-hint">仅后台可见，用户不可见</div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">保存修改</button>
                    <button type="button" class="btn btn-secondary" onclick="closeEditModal()">取消</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        let currentPage = 1;
        let currentRedpacket = null;

        // 加载红包列表
        async function loadRedpackets(page = 1) {
            currentPage = page;
            const keyword = document.getElementById('keyword').value;
            const status = document.getElementById('status').value;
            const type = document.getElementById('type').value;

            try {
                const response = await fetch(`/api/redpackets.php?action=list&page=${page}&keyword=${encodeURIComponent(keyword)}&status=${status}&type=${type}`);
                const result = await response.json();

                if (result.success) {
                    renderRedpackets(result.data.list);
                    renderPagination(result.data.pagination);
                }
            } catch (error) {
                console.error('加载失败:', error);
            }
        }

        // 渲染红包列表
        function renderRedpackets(list) {
            const tbody = document.getElementById('redpacketList');

            if (list.length === 0) {
                tbody.innerHTML = '<tr><td colspan="12" style="text-align: center; color: #999;">暂无数据</td></tr>';
                return;
            }

            const typeText = { single: '一次性', batch: '批量', multiple: '多次领取' };
            const statusClass = { active: 'active', paused: 'paused', expired: 'expired', completed: 'completed', cancelled: 'cancelled' };
            const statusText = { active: '有效', paused: '暂停', expired: '过期', completed: '已完成', cancelled: '已作废' };

            tbody.innerHTML = list.map(item => {
                const claimedCount = item.claimed_count || 0;
                const remainingCount = item.remaining_quantity || 0;
                const canDelete = (claimedCount === 0 && item.status !== 'cancelled') || item.status === 'cancelled';
                const isMultiple = item.type === 'multiple';
                const isSingleOrBatch = item.type === 'single' || item.type === 'batch';
                const canTerminate = isMultiple && item.status === 'active' && claimedCount > 0 && claimedCount < item.total_quantity;
                const canEdit = item.status === 'active' && claimedCount === 0;
                const hasClaimed = claimedCount > 0;
                const canCancel = !isSingleOrBatch && !hasClaimed && item.status === 'active';

                return `
                <tr>
                    <td><code>${item.code}</code></td>
                    <td>${item.name}</td>
                    <td>${typeText[item.type] || item.type}</td>
                    <td class="amount">¥${parseFloat(item.amount_per_packet).toFixed(2)}</td>
                    <td>${item.total_quantity}</td>
                    <td>${claimedCount} / ${remainingCount}</td>
                    <td><span class="status-badge ${statusClass[item.status]}">${statusText[item.status]}</span></td>
                    <td>${new Date(item.valid_end).toLocaleDateString()}</td>
                    <td>${item.transfer_remark || '-'}</td>
                    <td>${item.remark || '-'}</td>
                    <td>${item.creator_name}</td>
                    <td>
                        <div class="actions">
                            <button class="btn btn-secondary btn-sm" onclick="showQrcode(${item.id}, '${item.qrcode_path}')">二维码</button>
                            <button class="btn btn-success btn-sm" onclick="showPosterDirect(${item.id}, '${item.code}')">海报</button>
                            <button class="btn btn-secondary btn-sm" onclick="viewClaims(${item.id})">记录</button>
                            ${canEdit ? `<a href="/admin/redpacket_create.php?edit=${item.id}" class="btn btn-secondary btn-sm" style="text-decoration: none;">编辑</a>` : ''}
                            ${item.status === 'active' ? `
                                ${canTerminate ?
                                    `<button class="btn btn-warning btn-sm" onclick="terminateRedpacket(${item.id})">终止</button>` :
                                    (canCancel ? `<button class="btn btn-danger btn-sm" onclick="cancelRedpacket(${item.id})">作废</button>` : '')
                                }
                            ` : ''}
                            ${canDelete ? `<button class="btn btn-danger btn-sm" onclick="deleteRedpacket(${item.id})">删除</button>` : ''}
                        </div>
                    </td>
                </tr>
            `}).join('');
        }

        // 渲染分页
        function renderPagination(pagination) {
            const container = document.getElementById('pagination');
            let html = '';

            if (pagination.has_prev) {
                html += `<a href="javascript:;" onclick="loadRedpackets(${pagination.page - 1})">上一页</a>`;
            }

            for (let i = 1; i <= pagination.total_pages; i++) {
                if (i === pagination.page) {
                    html += `<span class="current">${i}</span>`;
                } else {
                    html += `<a href="javascript:;" onclick="loadRedpackets(${i})">${i}</a>`;
                }
            }

            if (pagination.has_next) {
                html += `<a href="javascript:;" onclick="loadRedpackets(${pagination.page + 1})">下一页</a>`;
            }

            container.innerHTML = html;
        }

        // 显示二维码
        async function showQrcode(id, path) {
            document.getElementById('qrcodeImg').src = path;
            document.getElementById('downloadQrBtn').href = path;
            document.getElementById('qrcodeModal').classList.add('active');

            // 加载红包统计信息
            await loadRedpacketStats(id);

            // 保存当前红包信息供海报使用
            try {
                const response = await fetch(`/api/redpackets.php?action=get&id=${id}`);
                const result = await response.json();
                if (result.success) {
                    currentRedpacket = result.data;
                }
            } catch (error) {
                console.error('加载红包信息失败:', error);
            }
        }

        // 加载红包统计
        async function loadRedpacketStats(id) {
            try {
                const response = await fetch(`/api/redpackets.php?action=get&id=${id}`);
                const result = await response.json();

                if (result.success) {
                    const item = result.data;
                    const claimedCount = item.claimed_count || 0;
                    const remainingCount = item.remaining_quantity || 0;
                    const totalAmount = claimedCount * parseFloat(item.amount_per_packet);

                    document.getElementById('qrcodeStats').innerHTML = `
                        <div class="stat-item">
                            <span class="stat-label">已领取:</span>
                            <span class="stat-value">${claimedCount} 个</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">剩余:</span>
                            <span class="stat-value">${remainingCount} 个</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">已发放:</span>
                            <span class="stat-value">¥${totalAmount.toFixed(2)}</span>
                        </div>
                    `;
                }
            } catch (error) {
                console.error('加载统计失败:', error);
            }
        }

        function closeQrcodeModal() {
            document.getElementById('qrcodeModal').classList.remove('active');
        }

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

        // 直接显示海报（从列表点击）
        async function showPosterDirect(id, code) {
            // 加载红包信息
            try {
                const response = await fetch(`/api/redpackets.php?action=get&id=${id}`);
                const result = await response.json();
                if (result.success) {
                    currentRedpacket = result.data;

                    // 动态生成二维码
                    const claimUrl = window.location.origin + '/claim.php?code=' + currentRedpacket.code;
                    const qrcodeContainer = document.getElementById('posterQrcode');
                    qrcodeContainer.innerHTML = '';

                    // 使用 qrcode.js 生成二维码
                    const qr = new QRCode(qrcodeContainer, {
                        text: claimUrl,
                        width: 180,
                        height: 180,
                        colorDark: "#000000",
                        colorLight: "#ffffff",
                        correctLevel: QRCode.CorrectLevel.H
                    });

                    // 关键修复：等待二维码生成后，将其从 Canvas 转换为 Img 标签，提升 html2canvas 兼容性
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

                    // 生成手机分享二维码
                    const shareUrl = window.location.origin + '/share.php?code=' + currentRedpacket.code;
                    const mobileShareContainer = document.getElementById('posterMobileShareQrcode');
                    mobileShareContainer.innerHTML = '';
                    new QRCode(mobileShareContainer, {
                        text: shareUrl,
                        width: 200,
                        height: 200,
                        colorDark: "#000000",
                        colorLight: "#ffffff",
                        correctLevel: QRCode.CorrectLevel.H
                    });

                    document.getElementById('posterAmount').textContent = '¥' + parseFloat(currentRedpacket.amount_per_packet).toFixed(2);
                    document.getElementById('posterAmountDesc').textContent = currentRedpacket.type === 'single' ? '固定金额' : '每人可领';
                }
            } catch (error) {
                console.error('加载红包信息失败:', error);
            }

            document.getElementById('posterModal').classList.add('active');
        }

        // 显示海报弹窗
        async function showPosterModal() {
            if (currentRedpacket) {
                // 动态生成二维码
                const claimUrl = window.location.origin + '/claim.php?code=' + currentRedpacket.code;
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

                // 转换 Canvas 为图片，解决截屏失败问题
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

                // 生成手机分享二维码
                const shareUrl = window.location.origin + '/share.php?code=' + currentRedpacket.code;
                const mobileShareContainer = document.getElementById('posterMobileShareQrcode');
                mobileShareContainer.innerHTML = '';
                new QRCode(mobileShareContainer, {
                    text: shareUrl,
                    width: 200,
                    height: 200,
                    colorDark: "#000000",
                    colorLight: "#ffffff",
                    correctLevel: QRCode.CorrectLevel.H
                });

                document.getElementById('posterAmount').textContent = '¥' + parseFloat(currentRedpacket.amount_per_packet).toFixed(2);
                document.getElementById('posterAmountDesc').textContent = currentRedpacket.type === 'single' ? '固定金额' : '每人可领';
            }

            document.getElementById('posterModal').classList.add('active');
        }

        function closePosterModal() {
            document.getElementById('posterModal').classList.remove('active');
        }

        // 查看领取记录
        function viewClaims(redpacketId) {
            window.location.href = `/admin/claims.php?redpacket_id=${redpacketId}`;
        }

        // 编辑红包
        async function editRedpacket(id) {
            try {
                const response = await fetch(`/api/redpackets.php?action=get&id=${id}`);
                const result = await response.json();

                if (result.success) {
                    currentRedpacket = result.data;

                    // 检查是否已被领取
                    const claimedCount = currentRedpacket.total_quantity - currentRedpacket.remaining_quantity;
                    if (claimedCount > 0) {
                        alert('已被领取的红包不允许编辑');
                        return;
                    }

                    document.getElementById('editId').value = currentRedpacket.id;
                    document.getElementById('editName').value = currentRedpacket.name;
                    document.getElementById('editDescription').value = currentRedpacket.description || '';
                    document.getElementById('editAmount').value = parseFloat(currentRedpacket.amount_per_packet).toFixed(2);
                    document.getElementById('editQuantity').value = currentRedpacket.total_quantity;
                    document.getElementById('editMaxClaims').value = currentRedpacket.max_claims_per_user;
                    document.getElementById('editValidHours').value = currentRedpacket.valid_hours;
                    document.getElementById('editTransferRemark').value = currentRedpacket.transfer_remark || '';
                    document.getElementById('editRemark').value = currentRedpacket.remark || '';

                    // 一次性红包固定数量和限领次数
                    const quantityInput = document.getElementById('editQuantity');
                    const maxClaimsInput = document.getElementById('editMaxClaims');

                    if (currentRedpacket.type === 'single') {
                        quantityInput.value = 1;
                        quantityInput.readOnly = true;
                        quantityInput.style.background = '#f5f5f5';
                        maxClaimsInput.value = 1;
                        maxClaimsInput.readOnly = true;
                        maxClaimsInput.style.background = '#f5f5f5';
                    } else {
                        quantityInput.readOnly = false;
                        quantityInput.style.background = '';
                        maxClaimsInput.readOnly = false;
                        maxClaimsInput.style.background = '';
                    }

                    document.getElementById('editModal').classList.add('active');
                } else {
                    alert(result.message || '加载失败');
                }
            } catch (error) {
                alert('加载失败');
            }
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.remove('active');
            currentRedpacket = null;
        }

        // 提交编辑表单
        document.getElementById('editForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            const formData = new FormData(this);

            try {
                const response = await fetch('/api/redpackets.php?action=update', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    alert('修改成功');
                    closeEditModal();
                    loadRedpackets(currentPage);
                } else {
                    alert(result.message || '修改失败');
                }
            } catch (error) {
                alert('操作失败');
            }
        });

        // 作废红包
        async function cancelRedpacket(id) {
            if (!confirm('确定要作废这个红包吗？作废后无法恢复。')) return;

            try {
                const response = await fetch('/api/redpackets.php?action=cancel', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'id=' + id
                });

                const result = await response.json();

                if (result.success) {
                    alert('作废成功');
                    loadRedpackets(currentPage);
                } else {
                    alert(result.message || '作废失败');
                }
            } catch (error) {
                alert('操作失败');
            }
        }

        // 终止红包（提前结束多次领取）
        async function terminateRedpacket(id) {
            if (!confirm('确定要提前终止这个红包吗？终止后剩余金额将保留，但用户不能再领取。')) return;

            try {
                const response = await fetch('/api/redpackets.php?action=terminate', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'id=' + id
                });

                const result = await response.json();

                if (result.success) {
                    alert('终止成功');
                    loadRedpackets(currentPage);
                } else {
                    alert(result.message || '终止失败');
                }
            } catch (error) {
                alert('操作失败');
            }
        }

        // 删除红包
        async function deleteRedpacket(id) {
            if (!confirm('确定要删除这个红包吗？删除后不可恢复。')) return;

            try {
                const response = await fetch('/api/redpackets.php?action=delete', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'id=' + id
                });

                const result = await response.json();

                if (result.success) {
                    alert('删除成功');
                    loadRedpackets(currentPage);
                } else {
                    alert(result.message || '删除失败');
                }
            } catch (error) {
                alert('操作失败');
            }
        }

        // 重置筛选
        function resetFilter() {
            document.getElementById('keyword').value = '';
            document.getElementById('status').value = '';
            document.getElementById('type').value = '';
            loadRedpackets(1);
        }

        // 退出登录
        async function logout() {
            if (!confirm('确定要退出登录吗？')) return;

            try {
                const response = await fetch('/api/auth.php?action=logout');
                const result = await response.json();

                if (result.success) {
                    window.location.href = '/admin/login.php';
                }
            } catch (error) {
                alert('退出失败');
            }
        }

        // 初始化加载
        loadRedpackets();
    </script>
</body>
</html>