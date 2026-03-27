<?php
/**
 * 红包领取API
 */
// 关闭错误显示，只记录错误
ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once '../config.php';

// 清除所有输出缓冲
while (ob_get_level()) {
    ob_end_clean();
}
ob_start();

header('Content-Type: application/json; charset=utf-8');

// 捕获所有错误并返回JSON
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    logError("API Error: [$errno] $errstr in $errfile on line $errline");
    // 清除之前的输出
    if (ob_get_length()) ob_clean();
    jsonResponse(false, '服务器内部错误，请稍后重试');
    exit;
});

set_exception_handler(function($e) {
    logError("API Exception: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    // 清除之前的输出
    if (ob_get_length()) ob_clean();
    jsonResponse(false, '服务器异常，请稍后重试');
    exit;
});

$action = $_GET['action'] ?? '';
$redpacket = new Redpacket();

switch ($action) {
    case 'info':
        // 获取红包信息
        $code = $_GET['code'] ?? '';
        if (empty($code)) {
            jsonResponse(false, '红包编码不能为空');
        }
        
        // 使用 checkStatus 方法检查红包状态
        $status = $redpacket->checkStatus($code);
        
        if (!$status['valid']) {
            jsonResponse(false, $status['message']);
        }
        
        $info = $status['redpacket'];
        
        // 返回红包信息（隐藏敏感字段）
        $publicInfo = [
            'name' => $info['name'],
            'description' => $info['description'],
            'amount' => $info['amount_per_packet'],
            'valid_hours' => $info['valid_hours'],
            'valid_end' => $info['valid_end'],
            'remaining_quantity' => $info['remaining_quantity'],
            'max_claims_per_user' => $info['max_claims_per_user'],
            'bg_color' => $info['bg_color'],
            'bg_gradient' => $info['bg_gradient'],
            'title_color' => $info['title_color'],
            'amount_color' => $info['amount_color'],
            'button_color' => $info['button_color'],
            'button_text' => $info['button_text'],
            'animation_type' => $info['animation_type']
        ];
        
        jsonResponse(true, '', $publicInfo);
        break;
        
    case 'claim':
        // 第一步：锁定红包（用户点击"开"按钮）
        $code = $_POST['code'] ?? '';
        $openid = $_POST['openid'] ?? '';
        $nickname = $_POST['nickname'] ?? '';
        $avatar = $_POST['avatar'] ?? '';

        if (empty($code) || empty($openid)) {
            jsonResponse(false, '参数错误');
        }

        $userInfo = [
            'openid' => $openid,
            'nickname' => $nickname,
            'avatar' => $avatar
        ];

        $result = $redpacket->claim($code, $userInfo);
        jsonResponse($result['success'], $result['message'] ?? '', [
            'amount' => $result['amount'] ?? 0,
            'claim_id' => $result['claim_id'] ?? 0,
            'need_transfer' => $result['need_transfer'] ?? false,
            'package_info' => $result['package_info'] ?? '',
            'transfer_bill_no' => $result['transfer_bill_no'] ?? ''
        ]);
        break;

    case 'initiate_transfer':
        // 第二步：发起微信转账（用户点击"确认收款"）
        $claimId = intval($_POST['claim_id'] ?? 0);
        $openid = $_POST['openid'] ?? '';

        if (empty($claimId) || empty($openid)) {
            jsonResponse(false, '参数错误');
        }

        $result = $redpacket->initiateTransfer($claimId, $openid);
        jsonResponse($result['success'], $result['message'] ?? '', [
            'amount' => $result['amount'] ?? 0,
            'package_info' => $result['package_info'] ?? '',
            'transfer_bill_no' => $result['transfer_bill_no'] ?? ''
        ]);
        break;
        
    case 'check':
        // 检查领取状态
        $code = $_GET['code'] ?? '';
        $openid = $_GET['openid'] ?? '';

        if (empty($code) || empty($openid)) {
            jsonResponse(false, '参数错误');
        }

        $db = Database::getInstance();
        $claim = $db->fetch("SELECT * FROM redpacket_claims
            WHERE redpacket_code = ? AND openid = ? AND claim_status = 'success'
            ORDER BY id DESC LIMIT 1", [$code, $openid]);

        if ($claim) {
            jsonResponse(true, '已领取', [
                'claimed' => true,
                'amount' => $claim['amount'],
                'claimed_at' => $claim['claimed_at']
            ]);
        } else {
            jsonResponse(true, '未领取', ['claimed' => false]);
        }
        break;

    case 'confirm':
        // 用户确认收款后更新状态
        $billNo = $_POST['bill_no'] ?? '';

        if (empty($billNo)) {
            jsonResponse(false, '参数错误');
        }

        try {
            $db = Database::getInstance();
            $db->update('redpacket_claims', [
                'claim_status' => 'success',
                'transfer_result' => 'SUCCESS',
                'completed_at' => date('Y-m-d H:i:s')
            ], 'transfer_bill_no = :bill_no', ['bill_no' => $billNo]);

            jsonResponse(true, '确认成功');
        } catch (Exception $e) {
            jsonResponse(false, '确认失败：' . $e->getMessage());
        }
        break;

    default:
        jsonResponse(false, '未知操作');
}
