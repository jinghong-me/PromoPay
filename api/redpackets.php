<?php
/**
 * 红包管理 API
 */
require_once '../config.php';

checkAuth();

header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? '';
$redpacket = new Redpacket();
$db = Database::getInstance();

switch ($action) {
    case 'list':
        $page = intval($_GET['page'] ?? 1);
        $filters = [
            'status' => $_GET['status'] ?? '',
            'type' => $_GET['type'] ?? '',
            'created_by' => $_GET['created_by'] ?? '',
            'keyword' => $_GET['keyword'] ?? ''
        ];
        
        // 员工只能看到自己的红包
        if ($_SESSION['user_role'] !== 'admin') {
            $filters['created_by'] = $_SESSION['user_id'];
        }
        
        $result = $redpacket->getList($filters, $page);
        jsonResponse(true, '', $result);
        break;
        
    case 'get':
        $id = intval($_GET['id'] ?? 0);
        $code = $_GET['code'] ?? '';

        if ($id) {
            $info = $redpacket->getById($id);
        } elseif ($code) {
            $info = $redpacket->getByCode($code);
        } else {
            jsonResponse(false, '红包 ID 或编码不能为空');
        }

        if (!$info) {
            jsonResponse(false, '红包不存在');
        }

        // 检查权限
        if ($_SESSION['user_role'] !== 'admin' && $info['created_by'] != $_SESSION['user_id']) {
            jsonResponse(false, '无权查看此红包');
        }

        // 添加二维码 Base64 编码，解决前端 html2canvas 跨域问题
        $qrcodeFile = BASE_PATH . '/' . ltrim($info['qrcode_path'], '/');
        if (file_exists($qrcodeFile)) {
            $type = pathinfo($qrcodeFile, PATHINFO_EXTENSION);
            $data = file_get_contents($qrcodeFile);
            $info['qrcode_base64'] = 'data:image/' . $type . ';base64,' . base64_encode($data);
        }

        jsonResponse(true, '', $info);
        break;
        
    case 'create':
        // 检查当前用户状态
        $currentUserId = $_SESSION['user_id'];
        $userCheck = $db->fetch("SELECT status FROM users WHERE id = ?", [$currentUserId]);
        if (!$userCheck || $userCheck['status'] != 1) {
            jsonResponse(false, '您的账号已被禁用，无法创建红包');
        }

        // 检查是否是编辑操作
        $editId = intval($_POST['edit_id'] ?? 0);
        
        if ($editId) {
            // 编辑模式
            $id = $editId;
            
            // 检查权限
            $existing = $redpacket->getById($id);
            if (!$existing) {
                jsonResponse(false, '红包不存在');
            }
            if ($_SESSION['user_role'] !== 'admin' && $existing['created_by'] != $_SESSION['user_id']) {
                jsonResponse(false, '无权修改此红包');
            }
            
            // 只能编辑有效的红包
            if ($existing['status'] !== 'active') {
                jsonResponse(false, '只能编辑有效的红包');
            }
            
            // 检查是否已被领取
            $claimedCount = $existing['total_quantity'] - $existing['remaining_quantity'];
            if ($claimedCount > 0) {
                jsonResponse(false, '已被领取的红包不允许编辑');
            }
            
            $data = [
                'name' => $_POST['name'] ?? '',
                'description' => $_POST['description'] ?? '',
                'template_id' => intval($_POST['template_id'] ?? 1),
                'type' => $_POST['type'] ?? 'single',
                'amount_per_packet' => floatval($_POST['amount_per_packet'] ?? 0),
                'total_quantity' => intval($_POST['total_quantity'] ?? 1),
                'max_claims_per_user' => intval($_POST['max_claims_per_user'] ?? 1),
                'valid_hours' => intval($_POST['valid_hours'] ?? 24),
                'transfer_remark' => $_POST['transfer_remark'] ?? '课程推广返现',
                'remark' => $_POST['remark'] ?? ''
            ];
            
            // 验证数据
            if (empty($data['name'])) {
                jsonResponse(false, '请输入红包名称');
            }
            
            if ($data['amount_per_packet'] <= 0) {
                jsonResponse(false, '红包金额必须大于 0');
            }
            
            if ($data['total_quantity'] <= 0) {
                jsonResponse(false, '红包数量必须大于 0');
            }
            
            // 重新计算剩余数量和总金额
            $data['remaining_quantity'] = $data['total_quantity'];
            $data['total_amount'] = $data['amount_per_packet'] * $data['total_quantity'];
            
            // 重新计算有效期
            if ($data['valid_hours'] > 0) {
                $data['valid_end'] = date('Y-m-d H:i:s', strtotime('+' . $data['valid_hours'] . ' hours'));
            } else {
                $data['valid_end'] = date('Y-m-d H:i:s', strtotime('+100 years'));
            }
            
            $result = $redpacket->update($id, $data, $_SESSION['user_id']);
            if ($result['success']) {
                // 返回更新后的二维码
                $updated = $redpacket->getById($id);
                
                // 添加 Base64 解决跨域
                $qrcodeFile = BASE_PATH . '/' . ltrim($updated['qrcode_path'], '/');
                $qrcodeBase64 = '';
                if (file_exists($qrcodeFile)) {
                    $type = pathinfo($qrcodeFile, PATHINFO_EXTENSION);
                    $data = file_get_contents($qrcodeFile);
                    $qrcodeBase64 = 'data:image/' . $type . ';base64,' . base64_encode($data);
                }

                jsonResponse(true, '修改成功', [
                    'id' => $id,
                    'code' => $updated['code'],
                    'qrcode' => $updated['qrcode_path'],
                    'qrcode_base64' => $qrcodeBase64
                ]);
            } else {
                jsonResponse(false, $result['message'] ?? '修改失败');
            }
        } else {
            // 创建模式
            $data = [
                'name' => $_POST['name'] ?? '',
                'description' => $_POST['description'] ?? '',
                'template_id' => intval($_POST['template_id'] ?? 1),
                'type' => $_POST['type'] ?? 'single',
                'amount_per_packet' => floatval($_POST['amount_per_packet'] ?? 0),
                'total_quantity' => intval($_POST['total_quantity'] ?? 1),
                'max_claims_per_user' => intval($_POST['max_claims_per_user'] ?? 1),
                'valid_hours' => intval($_POST['valid_hours'] ?? 24),
                'transfer_remark' => $_POST['transfer_remark'] ?? '课程推广返现',
                'remark' => $_POST['remark'] ?? '',
                'created_by' => $_SESSION['user_id']
            ];
            
            // 验证数据
            if (empty($data['name'])) {
                jsonResponse(false, '请输入红包名称');
            }
            
            if ($data['amount_per_packet'] <= 0) {
                jsonResponse(false, '红包金额必须大于 0');
            }
            
            if ($data['total_quantity'] <= 0) {
                jsonResponse(false, '红包数量必须大于 0');
            }
            
            // 计算总金额
            $data['total_amount'] = $data['amount_per_packet'] * $data['total_quantity'];
            
            // 批量创建
            $batchCount = intval($_POST['batch_count'] ?? 1);
            if ($batchCount > 1) {
                $results = $redpacket->createBatch($data, $batchCount);
                jsonResponse(true, '成功创建' . count($results) . '个红包', ['items' => $results]);
            } else {
                $result = $redpacket->create($data);
                if ($result['success']) {
                    // 添加 Base64 解决跨域
                    $qrcodeFile = BASE_PATH . '/' . ltrim($result['qrcode'], '/');
                    if (file_exists($qrcodeFile)) {
                        $type = pathinfo($qrcodeFile, PATHINFO_EXTENSION);
                        $fileData = file_get_contents($qrcodeFile);
                        $result['qrcode_base64'] = 'data:image/' . $type . ';base64,' . base64_encode($fileData);
                    }
                    jsonResponse(true, '创建成功', $result);
                } else {
                    jsonResponse(false, $result['message'] ?? '创建失败');
                }
            }
        }
        break;
        
    case 'update':
        // 这个接口已经被 create 接口的 edit 模式替代，保留以兼容旧代码
        $id = intval($_POST['id'] ?? 0);
        if (!$id) {
            jsonResponse(false, '红包 ID 不能为空');
        }

        // 检查权限
        $existing = $redpacket->getById($id);
        if (!$existing) {
            jsonResponse(false, '红包不存在');
        }
        if ($_SESSION['user_role'] !== 'admin' && $existing['created_by'] != $_SESSION['user_id']) {
            jsonResponse(false, '无权修改此红包');
        }

        // 只能编辑有效的红包
        if ($existing['status'] !== 'active') {
            jsonResponse(false, '只能编辑有效的红包');
        }

        // 检查是否已被领取
        $claimedCount = $existing['total_quantity'] - $existing['remaining_quantity'];
        if ($claimedCount > 0) {
            jsonResponse(false, '已被领取的红包不允许编辑');
        }

        $data = [
            'name' => $_POST['name'] ?? '',
            'description' => $_POST['description'] ?? '',
            'amount_per_packet' => floatval($_POST['amount_per_packet'] ?? 0),
            'total_quantity' => intval($_POST['total_quantity'] ?? 1),
            'max_claims_per_user' => intval($_POST['max_claims_per_user'] ?? 1),
            'valid_hours' => intval($_POST['valid_hours'] ?? 24),
            'transfer_remark' => $_POST['transfer_remark'] ?? '',
            'remark' => $_POST['remark'] ?? ''
        ];

        // 验证数据
        if (empty($data['name'])) {
            jsonResponse(false, '请输入红包名称');
        }

        if ($data['amount_per_packet'] <= 0) {
            jsonResponse(false, '红包金额必须大于 0');
        }

        if ($data['total_quantity'] <= 0) {
            jsonResponse(false, '红包数量必须大于 0');
        }

        // 重新计算剩余数量和总金额
        $data['remaining_quantity'] = $data['total_quantity'];
        $data['total_amount'] = $data['amount_per_packet'] * $data['total_quantity'];

        // 重新计算有效期
        if ($data['valid_hours'] > 0) {
            $data['valid_end'] = date('Y-m-d H:i:s', strtotime('+' . $data['valid_hours'] . ' hours'));
        } else {
            $data['valid_end'] = date('Y-m-d H:i:s', strtotime('+100 years'));
        }

        $result = $redpacket->update($id, $data, $_SESSION['user_id']);
        jsonResponse($result['success'], $result['message'] ?? '');
        break;
        
    case 'cancel':
        $id = intval($_POST['id'] ?? 0);
        if (!$id) {
            jsonResponse(false, '红包 ID 不能为空');
        }

        // 检查权限
        $existing = $redpacket->getById($id);
        if (!$existing) {
            jsonResponse(false, '红包不存在');
        }
        if ($_SESSION['user_role'] !== 'admin' && $existing['created_by'] != $_SESSION['user_id']) {
            jsonResponse(false, '无权作废此红包');
        }

        $result = $redpacket->cancel($id, $_SESSION['user_id']);
        jsonResponse($result['success'], $result['message'] ?? '');
        break;

    case 'terminate':
        // 终止红包（提前结束多次领取）
        $id = intval($_POST['id'] ?? 0);
        if (!$id) {
            jsonResponse(false, '红包 ID 不能为空');
        }

        // 检查权限
        $existing = $redpacket->getById($id);
        if (!$existing) {
            jsonResponse(false, '红包不存在');
        }
        if ($_SESSION['user_role'] !== 'admin' && $existing['created_by'] != $_SESSION['user_id']) {
            jsonResponse(false, '无权终止此红包');
        }

        $result = $redpacket->terminate($id, $_SESSION['user_id']);
        jsonResponse($result['success'], $result['message'] ?? '');
        break;

    case 'delete':
        // 删除红包
        $id = intval($_POST['id'] ?? 0);
        if (!$id) {
            jsonResponse(false, '红包 ID 不能为空');
        }

        // 检查权限
        $existing = $redpacket->getById($id);
        if (!$existing) {
            jsonResponse(false, '红包不存在');
        }
        if ($_SESSION['user_role'] !== 'admin' && $existing['created_by'] != $_SESSION['user_id']) {
            jsonResponse(false, '无权删除此红包');
        }

        $result = $redpacket->delete($id, $_SESSION['user_id']);
        jsonResponse($result['success'], $result['message'] ?? '');
        break;

    default:
        jsonResponse(false, '未知操作');
        break;
}
