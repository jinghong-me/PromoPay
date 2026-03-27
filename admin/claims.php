<?php
/**
 * 领取记录页面
 */
require_once '../config.php';
checkAuth();

$db = Database::getInstance();
$currentUser = User::getCurrentUser();

// 获取筛选参数
$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';
$status = $_GET['status'] ?? '';
$redpacketId = intval($_GET['redpacket_id'] ?? 0);
$page = intval($_GET['page'] ?? 1);
$pageSize = 20;
$offset = ($page - 1) * $pageSize;

// 构建查询条件
$where = ['1=1'];
$params = [];

if ($startDate) {
    $where[] = 'DATE(c.created_at) >= ?';
    $params[] = $startDate;
}
if ($endDate) {
    $where[] = 'DATE(c.created_at) <= ?';
    $params[] = $endDate;
}
if ($status) {
    $where[] = 'c.claim_status = ?';
    $params[] = $status;
}
if ($redpacketId) {
    $where[] = 'c.redpacket_id = ?';
    $params[] = $redpacketId;
}

// 员工只能看到自己的红包的领取记录
if ($currentUser['role'] !== 'admin') {
    $where[] = 'r.created_by = ?';
    $params[] = $currentUser['id'];
}

// 获取统计数据
$statsSql = "SELECT 
    COUNT(*) as total_claims,
    SUM(CASE WHEN c.claim_status = 'success' THEN 1 ELSE 0 END) as success_count,
    SUM(CASE WHEN c.claim_status = 'success' THEN c.amount ELSE 0 END) as success_amount
FROM redpacket_claims c 
LEFT JOIN redpackets r ON c.redpacket_id = r.id 
WHERE " . implode(' AND ', $where);
$stats = $db->fetch($statsSql, $params);

// 获取列表数据
$listSql = "SELECT c.*, r.name as redpacket_name, r.transfer_remark, r.remark as redpacket_remark, u.real_name as sender_name
    FROM redpacket_claims c
    LEFT JOIN redpackets r ON c.redpacket_id = r.id
    LEFT JOIN users u ON r.created_by = u.id
    WHERE " . implode(' AND ', $where) . "
    ORDER BY c.created_at DESC
    LIMIT ? OFFSET ?";
$listParams = array_merge($params, [$pageSize, $offset]);
$claims = $db->fetchAll($listSql, $listParams);

// 获取总数
$countSql = "SELECT COUNT(*) as total FROM redpacket_claims c 
             LEFT JOIN redpackets r ON c.redpacket_id = r.id 
             WHERE " . implode(' AND ', $where);
$total = $db->fetch($countSql, $params)['total'];
$totalPages = ceil($total / $pageSize);

