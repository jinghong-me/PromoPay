<?php
/**
 * 后台登录页面
 */
require_once '../config.php';

// 已登录则跳转
if (isset($_SESSION['user_id'])) {
    redirect('/admin/');
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>登录 - <?php echo SITE_NAME; ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'PingFang SC', 'Hiragino Sans GB', 'Microsoft YaHei', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .login-container {
            background: white;
            border-radius: 20px;
            padding: 50px 40px;
            width: 100%;
            max-width: 420px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .login-header .logo {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #FF6B6B 0%, #FF8E53 100%);
            border-radius: 20px;
            margin: 0 auto 20px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .login-header .logo svg {
            width: 45px;
            height: 45px;
            fill: white;
        }
        
        .login-header h1 {
            font-size: 28px;
            color: #333;
            margin-bottom: 8px;
        }
        
        .login-header p {
            color: #999;
            font-size: 14px;
        }
        
        .form-group {
            margin-bottom: 24px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-size: 14px;
            color: #555;
            font-weight: 500;
        }
        
        .form-group input {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #e8e8e8;
            border-radius: 12px;
            font-size: 15px;
            transition: all 0.3s;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .form-group input::placeholder {
            color: #bbb;
        }
        
        .login-btn {
            width: 100%;
            padding: 16px;
            font-size: 16px;
            font-weight: 600;
            color: white;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
        }
        
        .login-btn:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }
        
        .error-message {
            background: #fff2f0;
            border: 1px solid #ffccc7;
            color: #ff4d4f;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            display: none;
        }
        
        .error-message.show {
            display: block;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <div class="logo">
                <svg viewBox="0 0 24 24">
                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                </svg>
            </div>
            <h1><?php echo SITE_NAME; ?></h1>
            <p>请登录您的账户</p>
        </div>
        
        <div class="error-message" id="errorMsg"></div>
        
        <form id="loginForm">
            <div class="form-group">
                <label>用户名</label>
                <input type="text" name="username" id="username" placeholder="请输入用户名" required>
            </div>
            
            <div class="form-group">
                <label>密码</label>
                <input type="password" name="password" id="password" placeholder="请输入密码" required>
            </div>
            
            <button type="submit" class="login-btn" id="loginBtn">登录</button>
        </form>
    </div>
    
    <script>
        document.getElementById('loginForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const btn = document.getElementById('loginBtn');
            const errorMsg = document.getElementById('errorMsg');
            const username = document.getElementById('username').value;
            const password = document.getElementById('password').value;
            
            btn.disabled = true;
            btn.textContent = '登录中...';
            errorMsg.classList.remove('show');
            
            try {
                const response = await fetch('/api/auth.php?action=login', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: 'username=' + encodeURIComponent(username) + '&password=' + encodeURIComponent(password)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    window.location.href = '/admin/';
                } else {
                    errorMsg.textContent = result.message || '登录失败';
                    errorMsg.classList.add('show');
                    btn.disabled = false;
                    btn.textContent = '登录';
                }
            } catch (error) {
                errorMsg.textContent = '网络错误，请稍后重试';
                errorMsg.classList.add('show');
                btn.disabled = false;
                btn.textContent = '登录';
            }
        });
    </script>
</body>
</html>
