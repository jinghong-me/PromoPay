<?php
/**
 * 员工红包发放统计明细表（仅管理员）
 */
require_once '../config.php';
checkAdmin();

$db = Database::getInstance();

// 获取筛选参数
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-d');
$userId = intval($_GET['user_id'] ?? 0);

// 获取所有用户列表（用于筛选，包含管理员和员工）
$staffList = $db->fetchAll("SELECT id, real_name, role FROM users WHERE status = 1 ORDER BY role DESC, real_name");

// 构建查询条件
$where = ['r.created_at >= ? AND r.created_at <= ?'];
$params = [$startDate . ' 00:00:00', $endDate . ' 23:59:59'];

if ($userId) {
    $where[] = 'r.created_by = ?';
    $params[] = $userId;
}

$whereClause = implode(' AND ', $where);

// 获取统计汇总
$summarySql = "SELECT
    COUNT(DISTINCT r.id) as total_redpackets,
    SUM(r.total_quantity) as total_quantity,
    SUM(r.total_quantity - r.remaining_quantity) as claimed_quantity,
    SUM(r.total_amount) as total_amount,
    SUM((r.total_quantity - r.remaining_quantity) * r.amount_per_packet) as claimed_amount,
    COUNT(DISTINCT CASE WHEN r.status = 'active' THEN r.id END) as active_count,
    COUNT(DISTINCT CASE WHEN r.status = 'completed' THEN r.id END) as completed_count,
    COUNT(DISTINCT CASE WHEN r.status = 'cancelled' THEN r.id END) as cancelled_count
FROM redpackets r
WHERE {$whereClause}";
$summary = $db->fetch($summarySql, $params);

// 获取用户明细统计（包含管理员和员工）
$staffStatsSql = "SELECT
    u.id as user_id,
    u.real_name,
    u.username,
    u.role,
    COUNT(DISTINCT r.id) as redpacket_count,
    SUM(r.total_quantity) as total_quantity,
    SUM(r.total_quantity - r.remaining_quantity) as claimed_quantity,
    ROUND(SUM(r.total_quantity - r.remaining_quantity) / SUM(r.total_quantity) * 100, 2) as claim_rate,
    SUM(r.total_amount) as total_amount,
    SUM((r.total_quantity - r.remaining_quantity) * r.amount_per_packet) as claimed_amount,
    COUNT(DISTINCT CASE WHEN r.status = 'active' THEN r.id END) as active_count,
    COUNT(DISTINCT CASE WHEN r.status = 'completed' THEN r.id END) as completed_count,
    COUNT(DISTINCT CASE WHEN r.status = 'cancelled' THEN r.id END) as cancelled_count
