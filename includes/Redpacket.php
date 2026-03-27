<?php
/**
 * 红包业务逻辑类
 */
class Redpacket {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * 创建红包
     */
    public function create($data) {
        try {
            $this->db->beginTransaction();
            
            // 生成红包编码
            $code = generateRedpacketCode();
            
            // 计算有效期（0表示长期有效）
            $validHours = intval($data['valid_hours'] ?? 24);
            $validStart = date('Y-m-d H:i:s');
            if ($validHours > 0) {
                $validEnd = date('Y-m-d H:i:s', strtotime("+{$validHours} hours"));
            } else {
                // 长期有效，设置一个较远的日期
                $validEnd = date('Y-m-d H:i:s', strtotime('+100 years'));
            }
            
            $redpacketData = [
                'code' => $code,
                'name' => $data['name'],
                'description' => $data['description'] ?? '',
                'template_id' => $data['template_id'],
                'type' => $data['type'],
                'total_amount' => $data['total_amount'],
                'amount_per_packet' => $data['amount_per_packet'],
                'total_quantity' => $data['total_quantity'],
                'remaining_quantity' => $data['total_quantity'],
                'max_claims_per_user' => $data['max_claims_per_user'] ?? 1,
                'valid_hours' => $validHours,
                'valid_start' => $validStart,
                'valid_end' => $validEnd,
                'status' => 'active',
                'created_by' => $data['created_by'],
                'transfer_remark' => $data['transfer_remark'] ?? '课程推广返现',
                'remark' => $data['remark'] ?? ''
            ];
            
            $redpacketId = $this->db->insert('redpackets', $redpacketData);
            
            // 生成二维码
            $claimUrl = SITE_URL . '/claim.php?code=' . $code;
            $qrPath = QRCode::generate($claimUrl, QR_CODE_PATH . '/qr_' . $code . '.png', 400);
            
            // 更新红包记录(只保存二维码路径)
            $this->db->update('redpackets', [
                'qrcode_path' => str_replace(BASE_PATH, '', $qrPath)
            ], 'id = :id', ['id' => $redpacketId]);
            
            $this->db->commit();
            
            // 记录操作日志
            logOperation($data['created_by'], 'create_redpacket', 'redpacket', $redpacketId, $redpacketData);
            
            return [
                'success' => true,
                'id' => $redpacketId,
                'code' => $code,
                'qrcode' => str_replace(BASE_PATH, '', $qrPath)
            ];
            
        } catch (Exception $e) {
            $this->db->rollback();
            logError('Create redpacket failed: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * 批量创建红包
     */
    public function createBatch($data, $count) {
        $results = [];
        for ($i = 0; $i < $count; $i++) {
            $result = $this->create($data);
            if ($result['success']) {
                $results[] = $result;
            }
        }
        return $results;
    }
    
    /**
     * 检查红包状态（用于领取页面展示）
     */
    public function checkStatus($code) {
        $redpacket = $this->db->fetch("SELECT r.*, u.status as creator_status,
            t.name as template_name, t.type as template_type, t.bg_color, t.bg_gradient,
            t.title_color, t.amount_color, t.button_color, t.button_text, t.animation_type
            FROM redpackets r
            LEFT JOIN users u ON r.created_by = u.id
            LEFT JOIN redpacket_templates t ON r.template_id = t.id
            WHERE r.code = ?", [$code]);

        if (!$redpacket) {
            return ['valid' => false, 'message' => '红包不存在'];
        }

        // 检查创建者状态
        if ($redpacket['creator_status'] != 1) {
            return ['valid' => false, 'message' => '该红包已无法领取'];
        }

        // 检查是否已作废
        if ($redpacket['status'] === 'cancelled') {
            return ['valid' => false, 'message' => '红包已作废'];
        }

        // 检查有效期（仅针对有有效期的红包）
        if ($redpacket['valid_hours'] > 0 && strtotime($redpacket['valid_end']) < time()) {
            return ['valid' => false, 'message' => '红包已过期'];
        }

        // 检查是否已领完
        if ($redpacket['remaining_quantity'] <= 0) {
            return ['valid' => false, 'message' => '红包已被领完'];
        }

        return [
            'valid' => true,
            'redpacket' => $redpacket,
            'message' => '红包有效'
        ];
    }
    
    /**
     * 领取红包 - 第一步：锁定红包
     */
    public function claim($code, $userInfo) {
        $claimId = null;
        $redpacket = null;
        $openid = $userInfo['openid'] ?? '';
        $transactionActive = false;
        $newRemaining = 0;

        try {
            $this->db->beginTransaction();
            $transactionActive = true;

            // 获取红包信息（加锁查询，防止并发问题）
            $redpacket = $this->db->fetch("SELECT r.*, u.status as creator_status,
                t.name as template_name, t.type as template_type, t.bg_color, t.bg_gradient,
                t.title_color, t.amount_color, t.button_color, t.button_text, t.animation_type
                FROM redpackets r
                LEFT JOIN users u ON r.created_by = u.id
                LEFT JOIN redpacket_templates t ON r.template_id = t.id
                WHERE r.code = ?
                FOR UPDATE", [$code]);

            if (!$redpacket) {
                $this->db->rollback();
                return ['success' => false, 'message' => '红包不存在'];
            }

            // 检查创建者状态
            if ($redpacket['creator_status'] != 1) {
                $this->db->rollback();
                return ['success' => false, 'message' => '该红包已无法领取'];
            }

            // 检查是否已作废
            if ($redpacket['status'] === 'cancelled') {
                $this->db->rollback();
                return ['success' => false, 'message' => '红包已作废'];
            }

            // 检查有效期
            if ($redpacket['valid_hours'] > 0 && strtotime($redpacket['valid_end']) < time()) {
                $this->db->rollback();
                return ['success' => false, 'message' => '红包已过期'];
            }

            // 检查剩余数量
            if ($redpacket['remaining_quantity'] <= 0) {
                $this->db->rollback();
                return ['success' => false, 'message' => '红包已被领完'];
            }

            // 检查用户是否已成功领取过
            $claimedCount = $this->db->fetch("SELECT COUNT(*) as cnt FROM redpacket_claims
                WHERE redpacket_id = ? AND openid = ? AND claim_status = 'success'",
                [$redpacket['id'], $openid]
            )['cnt'] ?? 0;

            if ($claimedCount >= $redpacket['max_claims_per_user']) {
                $this->db->rollback();
                return ['success' => false, 'message' => '您已达到领取上限'];
            }

            // 检查该用户是否已有进行中的领取（已锁定但未完成）
            $existingClaim = $this->db->fetch("SELECT * FROM redpacket_claims
                WHERE redpacket_id = ? AND openid = ? AND claim_status = 'processing'
                ORDER BY id DESC LIMIT 1",
                [$redpacket['id'], $openid]
            );

            if ($existingClaim) {
                // 已有锁定记录，检查是否已发起过微信转账
                if (!empty($existingClaim['transfer_bill_no']) && $existingClaim['transfer_bill_no'] !== 'LOCKED') {
                    $this->db->commit();
                    $transactionActive = false;

                    $wechatPay = new WechatPay();
                    $queryResult = $wechatPay->queryTransfer($existingClaim['transfer_bill_no']);

                    if ($queryResult['success']) {
                        $transferData = $queryResult['data'];
                        $transferState = $transferData['state'] ?? '';

                        if ($transferState === 'SUCCESS' || $transferState === 'PROCESSING') {
                            return [
                                'success' => true,
                                'message' => '转账处理中',
                                'amount' => $existingClaim['amount'],
                                'package_info' => $transferData['package_info'] ?? '',
                                'transfer_bill_no' => $existingClaim['transfer_bill_no'],
                                'claim_id' => $existingClaim['id']
                            ];
                        }

                        // 转账失败，允许重试
                        if ($transferState === 'FAIL') {
                            $this->db->update('redpacket_claims', [
                                'transfer_bill_no' => 'LOCKED',
                                'claim_status' => 'processing'
                            ], 'id = :id', ['id' => $existingClaim['id']]);

                            return [
                                'success' => true,
                                'message' => '红包已锁定',
                                'amount' => $existingClaim['amount'],
                                'claim_id' => $existingClaim['id'],
                                'need_transfer' => true
                            ];
                        }
                    }

                    // 查询失败，返回已有记录
                    return [
                        'success' => true,
                        'message' => '红包已锁定',
                        'amount' => $existingClaim['amount'],
                        'claim_id' => $existingClaim['id'],
                        'transfer_bill_no' => $existingClaim['transfer_bill_no']
                    ];
                }

                // 只是锁定状态，还未发起转账
                $this->db->commit();
                $transactionActive = false;

                return [
                    'success' => true,
                    'message' => '红包已锁定',
                    'amount' => $existingClaim['amount'],
                    'claim_id' => $existingClaim['id'],
                    'need_transfer' => true
                ];
            }

            // 生成转账单号（先用特殊标记表示已锁定但未发起转账）
            $transferBillNo = 'LOCKED';

            // 创建领取记录（锁定状态）
            $claimData = [
                'redpacket_id' => $redpacket['id'],
                'redpacket_code' => $code,
                'openid' => $openid,
                'unionid' => $userInfo['unionid'] ?? '',
                'nickname' => $userInfo['nickname'] ?? '',
                'avatar' => $userInfo['avatar'] ?? '',
                'amount' => $redpacket['amount_per_packet'],
                'claim_status' => 'processing',
                'transfer_bill_no' => $transferBillNo,
                'client_ip' => getClientIP(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'device_type' => $this->getDeviceType(),
                'claimed_at' => date('Y-m-d H:i:s')
            ];

            $claimId = $this->db->insert('redpacket_claims', $claimData);

            // 更新红包剩余数量（锁定库存）
            $newRemaining = $redpacket['remaining_quantity'] - 1;
            $this->db->update('redpackets', [
                'remaining_quantity' => $newRemaining
            ], 'id = :id', ['id' => $redpacket['id']]);

            // 记录领取日志
            $this->logClaimAction($claimId, $redpacket['id'], $openid, 'claim', '用户锁定红包', $claimData);

            $this->db->commit();
            $transactionActive = false;

            return [
                'success' => true,
                'message' => '红包已锁定',
                'amount' => $redpacket['amount_per_packet'],
                'claim_id' => $claimId,
                'need_transfer' => true
            ];

        } catch (Exception $e) {
            if ($transactionActive) {
                try {
                    $this->db->rollback();
                } catch (Exception $rollbackEx) {}
            }
            logError('Claim redpacket failed: ' . $e->getMessage() . "\nTrace: " . $e->getTraceAsString());
            return ['success' => false, 'message' => '领取失败，请稍后重试'];
        }
    }

    /**
     * 发起微信转账 - 第二步：用户点击"确认收款"时调用
     */
    public function initiateTransfer($claimId, $openid) {
        try {
            // 获取领取记录
            $claim = $this->db->fetch("SELECT c.*, r.transfer_remark, r.amount_per_packet
                FROM redpacket_claims c
                LEFT JOIN redpackets r ON c.redpacket_id = r.id
                WHERE c.id = ? AND c.openid = ? AND c.claim_status = 'processing'",
                [$claimId, $openid]
            );

            if (!$claim) {
                return ['success' => false, 'message' => '领取记录不存在或已处理'];
            }

            // 如果已经发起过转账，直接返回已有信息
            if (!empty($claim['transfer_bill_no']) && $claim['transfer_bill_no'] !== 'LOCKED') {
                $wechatPay = new WechatPay();
                $queryResult = $wechatPay->queryTransfer($claim['transfer_bill_no']);

                if ($queryResult['success']) {
                    $transferData = $queryResult['data'];
                    return [
                        'success' => true,
                        'message' => '转账处理中',
                        'amount' => $claim['amount'],
                        'package_info' => $transferData['package_info'] ?? '',
                        'transfer_bill_no' => $claim['transfer_bill_no']
                    ];
                }
            }

            // 生成真实的转账单号
            $transferBillNo = 'TRANS' . date('YmdHis') . rand(1000, 9999);

            // 更新领取记录
            $this->db->update('redpacket_claims', [
                'transfer_bill_no' => $transferBillNo
            ], 'id = :id', ['id' => $claimId]);

            // 调用微信支付转账
            $wechatPay = new WechatPay();
            $transferResult = $wechatPay->transfer(
                $openid,
                $claim['amount'],
                $transferBillNo,
                '', // userName
                $claim['transfer_remark'] ?? '课程推广返现'
            );

            if ($transferResult['success']) {
                $packageInfo = $transferResult['data']['package_info'] ?? '';

                $this->logClaimAction($claimId, $claim['redpacket_id'], $openid, 'transfer_initiated', '转账已发起，等待用户确认', $transferResult['data']);

                return [
                    'success' => true,
                    'message' => '转账已发起',
                    'amount' => $claim['amount'],
                    'package_info' => $packageInfo,
                    'transfer_bill_no' => $transferBillNo
                ];
            } else {
                // 转账发起失败，恢复红包数量
                $redpacket = $this->db->fetch("SELECT * FROM redpackets WHERE id = ?", [$claim['redpacket_id']]);
                if ($redpacket) {
                    $this->db->update('redpackets', [
                        'remaining_quantity' => $redpacket['remaining_quantity'] + 1
                    ], 'id = :id', ['id' => $redpacket['id']]);
                }

                // 标记领取记录为失败
                $this->db->update('redpacket_claims', [
                    'claim_status' => 'failed',
                    'fail_reason' => $transferResult['message']
                ], 'id = :id', ['id' => $claimId]);

                $this->logClaimAction($claimId, $claim['redpacket_id'], $openid, 'transfer_fail', '转账发起��败: ' . $transferResult['message'], $transferResult);

                return ['success' => false, 'message' => '转账发起失败: ' . $transferResult['message']];
            }

        } catch (Exception $e) {
            logError('Initiate transfer failed: ' . $e->getMessage());
            return ['success' => false, 'message' => '转账发起失败，请稍后重试'];
        }
    }

    /**
     * 根据ID获取红包信息
     */
    public function getById($id) {
        $redpacket = $this->db->fetch("SELECT r.*, t.name as template_name, t.type as template_type, t.bg_color, t.bg_gradient,
            t.title_color, t.amount_color, t.button_color, t.button_text, t.animation_type
            FROM redpackets r
            LEFT JOIN redpacket_templates t ON r.template_id = t.id
            WHERE r.id = ?", [$id]);

        if ($redpacket) {
            $claimedCount = $this->db->fetch("SELECT COUNT(*) as cnt FROM redpacket_claims
                WHERE redpacket_id = ? AND claim_status = 'success'", [$id])['cnt'] ?? 0;
            $redpacket['claimed_count'] = $claimedCount;
        }

        return $redpacket;
    }

    /**
     * 获取红包信息（通过 code）
     */
    public function getByCode($code) {
        return $this->db->fetch("SELECT r.*, t.name as template_name, t.type as template_type, t.bg_color, t.bg_gradient,
            t.title_color, t.amount_color, t.button_color, t.button_text, t.animation_type
            FROM redpackets r
            LEFT JOIN redpacket_templates t ON r.template_id = t.id
            WHERE r.code = ?", [$code]);
    }

    /**
     * 获取红包列表
     */
    public function getList($filters = [], $page = 1, $perPage = 20) {
        $where = ['1=1'];
        $params = [];
        
        if (!empty($filters['status'])) {
            $where[] = 'r.status = ?';
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['type'])) {
            $where[] = 'r.type = ?';
            $params[] = $filters['type'];
        }
        
        if (!empty($filters['created_by'])) {
            $where[] = 'r.created_by = ?';
            $params[] = $filters['created_by'];
        }
        
        if (!empty($filters['keyword'])) {
            $where[] = '(r.name LIKE ? OR r.code LIKE ?)';
            $params[] = '%' . $filters['keyword'] . '%';
            $params[] = '%' . $filters['keyword'] . '%';
        }
        
        $whereClause = implode(' AND ', $where);
        $total = $this->db->fetch("SELECT COUNT(*) as count FROM redpackets r WHERE {$whereClause}", $params)['count'];
        $pagination = paginate($total, $page, $perPage);

        $sql = "SELECT r.*, u.real_name as creator_name,
            (SELECT COUNT(*) FROM redpacket_claims WHERE redpacket_id = r.id AND claim_status = 'success') as claimed_count
            FROM redpackets r 
            LEFT JOIN users u ON r.created_by = u.id 
            WHERE {$whereClause}
            ORDER BY r.created_at DESC
            LIMIT {$pagination['offset']}, {$pagination['per_page']}";

        $list = $this->db->fetchAll($sql, $params);

        return [
            'list' => $list,
            'pagination' => $pagination
        ];
    }

    /**
     * 更新红包
     */
    public function update($id, $data, $userId) {
        try {
            unset($data['code'], $data['created_by'], $data['created_at']);
            $this->db->update('redpackets', $data, 'id = :id', ['id' => $id]);
            logOperation($userId, 'update_redpacket', 'redpacket', $id, $data);
            return ['success' => true];
        } catch (Exception $e) {
            logError('Update redpacket failed: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * 作废红包
     */
    public function cancel($id, $userId) {
        try {
            $redpacket = $this->getById($id);
            if (!$redpacket) {
                return ['success' => false, 'message' => '红包不存在'];
            }
            if ($redpacket['status'] === 'cancelled') {
                return ['success' => false, 'message' => '红包已作废'];
            }
            $claimedCount = $redpacket['total_quantity'] - $redpacket['remaining_quantity'];
            if ($claimedCount > 0) {
                return ['success' => false, 'message' => '已被领取的红包不允许作废'];
            }
            if ($redpacket['type'] === 'single' || $redpacket['type'] === 'batch') {
                return ['success' => false, 'message' => '一次性红包和批量红包不支持作废操作'];
            }
            $this->db->update('redpackets', [
                'status' => 'cancelled',
                'updated_at' => date('Y-m-d H:i:s')
            ], 'id = :id', ['id' => $id]);
            logOperation($userId, 'cancel_redpacket', 'redpacket', $id, []);
            return ['success' => true];
        } catch (Exception $e) {
            logError('Cancel redpacket failed: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * 终止红包
     */
    public function terminate($id, $userId) {
        try {
            $redpacket = $this->getById($id);
            if (!$redpacket) {
                return ['success' => false, 'message' => '红包不存在'];
            }
            if ($redpacket['status'] !== 'active') {
                return ['success' => false, 'message' => '只有有效的红包才能终止'];
            }
            $this->db->update('redpackets', [
                'status' => 'completed',
                'remaining_quantity' => 0,
                'updated_at' => date('Y-m-d H:i:s')
            ], 'id = :id', ['id' => $id]);
            logOperation($userId, 'terminate_redpacket', 'redpacket', $id, []);
            return ['success' => true];
        } catch (Exception $e) {
            logError('Terminate redpacket failed: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * 删除红包
     */
    public function delete($id, $userId) {
        try {
            $redpacket = $this->getById($id);
            if (!$redpacket) {
                return ['success' => false, 'message' => '红包不存在'];
            }
            $claimedCount = $this->db->fetch("SELECT COUNT(*) as count FROM redpacket_claims WHERE redpacket_id = ?", [$id])['count'];
            if ($claimedCount > 0 && $redpacket['status'] !== 'cancelled') {
                return ['success' => false, 'message' => '有领取记录的红包只能作废后删除'];
            }
            $this->db->query("DELETE FROM claim_logs WHERE redpacket_id = ?", [$id]);
            $this->db->query("DELETE FROM redpacket_claims WHERE redpacket_id = ?", [$id]);
            $this->db->query("DELETE FROM redpackets WHERE id = ?", [$id]);
            logOperation($userId, 'delete_redpacket', 'redpacket', $id, []);
            return ['success' => true];
        } catch (Exception $e) {
            logError('Delete redpacket failed: ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * 获取领取记录
     */
    public function getClaims($redpacketId = null, $page = 1, $perPage = 20) {
        $where = ['1=1'];
        $params = [];
        if ($redpacketId) {
            $where[] = 'c.redpacket_id = ?';
            $params[] = $redpacketId;
        }
        $whereClause = implode(' AND ', $where);
        $total = $this->db->fetch("SELECT COUNT(*) as count FROM redpacket_claims c WHERE {$whereClause}", $params)['count'];
        $pagination = paginate($total, $page, $perPage);
        $sql = "SELECT c.*, r.name as redpacket_name, r.code as redpacket_code
            FROM redpacket_claims c
            LEFT JOIN redpackets r ON c.redpacket_id = r.id
            WHERE {$whereClause}
            ORDER BY c.created_at DESC
            LIMIT {$pagination['offset']}, {$pagination['per_page']}";
        $list = $this->db->fetchAll($sql, $params);
        return [
            'list' => $list,
            'pagination' => $pagination
        ];
    }

    /**
     * 记录领取日志
     */
    private function logClaimAction($claimId, $redpacketId, $openid, $action, $desc, $data = []) {
        try {
            $this->db->insert('claim_logs', [
                'claim_id' => $claimId,
                'redpacket_id' => $redpacketId,
                'openid' => $openid,
                'action' => $action,
                'action_desc' => $desc,
                'request_data' => json_encode($data, JSON_UNESCAPED_UNICODE),
                'client_ip' => getClientIP(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
            ]);
        } catch (Exception $e) {
            logError('Log claim action failed: ' . $e->getMessage());
        }
    }

    /**
     * 获取设备类型
     */
    private function getDeviceType() {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        if (strpos($userAgent, 'MicroMessenger') !== false) {
            return 'wechat';
        } elseif (preg_match('/Mobile|Android|iPhone|iPad/', $userAgent)) {
            return 'mobile';
        } else {
            return 'desktop';
        }
    }

    /**
     * 获取统计报表
     */
    public function getStatistics($startDate = null, $endDate = null) {
        $where = '';
        $params = [];
        if ($startDate && $endDate) {
            $where = 'WHERE created_at BETWEEN ? AND ?';
            $params = [$startDate . ' 00:00:00', $endDate . ' 23:59:59'];
        }
        // 红包统计
        $redpacketStats = $this->db->fetch("SELECT 
            COUNT(*) as total_count,
            SUM(total_amount) as total_amount,
            SUM(total_quantity) as total_quantity,
            SUM(total_quantity - remaining_quantity) as claimed_quantity,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_count,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_count,
            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_count
            FROM redpackets {$where}", $params);
        // 领取统计
        $claimStats = $this->db->fetch("SELECT 
            COUNT(*) as total_claims,
            SUM(CASE WHEN claim_status = 'success' THEN 1 ELSE 0 END) as success_count,
            SUM(CASE WHEN claim_status = 'failed' THEN 1 ELSE 0 END) as fail_count,
            SUM(CASE WHEN claim_status = 'success' THEN amount ELSE 0 END) as total_claimed_amount
            FROM redpacket_claims {$where}", $params);
        // 按员工统计
        $staffStats = $this->db->fetchAll("SELECT 
            u.real_name,
            COUNT(r.id) as redpacket_count,
            SUM(r.total_amount) as total_amount,
            SUM(r.total_quantity - r.remaining_quantity) as claimed_count
            FROM redpackets r
            LEFT JOIN users u ON r.created_by = u.id
            {$where}
            GROUP BY r.created_by
            ORDER BY total_amount DESC", $params);
        return [
            'redpacket' => $redpacketStats,
            'claim' => $claimStats,
            'staff' => $staffStats
        ];
    }
}