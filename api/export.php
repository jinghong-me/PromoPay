<?php
/**
 * 数据导出API
 */
require_once '../config.php';

checkAuth();

$type = $_GET['type'] ?? '';
$db = Database::getInstance();

switch ($type) {
    case 'claims':
        // 导出领取记录
        $startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
        $endDate = $_GET['end_date'] ?? date('Y-m-d');
        
        $where = 'WHERE c.created_at BETWEEN ? AND ?';
        $params = [$startDate . ' 00:00:00', $endDate . ' 23:59:59'];
        
        // 员工只能导出自己的
        if ($_SESSION['user_role'] !== 'admin') {
            $where .= ' AND r.created_by = ?';
            $params[] = $_SESSION['user_id'];
        }
        
        $claims = $db->fetchAll("SELECT 
            c.id,
            r.code as redpacket_code,
            r.name as redpacket_name,
            u.real_name as sender_name,
            c.openid,
            c.nickname,
            c.amount,
            c.claim_status,
            c.transfer_bill_no,
            c.client_ip,
            c.device_type,
            c.claimed_at,
            c.completed_at
            FROM redpacket_claims c
            LEFT JOIN redpackets r ON c.redpacket_id = r.id
            LEFT JOIN users u ON r.created_by = u.id
            {$where}
            ORDER BY c.created_at DESC", $params);
        
        // 生成CSV
        $filename = '领取记录_' . date('YmdHis') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM for UTF-8
        
        // 表头
        fputcsv($output, ['ID', '红包编码', '红包名称', '发放人', '用户OpenID', '用户昵称', '金额', '状态', '转账单号', 'IP地址', '设备类型', '领取时间', '完成时间']);
        
        foreach ($claims as $claim) {
            $statusText = [
                'pending' => '待处理',
                'processing' => '处理中',
                'success' => '成功',
                'failed' => '失败'
            ];
            
            fputcsv($output, [
                $claim['id'],
                $claim['redpacket_code'],
                $claim['redpacket_name'],
                $claim['sender_name'],
                $claim['openid'],
                $claim['nickname'],
                $claim['amount'],
                $statusText[$claim['claim_status']] ?? $claim['claim_status'],
                $claim['transfer_bill_no'],
                $claim['client_ip'],
                $claim['device_type'],
                $claim['claimed_at'],
                $claim['completed_at']
            ]);
        }
        
        fclose($output);
        exit;

    default:
        jsonResponse(false, '未知导出类型');
}