FROM users u
LEFT JOIN redpackets r ON r.created_by = u.id AND r.created_at >= ? AND r.created_at <= ?
GROUP BY u.id, u.real_name, u.username, u.role
ORDER BY claimed_amount DESC";
$staffStats = $db->fetchAll($staffStatsSql, [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);

// 获取详细红包列表（如果筛选了具体员工）
$redpacketDetails = [];
if ($userId) {
    $detailSql = "SELECT
        r.id,
        r.name,
        r.code,
        r.type,
        r.amount_per_packet,
        r.total_quantity,
        r.remaining_quantity,
        r.total_amount,
        r.status,
        r.created_at,
        r.transfer_remark,
        r.remark,
        (SELECT COUNT(*) FROM redpacket_claims WHERE redpacket_id = r.id AND claim_status = 'success') as actual_claimed,
        (SELECT SUM(amount) FROM redpacket_claims WHERE redpacket_id = r.id AND claim_status = 'success') as actual_claimed_amount
    FROM redpackets r
    WHERE {$whereClause}
    ORDER BY r.created_at DESC";
    $redpacketDetails = $db->fetchAll($detailSql, $params);
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>用户发放统计 - <?php echo SITE_NAME; ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'PingFang SC', 'Hiragino Sans GB', 'Microsoft YaHei', sans-serif;
            background: #f5f7fa;
            min-height: 100vh;
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

        /* 统计卡片 */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 24px;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }

        .stat-value {
            font-size: 28px;
            font-weight: 700;
            color: #333;
            margin-bottom: 4px;
        }

        .stat-label {
            font-size: 13px;
            color: #999;
        }

        .stat-change {
            font-size: 12px;
            color: #52c41a;
            margin-top: 4px;
        }

        /* 表格 */
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

        .data-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table th, .data-table td {
            padding: 14px 20px;
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

        .data-table tr.clickable { cursor: pointer; }
        .data-table tr.clickable:hover { background: #f0f5ff; }

        .amount { font-weight: 600; color: #ff6b6b; }
        .amount.success { color: #52c41a; }

        .status-badge {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }

        .status-badge.active { background: #f6ffed; color: #52c41a; }
        .status-badge.completed { background: #e6f7ff; color: #1890ff; }
        .status-badge.cancelled { background: #fff2f0; color: #ff4d4f; }

        .progress-bar {
            width: 100px;
            height: 6px;
            background: #f0f0f0;
            border-radius: 3px;
            overflow: hidden;
            display: inline-block;
            vertical-align: middle;
            margin-right: 8px;
        }

        .progress-bar .fill {
            height: 100%;
            background: linear-gradient(90deg, #52c41a, #73d13d);
            border-radius: 3px;
        }

        .rate-text {
            font-size: 12px;
            color: #666;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }
    </style>
</head>
<body>
    <?php require_once 'includes/sidebar.php'; ?>

    <main class="main-content">
        <header class="topbar">
            <h1 class="topbar-title">用户红包发放统计</h1>
        </header>

        <div class="content">
            <!-- 筛选栏 -->
            <div class="filter-bar">
                <input type="date" id="startDate" value="<?php echo $startDate; ?>">
                <span>至</span>
                <input type="date" id="endDate" value="<?php echo $endDate; ?>">
                <select id="userId">
                    <option value="">全部用户</option>
                    <?php foreach ($staffList as $staff): ?>
                    <option value="<?php echo $staff['id']; ?>" <?php echo $userId == $staff['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($staff['real_name']) . ' (' . ($staff['role'] === 'admin' ? '管理员' : '员工') . ')'; ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <button class="btn btn-primary" onclick="search()">查询</button>
                <button class="btn btn-secondary" onclick="exportData()">导出Excel</button>
                <a href="/admin/users.php" class="btn btn-secondary">返回用户管理</a>
            </div>

            <!-- 汇总统计 -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value"><?php echo intval($summary['total_redpackets'] ?? 0); ?></div>
                    <div class="stat-label">红包总数</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo intval($summary['total_quantity'] ?? 0); ?></div>
                    <div class="stat-label">总发放次数</div>
                    <div class="stat-change">已领 <?php echo intval($summary['claimed_quantity'] ?? 0); ?> 次</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">¥<?php echo number_format($summary['total_amount'] ?? 0, 2); ?></div>
                    <div class="stat-label">总发放金额</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value success">¥<?php echo number_format($summary['claimed_amount'] ?? 0, 2); ?></div>
                    <div class="stat-label">已领取金额</div>
                    <div class="stat-change">
                        <?php
                        $claimRate = ($summary['total_quantity'] ?? 0) > 0
                            ? round(($summary['claimed_quantity'] ?? 0) / $summary['total_quantity'] * 100, 1)
                            : 0;
                        echo $claimRate; ?>% 领取率
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo intval($summary['active_count'] ?? 0); ?></div>
                    <div class="stat-label">有效红包</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo intval($summary['completed_count'] ?? 0); ?></div>
                    <div class="stat-label">已完成</div>
                </div>
            </div>

            <!-- 用户发放明细统计 -->
            <div class="panel">
                <div class="panel-header">
                    <h2 class="panel-title">用户发放明细</h2>
                </div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>用户姓名</th>
                            <th>角色</th>
                            <th>红包数量</th>
                            <th>发放次数</th>
                            <th>已领次数</th>
                            <th>领取率</th>
                            <th>发放金额</th>
                            <th>已领金额</th>
                            <th>有效/完成/作废</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($staffStats)): ?>
                        <tr>
                            <td colspan="9" class="empty-state">暂无数据</td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($staffStats as $staff): ?>
                        <tr class="clickable" onclick="viewStaffDetail(<?php echo $staff['user_id']; ?>)">
                            <td>
                                <strong><?php echo htmlspecialchars($staff['real_name']); ?></strong>
                                <br><small style="color:#999"><?php echo $staff['username']; ?></small>
                            </td>
                            <td>
                                <span class="status-badge <?php echo $staff['role'] === 'admin' ? 'completed' : 'active'; ?>">
                                    <?php echo $staff['role'] === 'admin' ? '管理员' : '员工'; ?>
                                </span>
                            </td>
                            <td><?php echo intval($staff['redpacket_count']); ?></td>
                            <td><?php echo intval($staff['total_quantity']); ?></td>
                            <td><?php echo intval($staff['claimed_quantity']); ?></td>
                            <td>
                                <div class="progress-bar">
                                    <div class="fill" style="width: <?php echo min($staff['claim_rate'], 100); ?>%"></div>
                                </div>
                                <span class="rate-text"><?php echo $staff['claim_rate']; ?>%</span>
                            </td>
                            <td class="amount">¥<?php echo number_format($staff['total_amount'] ?? 0, 2); ?></td>
                            <td class="amount success">¥<?php echo number_format($staff['claimed_amount'] ?? 0, 2); ?></td>
                            <td>
                                <span class="status-badge active"><?php echo intval($staff['active_count']); ?></span>
                                <span class="status-badge completed"><?php echo intval($staff['completed_count']); ?></span>
                                <span class="status-badge cancelled"><?php echo intval($staff['cancelled_count']); ?></span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- 红包详细列表（当筛选了具体员工时显示） -->
            <?php if ($userId && !empty($redpacketDetails)): ?>
            <div class="panel">
                <div class="panel-header">
                    <h2 class="panel-title">红包明细列表</h2>
                </div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>红包名称</th>
                            <th>类型</th>
                            <th>金额</th>
                            <th>发放/已领</th>
                            <th>状态</th>
                            <th>创建时间</th>
                            <th>转账备注</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($redpacketDetails as $rp): ?>
                        <tr>
                            <td><?php echo $rp['id']; ?></td>
                            <td><?php echo htmlspecialchars($rp['name']); ?></td>
                            <td><?php echo $rp['type'] === 'single' ? '一次性' : ($rp['type'] === 'batch' ? '批量' : '多次'); ?></td>
                            <td class="amount">¥<?php echo number_format($rp['amount_per_packet'], 2); ?></td>
                            <td><?php echo $rp['actual_claimed']; ?> / <?php echo $rp['total_quantity']; ?></td>
                            <td>
                                <span class="status-badge <?php echo $rp['status']; ?>">
                                    <?php echo $rp['status'] === 'active' ? '有效' : ($rp['status'] === 'completed' ? '完成' : '作废'); ?>
                                </span>
                            </td>
                            <td><?php echo date('Y-m-d H:i', strtotime($rp['created_at'])); ?></td>
                            <td><?php echo htmlspecialchars($rp['transfer_remark'] ?: '-'); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </main>

    <script>
        function search() {
            const startDate = document.getElementById('startDate').value;
            const endDate = document.getElementById('endDate').value;
            const userId = document.getElementById('userId').value;

            let url = `?start_date=${startDate}&end_date=${endDate}`;
            if (userId) {
                url += `&user_id=${userId}`;
            }
            window.location.href = url;
        }

        function viewStaffDetail(userId) {
            const startDate = document.getElementById('startDate').value;
            const endDate = document.getElementById('endDate').value;
            window.location.href = `?start_date=${startDate}&end_date=${endDate}&user_id=${userId}`;
        }

        function exportData() {
            const startDate = document.getElementById('startDate').value;
            const endDate = document.getElementById('endDate').value;
            const userId = document.getElementById('userId').value;

            let url = `/api/export.php?type=user_stats&start_date=${startDate}&end_date=${endDate}`;
            if (userId) {
                url += `&user_id=${userId}`;
            }
            window.open(url, '_blank');
        }
    </script>
</body>
</html>
