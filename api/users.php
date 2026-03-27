<?php
/**
 * 用户管理API
 */
require_once '../config.php';

checkAdmin();

header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? '';
$user = new User();

switch ($action) {
    case 'list':
        $page = intval($_GET['page'] ?? 1);
        $filters = [
            'role' => $_GET['role'] ?? '',
            'status' => $_GET['status'] ?? '',
            'keyword' => $_GET['keyword'] ?? ''
        ];
        
        $result = $user->getList($filters, $page);
        jsonResponse(true, '', $result);
        break;
        
    case 'get':
        $id = intval($_GET['id'] ?? 0);
        if (!$id) {
            jsonResponse(false, '用户ID不能为空');
        }
        
        $userInfo = $user->getById($id);
        if (!$userInfo) {
            jsonResponse(false, '用户不存在');
        }
        
        jsonResponse(true, '', $userInfo);
        break;
        
    case 'create':
        $data = [
            'username' => $_POST['username'] ?? '',
            'password' => $_POST['password'] ?? '',
            'real_name' => $_POST['real_name'] ?? '',
            'role' => $_POST['role'] ?? 'staff',
            'phone' => $_POST['phone'] ?? '',
            'email' => $_POST['email'] ?? ''
        ];
        
        if (empty($data['username']) || empty($data['password']) || empty($data['real_name'])) {
            jsonResponse(false, '请填写完整信息');
        }
        
        if (strlen($data['password']) < 6) {
            jsonResponse(false, '密码长度不能少于6位');
        }
        
        $result = $user->create($data, $_SESSION['user_id']);
        jsonResponse($result['success'], $result['message'] ?? '', ['id' => $result['id'] ?? null]);
        break;
        
    case 'update':
        $id = intval($_POST['id'] ?? 0);
        if (!$id) {
            jsonResponse(false, '用户ID不能为空');
        }
        
        $data = [
            'real_name' => $_POST['real_name'] ?? '',
            'role' => $_POST['role'] ?? '',
            'phone' => $_POST['phone'] ?? '',
            'email' => $_POST['email'] ?? '',
            'status' => $_POST['status'] ?? ''
        ];
        
        // 过滤空值
        $data = array_filter($data, function($v) { return $v !== ''; });
        
        if (!empty($_POST['password'])) {
            if (strlen($_POST['password']) < 6) {
                jsonResponse(false, '密码长度不能少于6位');
            }
            $data['password'] = $_POST['password'];
        }
        
        $result = $user->update($id, $data, $_SESSION['user_id']);
        jsonResponse($result['success'], $result['message'] ?? '');
        break;
        
    case 'delete':
        $id = intval($_POST['id'] ?? 0);
        if (!$id) {
            jsonResponse(false, '用户ID不能为空');
        }

        $result = $user->delete($id, $_SESSION['user_id']);
        jsonResponse($result['success'], $result['message'] ?? '');
        break;

    case 'toggle_status':
        $id = intval($_POST['id'] ?? 0);
        $status = intval($_POST['status'] ?? 1);

        if (!$id) {
            jsonResponse(false, '用户ID不能为空');
        }

        // 不能禁用自己
        if ($id == $_SESSION['user_id']) {
            jsonResponse(false, '不能禁用当前登录用户');
        }

        $result = $user->update($id, ['status' => $status], $_SESSION['user_id']);
        jsonResponse($result['success'], $result['message'] ?? '');
        break;

    default:
        jsonResponse(false, '未知操作');
}
