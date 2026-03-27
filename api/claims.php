<?php
/**
 * 领取记录管理 API
 */
require_once '../config.php';

checkAuth();

header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? '';
$db = Database::getInstance();

switch ($action) {
    case 'update_remark':
        // 更新内部备注（直接编辑）
        $claimId = intval($_POST['claim_id'] ?? 0);
        $content = trim($_POST['content'] ?? '');

        if (!$claimId) {
            jsonResponse(false, '领取记录ID不能为空');
        }

        // 检查领取记录是否存在
        $claim = $db->fetch("SELECT id, admin_remark FROM redpacket_claims WHERE id = ?", [$claimId]);
        if (!$claim) {
            jsonResponse(false, '领取记录不存在');
        }

        // 获取当前用户信息
        $currentUser = User::getCurrentUser();

        // 更新备注
        $result = $db->update('redpacket_claims', [
            'admin_remark' => $content
        ], 'id = :id', ['id' => $claimId]);

        if ($result !== false) {
            // 记录操作日志
            logOperation($currentUser['id'], 'update_remark', 'claim', $claimId, [
                'old_content' => $claim['admin_remark'],
                'new_content' => $content
            ]);

            jsonResponse(true, '备注更新成功');
        } else {
            jsonResponse(false, '备注更新失败');
        }
        break;

    default:
        jsonResponse(false, '未知操作');
}
