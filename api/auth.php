<?php
/**
 * 用户认证API
 */
require_once '../config.php';

header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? '';
$user = new User();

switch ($action) {
    case 'login':
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        
        if (empty($username) || empty($password)) {
            jsonResponse(false, '请输入用户名和密码');
        }
        
        $result = $user->login($username, $password);
        jsonResponse($result['success'], $result['message'] ?? '', $result['user'] ?? null);
        break;
        
    case 'logout':
        $result = $user->logout();
        // 如果是页面跳转过来的，重定向到登录页
        if (isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], '/admin/') !== false) {
            header('Location: /admin/login.php');
            exit;
        }
        jsonResponse(true, '已退出登录');
        break;
        
    case 'info':
        checkAuth();
        $currentUser = User::getCurrentUser();
        jsonResponse(true, '', $currentUser);
        break;
        
    case 'change_password':
        checkAuth();
        $oldPassword = $_POST['old_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        
        if (empty($oldPassword) || empty($newPassword)) {
            jsonResponse(false, '请输入原密码和新密码');
        }
        
        if (strlen($newPassword) < 6) {
            jsonResponse(false, '新密码长度不能少于6位');
        }
        
        $result = $user->changePassword($_SESSION['user_id'], $oldPassword, $newPassword);
        jsonResponse($result['success'], $result['message'] ?? '');
        break;
        
    default:
        jsonResponse(false, '未知操作');
}
