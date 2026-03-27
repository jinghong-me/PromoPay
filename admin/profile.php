<?php
/**
 * 个人中心页面
 */
require_once '../config.php';
checkAuth();

$db = Database::getInstance();
$currentUser = User::getCurrentUser();
$message = '';
$error = '';

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_profile') {
        // 更新个人信息
        $realName = trim($_POST['real_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        
        if (empty($realName)) {
            $error = '请输入真实姓名';
        } else {
            $data = [
                'real_name' => $realName,
                'email' => $email,
                'phone' => $phone,
                'updated_at' => date('Y-m-d H:i:s')
            ];
            
            $result = $db->update('users', $data, 'id = :id', ['id' => $currentUser['id']]);
            
            if ($result) {
                $message = '个人信息更新成功';
                // 更新 session 中的用户信息
                $_SESSION['user_realname'] = $realName;
                $currentUser = User::getCurrentUser();
            } else {
                $error = '更新失败，请重试';
            }
        }
    } elseif ($action === 'change_password') {
        // 修改密码
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        if (empty($currentPassword)) {
            $error = '请输入当前密码';
        } elseif (empty($newPassword)) {
            $error = '请输入新密码';
        } elseif (strlen($newPassword) < 6) {
            $error = '新密码长度不能少于6位';
        } elseif ($newPassword !== $confirmPassword) {
            $error = '两次输入的新密码不一致';
        } else {
            // 验证当前密码
            $user = $db->fetch("SELECT * FROM users WHERE id = ?", [$currentUser['id']]);
            
            if (!$user || !password_verify($currentPassword, $user['password'])) {
                $error = '当前密码错误';
            } else {
                // 更新密码
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                $result = $db->update('users', 
                    ['password' => $hashedPassword, 'updated_at' => date('Y-m-d H:i:s')], 
                    'id = :id', 
                    ['id' => $currentUser['id']]
                );
                
                if ($result) {
                    $message = '密码修改成功';
                } else {
                    $error = '密码修改失败，请重试';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>个人中心 - <?php echo SITE_NAME; ?></title>
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
        
        .content {
            padding: 30px;
            max-width: 800px;
        }
        
        /* 消息提示 */
        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .alert-success {
            background: #f6ffed;
            color: #52c41a;
            border: 1px solid #b7eb8f;
        }
        
        .alert-error {
            background: #fff2f0;
            color: #ff4d4f;
            border: 1px solid #ffccc7;
        }
        
        /* 卡片样式 */
        .card {
            background: white;
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }
        
        .card-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .card-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .card-icon.blue {
            background: #e6f7ff;
            color: #1890ff;
        }
        
        .card-icon.green {
            background: #f6ffed;
            color: #52c41a;
        }
        
        .card-icon svg {
            width: 20px;
            height: 20px;
            fill: currentColor;
        }
        
        .card-title {
            font-size: 16px;
            font-weight: 600;
            color: #333;
        }
        
        .card-subtitle {
            font-size: 13px;
            color: #999;
            margin-top: 2px;
        }
        
        /* 表单样式 */
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-size: 14px;
            color: #333;
            font-weight: 500;
        }
        
        .form-group input {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid #e8e8e8;
            border-radius: 8px;
            font-size: 14px;
            outline: none;
            transition: all 0.3s;
        }
        
        .form-group input:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .form-group input:read-only {
            background: #f5f5f5;
            color: #999;
        }
        
        .form-hint {
            font-size: 12px;
            color: #999;
            margin-top: 6px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        
        /* 按钮样式 */
        .btn {
            padding: 10px 24px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            border: none;
            transition: all 0.3s;
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
        
        /* 用户信息展示 */
        .user-info-display {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 20px;
            background: #fafafa;
            border-radius: 10px;
            margin-bottom: 24px;
        }
        
        .user-avatar-large {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 24px;
        }
        
        .user-info-text .user-name {
            font-size: 18px;
            font-weight: 600;
            color: #333;
        }
        
        .user-info-text .user-role {
            font-size: 13px;
            color: #999;
            margin-top: 4px;
        }
        
        .user-info-text .user-username {
            font-size: 13px;
            color: #666;
            margin-top: 2px;
        }
        
        /* 分隔线 */
        .divider {
            height: 1px;
            background: #f0f0f0;
            margin: 24px 0;
        }
    </style>
</head>
<body>
    <?php require_once 'includes/sidebar.php'; ?>

    <main class="main-content">
        <header class="topbar">
            <h1 class="topbar-title">个人中心</h1>
        </header>

        <div class="content">
            <?php if ($message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <!-- 个人信息卡片 -->
            <div class="card">
                <div class="user-info-display">
                    <div class="user-avatar-large"><?php echo mb_substr($currentUser['real_name'], 0, 1); ?></div>
                    <div class="user-info-text">
                        <div class="user-name"><?php echo htmlspecialchars($currentUser['real_name']); ?></div>
                        <div class="user-role"><?php echo $currentUser['role'] === 'admin' ? '系统管理员' : '员工'; ?></div>
                        <div class="user-username">用户名：<?php echo htmlspecialchars($currentUser['username']); ?></div>
                    </div>
                </div>

                <div class="card-header">
                    <div class="card-icon blue">
                        <svg viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                    </div>
                    <div>
                        <div class="card-title">基本信息</div>
                        <div class="card-subtitle">修改您的个人资料</div>
                    </div>
                </div>

                <form method="post">
                    <input type="hidden" name="action" value="update_profile">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>真实姓名 <span style="color: #ff4d4f;">*</span></label>
                            <input type="text" name="real_name" value="<?php echo htmlspecialchars($currentUser['real_name']); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>用户名</label>
                            <input type="text" value="<?php echo htmlspecialchars($currentUser['username']); ?>" readonly>
                            <div class="form-hint">用户名不可修改</div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>电子邮箱</label>
                            <input type="email" name="email" value="<?php echo htmlspecialchars($currentUser['email'] ?? ''); ?>" placeholder="请输入邮箱">
                        </div>
                        <div class="form-group">
                            <label>手机号码</label>
                            <input type="tel" name="phone" value="<?php echo htmlspecialchars($currentUser['phone'] ?? ''); ?>" placeholder="请输入手机号">
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary">保存修改</button>
                </form>
            </div>

            <!-- 修改密码卡片 -->
            <div class="card">
                <div class="card-header">
                    <div class="card-icon green">
                        <svg viewBox="0 0 24 24"><path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/></svg>
                    </div>
                    <div>
                        <div class="card-title">修改密码</div>
                        <div class="card-subtitle">定期更换密码可以保护账户安全</div>
                    </div>
                </div>

                <form method="post">
                    <input type="hidden" name="action" value="change_password">
                    
                    <div class="form-group">
                        <label>当前密码 <span style="color: #ff4d4f;">*</span></label>
                        <input type="password" name="current_password" placeholder="请输入当前密码" required>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>新密码 <span style="color: #ff4d4f;">*</span></label>
                            <input type="password" name="new_password" placeholder="请输入新密码（至少6位）" required minlength="6">
                        </div>
                        <div class="form-group">
                            <label>确认新密码 <span style="color: #ff4d4f;">*</span></label>
                            <input type="password" name="confirm_password" placeholder="请再次输入新密码" required minlength="6">
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary">修改密码</button>
                </form>
            </div>
        </div>
    </main>
</body>
</html>
