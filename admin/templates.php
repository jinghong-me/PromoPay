<?php
/**
 * 模板管理页面（仅管理员）
 */
require_once '../config.php';
checkAdmin();

$db = Database::getInstance();
$templates = $db->fetchAll("SELECT * FROM redpacket_templates ORDER BY is_default DESC, id ASC");
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>模板管理 - <?php echo SITE_NAME; ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'PingFang SC', 'Hiragino Sans GB', 'Microsoft YaHei', sans-serif;
            background: #f5f7fa;
            min-height: 100vh;
        }
        
        /* 复用侧边栏样式 */
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            bottom: 0;
            width: 260px;
            background: linear-gradient(180deg, #1a1f37 0%, #252b48 100%);
            color: white;
            z-index: 100;
            overflow-y: auto;
        }
        
        .sidebar-header {
            padding: 30px 24px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .sidebar-header .logo {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .sidebar-header .logo-icon {
            width: 45px;
            height: 45px;
            background: linear-gradient(135deg, #FF6B6B 0%, #FF8E53 100%);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .sidebar-header .logo-icon svg {
            width: 28px;
            height: 28px;
            fill: white;
        }
        
        .sidebar-header .logo-text {
            font-size: 20px;
            font-weight: 700;
        }
        
        .sidebar-header .logo-subtext {
            font-size: 12px;
            color: rgba(255,255,255,0.6);
            margin-top: 2px;
        }
        
        .nav-menu { padding: 20px 0; }
        
        .nav-item {
            display: flex;
            align-items: center;
            padding: 14px 24px;
            color: rgba(255,255,255,0.7);
            text-decoration: none;
            transition: all 0.3s;
            cursor: pointer;
        }
        
        .nav-item:hover, .nav-item.active {
            background: rgba(255,255,255,0.1);
            color: white;
        }
        
        .nav-item.active {
            border-left: 3px solid #FF6B6B;
        }
        
        .nav-item svg {
            width: 22px;
            height: 22px;
            margin-right: 12px;
            fill: currentColor;
        }
        
        .nav-section-title {
            padding: 20px 24px 10px;
            font-size: 12px;
            color: rgba(255,255,255,0.4);
            text-transform: uppercase;
            letter-spacing: 1px;
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
        
        .topbar-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            padding: 8px 16px;
            border-radius: 8px;
            transition: background 0.3s;
        }
        
        .user-info:hover { background: #f5f5f5; }
        
        .user-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 14px;
        }
        
        .content { padding: 30px; }
        
        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            border: none;
            transition: all 0.3s;
            text-decoration: none;
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
        
        .btn-secondary {
            background: #f5f5f5;
            color: #666;
        }
        
        .btn-danger {
            background: #ff4d4f;
            color: white;
        }
        
        .btn-sm { padding: 6px 12px; font-size: 12px; }
        
        /* 模板网格 */
        .template-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 24px;
        }
        
        .template-card {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 2px 12px rgba(0,0,0,0.04);
            transition: all 0.3s;
        }
        
        .template-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.08);
        }
        
        .template-preview {
            height: 160px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 48px;
            font-weight: 700;
            position: relative;
        }
        
        .template-badge {
            position: absolute;
            top: 12px;
            right: 12px;
            background: rgba(255,255,255,0.9);
            color: #333;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .template-info {
            padding: 20px;
        }
        
        .template-name {
            font-size: 16px;
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
        }
        
        .template-type {
            font-size: 13px;
            color: #999;
            margin-bottom: 16px;
        }
        
        .template-actions {
            display: flex;
            gap: 8px;
        }
        
        /* 弹窗 */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.6);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }
        
        .modal-overlay.active { display: flex; }
        
        .modal-content {
            background: white;
            border-radius: 16px;
            padding: 30px;
            max-width: 500px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }
        
        .modal-title {
            font-size: 20px;
            font-weight: 600;
            color: #333;
        }
        
        .modal-close {
            background: none;
            border: none;
            font-size: 24px;
            color: #999;
            cursor: pointer;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-size: 14px;
            color: #555;
            font-weight: 500;
        }
        
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #e8e8e8;
            border-radius: 10px;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
        }
        
        .form-actions {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            margin-top: 24px;
        }
    </style>
