<?php
/**
 * 微信支付转账回调通知处理
 * 商家转账到零钱 - 用户确认收款后微信异步通知
 */
require_once '../config.php';

$db = Database::getInstance();

// 获取回调数据
$headers = getallheaders();
$body = file_get_contents('php://input');

// 记录原始回调数据
logError('WeChat Notify Received', [
    'headers' => $headers,
    'body' => $body
]);

// 获取签名相关参数
$timestamp = $headers['Wechatpay-Timestamp'] ?? '';
$nonce = $headers['Wechatpay-Nonce'] ?? '';
$signature = $headers['Wechatpay-Signature'] ?? '';
$serial = $headers['Wechatpay-Serial'] ?? '';

// 验证必要参数
if (empty($timestamp) || empty($nonce) || empty($signature) || empty($body)) {
    http_response_code(400);
    echo json_encode(['code' => 'FAIL', 'message' => 'Missing parameters']);
    exit;
}

// 初始化微信支付
$wechatPay = new WechatPay();

// 验证签名
if (!$wechatPay->verifyNotify($timestamp, $nonce, $body, $signature)) {
    logError('WeChat Notify Signature Verify Failed', [
        'timestamp' => $timestamp,
        'nonce' => $nonce,
        'signature' => $signature
    ]);
    http_response_code(401);
    echo json_encode(['code' => 'FAIL', 'message' => 'Invalid signature']);
    exit;
}

// 解析回调数据
$notifyData = json_decode($body, true);

if (empty($notifyData)) {
    http_response_code(400);
    echo json_encode(['code' => 'FAIL', 'message' => 'Invalid body']);
    exit;
}

// 处理回调
$db = Database::getInstance();

try {
    // 微信转账回调数据结构
    // 根据微信文档，回调可能包含加密数据需要解密
    if (isset($notifyData['resource'])) {
        // 解密数据
        $resource = $notifyData['resource'];
        $decryptedData = $wechatPay->decryptNotify(
            $resource['associated_data'],
            $resource['nonce'],
            $resource['ciphertext']
        );

        logError('WeChat Notify Decrypted', $decryptedData);

        // 使用解密后的数据
        $notifyData = $decryptedData;
    }

    // 提取关键信息
    $outBillNo = $notifyData['out_bill_no'] ?? '';  // 商户转账单号
    $transferBillNo = $notifyData['transfer_bill_no'] ?? '';  // 微信转账单号
    $state = $notifyData['state'] ?? '';  // 转账状态
    $openid = $notifyData['openid'] ?? '';
    $amount = isset($notifyData['transfer_amount']) ? $notifyData['transfer_amount'] / 100 : 0;

    logError('WeChat Notify Processing', [
        'out_bill_no' => $outBillNo,
        'transfer_bill_no' => $transferBillNo,
        'state' => $state,
        'openid' => $openid,
        'amount' => $amount
    ]);

    if (empty($outBillNo)) {
        throw new Exception('Missing out_bill_no');
    }

    // 查询领取记录
    $claim = $db->fetch("SELECT * FROM redpacket_claims WHERE transfer_bill_no = ?", [$outBillNo]);

    if (!$claim) {
        throw new Exception('Claim record not found: ' . $outBillNo);
    }

    // 根据状态更新记录
    switch ($state) {
        case 'SUCCESS':
            // 转账成功
            $db->update('redpacket_claims', [
                'claim_status' => 'success',
                'transfer_result' => 'SUCCESS',
                'wechat_bill_no' => $transferBillNo,
                'completed_at' => date('Y-m-d H:i:s'),
                'notify_data' => json_encode($notifyData, JSON_UNESCAPED_UNICODE)
            ], 'transfer_bill_no = :bill_no', ['bill_no' => $outBillNo]);

            logError('WeChat Notify Success', ['bill_no' => $outBillNo]);
            break;

        case 'FAIL':
            // 转账失败
            $failReason = $notifyData['fail_reason'] ?? '未知错误';
            $db->update('redpacket_claims', [
                'claim_status' => 'failed',
                'transfer_result' => 'FAIL',
                'fail_reason' => $failReason,
                'wechat_bill_no' => $transferBillNo,
                'notify_data' => json_encode($notifyData, JSON_UNESCAPED_UNICODE)
            ], 'transfer_bill_no = :bill_no', ['bill_no' => $outBillNo]);

            // 恢复红包数量
            $redpacket = $db->fetch("SELECT * FROM redpackets WHERE id = ?", [$claim['redpacket_id']]);
            if ($redpacket) {
                $db->update('redpackets', [
                    'remaining_quantity' => $redpacket['remaining_quantity'] + 1
                ], 'id = :id', ['id' => $redpacket['id']]);
            }

            logError('WeChat Notify Failed', ['bill_no' => $outBillNo, 'reason' => $failReason]);
            break;

        case 'CANCELLED':
            // 用户取消或超时
            $db->update('redpacket_claims', [
                'claim_status' => 'cancelled',
                'transfer_result' => 'CANCELLED',
                'wechat_bill_no' => $transferBillNo,
                'notify_data' => json_encode($notifyData, JSON_UNESCAPED_UNICODE)
            ], 'transfer_bill_no = :bill_no', ['bill_no' => $outBillNo]);

            // 恢复红包数量
            $redpacket = $db->fetch("SELECT * FROM redpackets WHERE id = ?", [$claim['redpacket_id']]);
            if ($redpacket) {
                $db->update('redpackets', [
                    'remaining_quantity' => $redpacket['remaining_quantity'] + 1
                ], 'id = :id', ['id' => $redpacket['id']]);
            }

            logError('WeChat Notify Cancelled', ['bill_no' => $outBillNo]);
            break;

        default:
            // 其他状态，记录但不处理
            logError('WeChat Notify Unknown State', ['state' => $state, 'data' => $notifyData]);
            break;
    }

    // 记录回调日志
    $db->insert('claim_logs', [
        'claim_id' => $claim['id'],
        'redpacket_id' => $claim['redpacket_id'],
        'openid' => $openid,
        'action' => 'wechat_notify',
        'action_desc' => '微信回调: ' . $state,
        'request_data' => json_encode($notifyData, JSON_UNESCAPED_UNICODE),
        'client_ip' => getClientIP(),
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
    ]);

    // 返回成功响应给微信
    http_response_code(200);
    echo json_encode(['code' => 'SUCCESS', 'message' => 'OK']);

} catch (Exception $e) {
    logError('WeChat Notify Error: ' . $e->getMessage(), [
        'data' => $notifyData,
        'trace' => $e->getTraceAsString()
    ]);

    http_response_code(500);
    echo json_encode(['code' => 'FAIL', 'message' => $e->getMessage()]);
}
