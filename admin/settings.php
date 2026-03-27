<?php
/**
 * 系统设置页面（仅管理员）
 */
require_once '../config.php';
checkAdmin();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>系统设置 - <?php echo SITE_NAME; ?></title>
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
        

        
        .content { padding: 30px; }
        
        .form-container {
            background: white;
            border-radius: 16px;
            padding: 40px;
            max-width: 800px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.04);
        }
        
        .form-section {
            margin-bottom: 32px;
        }
        
        .form-section-title {
            font-size: 16px;
            font-weight: 600;
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group.full-width {
            grid-column: 1 / -1;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-size: 14px;
            color: #555;
            font-weight: 500;
        }
        
        .form-group input,
        .form-group textarea {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #e8e8e8;
            border-radius: 10px;
            font-size: 14px;
            transition: all 0.3s;
            font-family: inherit;
        }
        
        .form-group input:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }
        
        .form-group .help-text {
            font-size: 12px;
            color: #999;
            margin-top: 6px;
        }
        
        .form-actions {
            display: flex;
            gap: 16px;
            justify-content: flex-end;
            padding-top: 20px;
            border-top: 1px solid #f0f0f0;
        }
        
        .btn {
            padding: 12px 32px;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
        }
        
        .btn-primary:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }
        
        .btn-secondary {
            background: #f5f5f5;
            color: #666;
        }
        
        .btn-secondary:hover { background: #e8e8e8; }
        
        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: none;
        }
        
        .alert.success {
            background: #f6ffed;
            border: 1px solid #b7eb8f;
            color: #52c41a;
            display: block;
        }
        
        .alert.error {
            background: #fff2f0;
            border: 1px solid #ffccc7;
            color: #ff4d4f;
            display: block;
        }
    </style>
</head>
<body>
    <?php require_once 'includes/sidebar.php'; ?>
    
    <main class="main-content">
        <header class="topbar">
            <h1 class="topbar-title">系统设置</h1>
        </header>
        
        <div class="content">
            <div class="form-container">
                <div class="alert" id="alert"></div>
                
                <form id="settingsForm">
                    <!-- 微信支付配置 -->
                    <div class="form-section">
                        <h3 class="form-section-title">微信支付配置</h3>
                        <div class="form-row">
                            <div class="form-group">
                                <label>公众号 AppID</label>
                                <input type="text" name="wechat_appid" id="wechat_appid">
                                <div class="help-text">微信服务号的 AppID</div>
                            </div>
                            <div class="form-group">
                                <label>公众号 AppSecret</label>
                                <input type="password" name="wechat_appsecret" id="wechat_appsecret">
                                <div class="help-text">用于获取用户 OpenID</div>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>商户号 (MCHID)</label>
                                <input type="text" name="wechat_mchid" id="wechat_mchid">
                            </div>
                            <div class="form-group">
                                <label>APIv3密钥</label>
                                <input type="password" name="wechat_apiv3key" id="wechat_apiv3key">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>证书序列号</label>
                                <input type="text" name="wechat_serial_no" id="wechat_serial_no">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group full-width">
                                <label>私钥证书路径</label>
                                <input type="text" name="wechat_private_key_path" id="wechat_private_key_path" placeholder="例如：/cert/apiclient_key.pem">
                                <div class="help-text">请将微信支付API证书上传到服务器cert目录</div>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group full-width">
                                <label>公钥证书路径</label>
                                <input type="text" name="wechat_public_key_path" id="wechat_public_key_path" placeholder="例如：/cert/apiclient_cert.pem">
                            </div>
                        </div>
                    </div>
                    
                    <!-- 网站配置 -->
                    <div class="form-section">
                        <h3 class="form-section-title">网站配置</h3>
                        <div class="form-row">
                            <div class="form-group">
                                <label>网站名称</label>
                                <input type="text" name="site_name" id="site_name">
                            </div>
                            <div class="form-group">
                                <label>联系邮箱</label>
                                <input type="email" name="contact_email" id="contact_email">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>联系电话</label>
                                <input type="text" name="contact_phone" id="contact_phone">
                            </div>
                        </div>
                    </div>
                    
                    <!-- 消息配置 -->
                    <div class="form-section">
                        <h3 class="form-section-title">消息配置</h3>
                        <div class="form-row">
                            <div class="form-group full-width">
                                <label>领取成功提示</label>
                                <textarea name="claim_success_message" id="claim_success_message"></textarea>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group full-width">
                                <label>领取失败提示</label>
                                <textarea name="claim_fail_message" id="claim_fail_message"></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary" id="saveBtn">保存设置</button>
                    </div>
                </form>
            </div>
        </div>
    </main>
    
    <script>
        // 加载设置
        async function loadSettings() {
            try {
                const response = await fetch('/api/settings.php?action=get');
                const result = await response.json();
                
                if (result.success) {
                    const settings = result.data;
                    for (const [key, value] of Object.entries(settings)) {
                        const input = document.getElementById(key);
                        if (input) {
                            input.value = value.value || '';
                        }
                    }
                }
            } catch (error) {
                showAlert('加载设置失败', 'error');
            }
        }
        
        // 保存设置
        document.getElementById('settingsForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const btn = document.getElementById('saveBtn');
            btn.disabled = true;
            btn.textContent = '保存中...';
            
            const formData = new FormData(this);
            const settings = {};
            
            for (const [key, value] of formData.entries()) {
                settings[key] = value;
            }
            
            try {
                const response = await fetch('/api/settings.php?action=save', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'settings=' + encodeURIComponent(JSON.stringify(settings))
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showAlert('设置已保存', 'success');
                } else {
                    showAlert(result.message || '保存失败', 'error');
                }
            } catch (error) {
                showAlert('保存失败，请重试', 'error');
            } finally {
                btn.disabled = false;
                btn.textContent = '保存设置';
            }
        });
        
        // 显示提示
        function showAlert(message, type) {
            const alert = document.getElementById('alert');
            alert.textContent = message;
            alert.className = 'alert ' + type;
            
            setTimeout(() => {
                alert.className = 'alert';
            }, 3000);
        }
        
        // 退出登录
        async function logout() {
            if (!confirm('确定要退出登录吗？')) return;
            
            try {
                const response = await fetch('/api/auth.php?action=logout');
                const result = await response.json();
                
                if (result.success) {
                    window.location.href = '/admin/login.php';
                }
            } catch (error) {
                alert('退出失败');
            }
        }
        
        // 初始化加载
        loadSettings();
    </script>
</body>
</html>