</head>
<body>
    <!-- 侧边栏 -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <div class="logo">
                <div class="logo-icon">
                    <svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
                </div>
                <div>
                    <div class="logo-text"><?php echo SITE_NAME; ?></div>
                    <div class="logo-subtext">管理后台</div>
                </div>
            </div>
        </div>
        
        <nav class="nav-menu">
            <a href="/admin/" class="nav-item">
                <svg viewBox="0 0 24 24"><path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z"/></svg>
                首页概览
            </a>
            
            <div class="nav-section-title">红包管理</div>
            <a href="/admin/redpackets.php" class="nav-item">
                <svg viewBox="0 0 24 24"><path d="M20 4H4c-1.11 0-1.99.89-1.99 2L2 18c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V6c0-1.11-.89-2-2-2zm0 14H4v-6h16v6zm0-10H4V6h16v2z"/></svg>
                红包列表
            </a>
            <a href="/admin/redpacket_create.php" class="nav-item">
                <svg viewBox="0 0 24 24"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
                创建红包
            </a>
            <a href="/admin/claims.php" class="nav-item">
                <svg viewBox="0 0 24 24"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zM9 17H7v-7h2v7zm4 0h-2V7h2v10zm4 0h-2v-4h2v4z"/></svg>
                领取记录
            </a>
            
            <div class="nav-section-title">系统管理</div>
            <a href="/admin/users.php" class="nav-item">
                <svg viewBox="0 0 24 24"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>
                用户管理
            </a>
            <a href="/admin/templates.php" class="nav-item active">
                <svg viewBox="0 0 24 24"><path d="M12 3c-4.97 0-9 4.03-9 9s4.03 9 9 9c.83 0 1.5-.67 1.5-1.5 0-.39-.15-.74-.39-1.01-.23-.26-.38-.61-.38-.99 0-.83.67-1.5 1.5-1.5H16c2.76 0 5-2.24 5-5 0-4.42-4.03-8-9-8zm-5.5 9c-.83 0-1.5-.67-1.5-1.5S5.67 9 6.5 9 8 9.67 8 10.5 7.33 12 6.5 12zm3-4C8.67 8 8 7.33 8 6.5S8.67 5 9.5 5s1.5.67 1.5 1.5S10.33 8 9.5 8zm5 0c-.83 0-1.5-.67-1.5-1.5S13.67 5 14.5 5s1.5.67 1.5 1.5S15.33 8 14.5 8zm3 4c-.83 0-1.5-.67-1.5-1.5S16.67 9 17.5 9s1.5.67 1.5 1.5-.67 1.5-1.5 1.5z"/></svg>
                模板管理
            </a>
            <a href="/admin/settings.php" class="nav-item">
                <svg viewBox="0 0 24 24"><path d="M19.14 12.94c.04-.3.06-.61.06-.94 0-.32-.02-.64-.07-.94l2.03-1.58c.18-.14.23-.41.12-.61l-1.92-3.32c-.12-.22-.37-.29-.59-.22l-2.39.96c-.5-.38-1.03-.7-1.62-.94l-.36-2.54c-.04-.24-.24-.41-.48-.41h-3.84c-.24 0-.43.17-.47.41l-.36 2.54c-.59.24-1.13.57-1.62.94l-2.39-.96c-.22-.08-.47 0-.59.22L5.09 8.87c-.12.21-.08.47.12.61l2.03 1.58c-.05.3-.09.63-.09.94s.02.64.07.94l-2.03 1.58c-.18.14-.23.41-.12.61l1.92 3.32c.12.22.37.29.59.22l2.39-.96c.5.38 1.03.7 1.62.94l.36 2.54c.05.24.24.41.48.41h3.84c.24 0 .44-.17.47-.41l.36-2.54c.59-.24 1.13-.56 1.62-.94l2.39.96c.22.08.47 0 .59-.22l1.92-3.32c.12-.22.07-.47-.12-.61l-2.01-1.58zM12 15.6c-1.98 0-3.6-1.62-3.6-3.6s1.62-3.6 3.6-3.6 3.6 1.62 3.6 3.6-1.62 3.6-3.6 3.6z"/></svg>
                系统设置
            </a>
        </nav>
    </aside>
    
    <main class="main-content">
        <header class="topbar">
            <h1 class="topbar-title">模板管理</h1>
            <div class="topbar-right">
                <button class="btn btn-primary" onclick="openModal()">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
                    添加模板
                </button>
                <div class="user-info" onclick="logout()">
                    <div class="user-avatar"><?php echo mb_substr($_SESSION['user_realname'], 0, 1); ?></div>
                </div>
            </div>
        </header>
        
        <div class="content">
            <div class="template-grid">
                <?php foreach ($templates as $template): ?>
                <div class="template-card">
                    <div class="template-preview" style="background: <?php echo $template['bg_gradient'] ?: $template['bg_color']; ?>">
                        ¥
                        <?php if ($template['is_default']): ?>
                        <span class="template-badge">默认</span>
                        <?php endif; ?>
                    </div>
                    <div class="template-info">
                        <div class="template-name"><?php echo htmlspecialchars($template['name']); ?></div>
                        <div class="template-type">
                            <?php 
                            $typeText = ['wechat' => '微信风格', 'festive' => '喜庆风格', 'business' => '商务风格', 'custom' => '自定义'];
                            echo $typeText[$template['type']] ?? $template['type'];
                            ?>
                        </div>
                        <div class="template-actions">
                            <button class="btn btn-secondary btn-sm" onclick="editTemplate(<?php echo $template['id']; ?>)">编辑</button>
                            <?php if (!$template['is_default']): ?>
                            <button class="btn btn-danger btn-sm" onclick="deleteTemplate(<?php echo $template['id']; ?>)">删除</button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </main>
    
    <!-- 模板弹窗 -->
    <div class="modal-overlay" id="templateModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="modalTitle">添加模板</h3>
                <button class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            <form id="templateForm">
                <input type="hidden" name="id" id="templateId">
                <div class="form-group">
                    <label>模板名称 <span style="color: #ff4d4f;">*</span></label>
                    <input type="text" name="name" id="name" required>
                </div>
                <div class="form-group">
                    <label>模板类型</label>
                    <select name="type" id="type">
                        <option value="wechat">微信风格</option>
                        <option value="festive">喜庆风格</option>
                        <option value="business">商务风格</option>
                        <option value="custom">自定义</option>
                    </select>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>背景主色</label>
                        <input type="color" name="bg_color" id="bg_color" value="#FF6B6B">
                    </div>
                    <div class="form-group">
                        <label>标题颜色</label>
                        <input type="color" name="title_color" id="title_color" value="#FFFFFF">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>金额颜色</label>
                        <input type="color" name="amount_color" id="amount_color" value="#FFD700">
                    </div>
                    <div class="form-group">
                        <label>按钮颜色</label>
                        <input type="color" name="button_color" id="button_color" value="#FFD700">
                    </div>
                </div>
                <div class="form-group">
                    <label>按钮文字</label>
                    <input type="text" name="button_text" id="button_text" value="立即领取">
                </div>
                <div class="form-group">
                    <label>动画效果</label>
                    <select name="animation_type" id="animation_type">
                        <option value="none">无</option>
                        <option value="bounce">弹跳</option>
                        <option value="pulse">脉冲</option>
                        <option value="shake">摇摆</option>
                        <option value="fade">淡入淡出</option>
                    </select>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">取消</button>
                    <button type="submit" class="btn btn-primary">保存</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        let editingId = null;
        
        // 打开弹窗
        function openModal() {
            editingId = null;
            document.getElementById('modalTitle').textContent = '添加模板';
            document.getElementById('templateForm').reset();
            document.getElementById('templateId').value = '';
            document.getElementById('templateModal').classList.add('active');
        }
        
        // 编辑模板
        async function editTemplate(id) {
            editingId = id;
            document.getElementById('modalTitle').textContent = '编辑模板';
            
            try {
                const response = await fetch(`/api/templates.php?action=get&id=${id}`);
                const result = await response.json();
                
                if (result.success) {
                    const template = result.data;
                    document.getElementById('templateId').value = template.id;
                    document.getElementById('name').value = template.name;
                    document.getElementById('type').value = template.type;
                    document.getElementById('bg_color').value = template.bg_color;
                    document.getElementById('title_color').value = template.title_color;
                    document.getElementById('amount_color').value = template.amount_color;
                    document.getElementById('button_color').value = template.button_color;
                    document.getElementById('button_text').value = template.button_text;
                    document.getElementById('animation_type').value = template.animation_type;
                    document.getElementById('templateModal').classList.add('active');
                }
            } catch (error) {
                alert('加载模板信息失败');
            }
        }
        
        // 关闭弹窗
        function closeModal() {
            document.getElementById('templateModal').classList.remove('active');
        }
        
        // 提交表单
        document.getElementById('templateForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const action = editingId ? 'update' : 'create';
            if (editingId) {
                formData.append('id', editingId);
            }
            
            try {
                const response = await fetch(`/api/templates.php?action=${action}`, {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    closeModal();
                    location.reload();
                } else {
                    alert(result.message || '保存失败');
                }
            } catch (error) {
                alert('保存失败，请重试');
            }
        });
        
        // 删除模板
        async function deleteTemplate(id) {
            if (!confirm('确定要删除这个模板吗？')) return;
            
            try {
                const response = await fetch('/api/templates.php?action=delete', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'id=' + id
                });
                
                const result = await response.json();
                
                if (result.success) {
                    location.reload();
                } else {
                    alert(result.message || '删除失败');
                }
            } catch (error) {
                alert('删除失败，请重试');
            }
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
    </script>
</body>
</html>