// 状态文本映射
$statusClass = [
    'success' => 'success',
    'pending' => 'pending',
    'processing' => 'pending',
    'failed' => 'failed',
    'cancelled' => 'failed'
];
$statusText = [
    'success' => '成功',
    'pending' => '待处理',
    'processing' => '处理中',
    'failed' => '失败',
    'cancelled' => '已取消'
];
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>领取记录 - <?php echo SITE_NAME; ?></title>
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
        
        .status-badge.success { background: #f6ffed; color: #52c41a; }
        .status-badge.pending { background: #fff7e6; color: #fa8c16; }
        .status-badge.failed { background: #fff2f0; color: #ff4d4f; }
        
        .amount { font-weight: 600; color: #ff6b6b; }

        /* 备注单元格 */
        .remark-cell {
            display: block;
            max-width: 200px;
            min-height: 30px;
            padding: 8px;
            border-radius: 6px;
            cursor: pointer;
            transition: background 0.3s;
            text-decoration: none;
            color: inherit;
        }

        .remark-cell:hover {
            background: #f0f5ff;
        }

        .remark-content {
            font-size: 13px;
            color: #333;
            line-height: 1.5;
        }

        .remark-placeholder {
            font-size: 13px;
            color: #999;
            font-style: italic;
        }

        .remark-text {
            font-size: 13px;
            color: #666;
            cursor: help;
        }

        /* 备注弹窗 */
        .remark-modal textarea {
            width: 100%;
            min-height: 100px;
            padding: 12px;
            border: 1px solid #e8e8e8;
            border-radius: 8px;
            font-size: 14px;
            resize: vertical;
            font-family: inherit;
        }

        .remark-modal textarea:focus {
            outline: none;
            border-color: #667eea;
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

        .modal-close {
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

        .modal-close:hover {
            background: #f5f5f5;
            color: #333;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-size: 14px;
            color: #666;
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
        }

        .remark-history {
            background: #f5f5f5;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 16px;
            max-height: 150px;
            overflow-y: auto;
        }

        .remark-history-title {
            font-size: 12px;
            color: #999;
            margin-bottom: 8px;
        }

        .remark-history-item {
            font-size: 13px;
            color: #333;
            padding: 4px 0;
            border-bottom: 1px dashed #ddd;
        }

        .remark-history-item:last-child {
            border-bottom: none;
        }

        .remark-history-time {
            font-size: 11px;
            color: #999;
        }

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
        
        /* 统计卡片 */
        .stats-bar {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 24px;
        }
        
        .stat-item {
            background: white;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
        }
        
        .stat-value {
            font-size: 28px;
            font-weight: 700;
            color: #333;
        }
        
        .stat-label {
            font-size: 13px;
            color: #999;
            margin-top: 4px;
        }
    </style>
</head>
<body>
    <?php require_once 'includes/sidebar.php'; ?>
    
    <main class="main-content">
        <header class="topbar">
            <h1 class="topbar-title">领取记录</h1>
        </header>
        
        <div class="content">
            <!-- 统计 -->
            <div class="stats-bar">
                <div class="stat-item">
                    <div class="stat-value"><?php echo intval($stats['total_claims'] ?? 0); ?></div>
                    <div class="stat-label">总领取次数</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value"><?php echo intval($stats['success_count'] ?? 0); ?></div>
                    <div class="stat-label">成功领取</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value">¥<?php echo number_format(floatval($stats['success_amount'] ?? 0), 2); ?></div>
                    <div class="stat-label">领取总金额</div>
                </div>
            </div>
            
            <!-- 筛选栏 -->
            <div class="filter-bar">
                <button class="btn btn-secondary" onclick="setDateRange('thismonth')">本月</button>
                <button class="btn btn-secondary" onclick="setDateRange('lastmonth')">上月</button>
                <form method="get" style="display: flex; gap: 16px; flex-wrap: wrap; align-items: center;">
                    <input type="date" name="start_date" id="startDate" placeholder="开始日期" value="<?php echo htmlspecialchars($startDate); ?>">
                    <input type="date" name="end_date" id="endDate" placeholder="结束日期" value="<?php echo htmlspecialchars($endDate); ?>">
                    <select name="status" id="status">
                        <option value="">全部状态</option>
                        <option value="success" <?php echo $status === 'success' ? 'selected' : ''; ?>>成功</option>
                        <option value="processing" <?php echo $status === 'processing' ? 'selected' : ''; ?>>处理中</option>
                        <option value="failed" <?php echo $status === 'failed' ? 'selected' : ''; ?>>失败</option>
                        <option value="cancelled" <?php echo $status === 'cancelled' ? 'selected' : ''; ?>>已取消</option>
                    </select>
                    <button type="submit" class="btn btn-primary">搜索</button>
                    <a href="/admin/claims.php" class="btn btn-secondary">重置</a>
                </form>
            </div>
            
            <!-- 领取记录列表 -->
            <div class="panel">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>红包名称</th>
                            <th>发放人</th>
                            <th>用户</th>
                            <th>金额</th>
                            <th>状态</th>
                            <th>转账单号</th>
                            <th>微信转账备注</th>
                            <th>红包备注</th>
                            <th>内部备注（点击编辑）</th>
                            <th>设备</th>
                            <th>IP 地址</th>
                            <th>领取时间</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($claims)): ?>
                        <tr>
                            <td colspan="13" style="text-align: center; color: #999;">暂无数据</td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($claims as $claim): ?>
                        <tr>
                            <td><?php echo $claim['id']; ?></td>
                            <td><?php echo htmlspecialchars($claim['redpacket_name']); ?></td>
                            <td><?php echo htmlspecialchars($claim['sender_name']); ?></td>
                            <td><?php echo htmlspecialchars($claim['nickname'] ?: substr($claim['openid'], 0, 10) . '...'); ?></td>
                            <td class="amount">¥<?php echo number_format($claim['amount'], 2); ?></td>
                            <td>
                                <span class="status-badge <?php echo $statusClass[$claim['claim_status']] ?? 'pending'; ?>">
                                    <?php echo $statusText[$claim['claim_status']] ?? $claim['claim_status']; ?>
                                </span>
                            </td>
                            <td><code><?php echo $claim['transfer_bill_no'] ?: '-'; ?></code></td>
                            <td><?php echo htmlspecialchars($claim['transfer_remark'] ?: '-'); ?></td>
                            <td>
                                <?php if (!empty($claim['redpacket_remark'])): ?>
                                    <span class="remark-text" title="<?php echo htmlspecialchars($claim['redpacket_remark']); ?>">
                                        <?php echo htmlspecialchars(mb_strimwidth($claim['redpacket_remark'], 0, 20, '...')); ?>
                                    </span>
                                <?php else: ?>
                                    <span class="remark-placeholder">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="javascript:void(0);" class="remark-cell" onclick="openRemarkModal(<?php echo $claim['id']; ?>, '<?php echo htmlspecialchars($claim['admin_remark'] ?? '', ENT_QUOTES, 'UTF-8'); ?>'); return false;">
                                    <?php if (!empty($claim['admin_remark'])): ?>
                                        <div class="remark-content"><?php echo nl2br(htmlspecialchars($claim['admin_remark'])); ?></div>
                                    <?php else: ?>
                                        <span class="remark-placeholder">点击添加备注...</span>
                                    <?php endif; ?>
                                </a>
                            </td>
                            <td><?php echo $claim['device_type'] ?: '-'; ?></td>
                            <td><?php echo $claim['client_ip'] ?: '-'; ?></td>
                            <td><?php echo date('Y-m-d H:i:s', strtotime($claim['created_at'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
                
                <!-- 分页 -->
                <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?><?php echo $startDate ? '&start_date=' . $startDate : ''; ?><?php echo $endDate ? '&end_date=' . $endDate : ''; ?><?php echo $status ? '&status=' . $status : ''; ?><?php echo $redpacketId ? '&redpacket_id=' . $redpacketId : ''; ?>">上一页</a>
                    <?php endif; ?>

                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <?php if ($i == $page): ?>
                    <span class="current"><?php echo $i; ?></span>
                    <?php else: ?>
                    <a href="?page=<?php echo $i; ?><?php echo $startDate ? '&start_date=' . $startDate : ''; ?><?php echo $endDate ? '&end_date=' . $endDate : ''; ?><?php echo $status ? '&status=' . $status : ''; ?><?php echo $redpacketId ? '&redpacket_id=' . $redpacketId : ''; ?>"><?php echo $i; ?></a>
                    <?php endif; ?>
                    <?php endfor; ?>

                    <?php if ($page < $totalPages): ?>
                    <a href="?page=<?php echo $page + 1; ?><?php echo $startDate ? '&start_date=' . $startDate : ''; ?><?php echo $endDate ? '&end_date=' . $endDate : ''; ?><?php echo $status ? '&status=' . $status : ''; ?><?php echo $redpacketId ? '&redpacket_id=' . $redpacketId : ''; ?>">下一页</a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
    
    <!-- 备注编辑弹窗 -->
    <div class="modal-overlay" id="remarkModal" style="display: none;">
        <div class="modal-content remark-modal">
            <div class="modal-header">
                <h3 class="modal-title">编辑内部备注</h3>
                <button class="modal-close" onclick="closeRemarkModal()">&times;</button>
            </div>
            <div class="form-group">
                <label>备注内容：</label>
                <textarea id="remarkContent" placeholder="请输入备注内容..."></textarea>
            </div>
            <div class="form-actions">
                <button type="button" class="btn btn-secondary" onclick="closeRemarkModal()">取消</button>
                <button type="button" class="btn btn-primary" onclick="saveRemark()">保存</button>
            </div>
        </div>
    </div>

    <script>
        let currentClaimId = null;

        // 打开备注弹窗
        function openRemarkModal(claimId, currentRemark) {
            currentClaimId = claimId;

            // 设置当前备注内容
            var remarkContent = document.getElementById('remarkContent');
            if (remarkContent) {
                remarkContent.value = currentRemark || '';
            }

            // 显示弹窗
            var modal = document.getElementById('remarkModal');
            if (modal) {
                modal.style.display = 'flex';
            }
        }

        // 关闭备注弹窗
        function closeRemarkModal() {
            document.getElementById('remarkModal').style.display = 'none';
            currentClaimId = null;
        }

        // 保存备注
        async function saveRemark() {
            const content = document.getElementById('remarkContent').value.trim();

            try {
                const response = await fetch('/api/claims.php?action=update_remark', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `claim_id=${currentClaimId}&content=${encodeURIComponent(content)}`
                });

                const result = await response.json();

                if (result.success) {
                    closeRemarkModal();
                    // 刷新页面显示新备注
                    window.location.reload();
                } else {
                    alert(result.message || '保存失败');
                }
            } catch (error) {
                alert('保存失败，请重试');
            }
        }

        // 设置日期范围
        function setDateRange(range) {
            const now = new Date();
            let start, end;
            
            if (range === 'thismonth') {
                start = new Date(now.getFullYear(), now.getMonth(), 1);
                end = now;
            } else if (range === 'lastmonth') {
                start = new Date(now.getFullYear(), now.getMonth() - 1, 1);
                end = new Date(now.getFullYear(), now.getMonth(), 0);
            }
            
            const formatDate = (date) => {
                const year = date.getFullYear();
                const month = String(date.getMonth() + 1).padStart(2, '0');
                const day = String(date.getDate()).padStart(2, '0');
                return `${year}-${month}-${day}`;
            };
            
            document.getElementById('startDate').value = formatDate(start);
            document.getElementById('endDate').value = formatDate(end);
            
            // 自动提交表单
            document.querySelector('form').submit();
        }
    </script>
</body>
</html>
