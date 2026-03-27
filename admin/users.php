<?php
/**
 * 用户管理页面（仅管理员）
 */
require_once '../config.php';
checkAdmin();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>用户管理 - <?php echo SITE_NAME; ?></title>
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
        
        /* 筛选栏 */
        .filter-bar {
            background: white;
            border-radius: 12px;
            padding: 20px 24px;
            margin-bottom: 24px;
            display: flex;
            gap: 16px;
            flex-wrap: wrap;
            align-items: center;
        }
        
        .filter-bar input, .filter-bar select {
            padding: 10px 16px;
            border: 1px solid #e8e8e8;
            border-radius: 8px;
            font-size: 14px;
            outline: none;
        }
        
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

        .btn-warning {
            background: #faad14;
            color: white;
        }

        .btn-success {
            background: #52c41a;
            color: white;
        }

        .btn-sm { padding: 6px 12px; font-size: 12px; }
        
        /* 表格 */
        .panel {
            background: white;
            border-radius: 16px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.04);
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .data-table th, .data-table td {
            padding: 16px 20px;
            text-align: left;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .data-table th {
            font-weight: 600;
            color: #666;
            font-size: 13px;
            background: #fafafa;
        }
        
        .data-table td { font-size: 14px; color: #333; }
        
        .data-table tr:hover td { background: #fafafa; }
        
        .role-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .role-badge.admin { background: #e6f7ff; color: #1890ff; }
        .role-badge.staff { background: #f6ffed; color: #52c41a; }
        
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .status-badge.active { background: #f6ffed; color: #52c41a; }
        .status-badge.inactive { background: #fff2f0; color: #ff4d4f; }
        
        .actions { display: flex; gap: 8px; }
        
        /* 分页 */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 8px;
            padding: 20px;
        }
        
        .pagination a, .pagination span {
            padding: 8px 14px;
            border-radius: 8px;
            font-size: 14px;
            text-decoration: none;
            color: #666;
            background: #f5f5f5;
            transition: all 0.3s;
        }
        
        .pagination a:hover { background: #667eea; color: white; }
        
        .pagination .current {
            background: #667eea;
            color: white;
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
        
        .form-group label .required {
            color: #ff4d4f;
            margin-left: 4px;
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
        
        .form-actions {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            margin-top: 24px;
        }
    </style>
</head>
<body>
    <?php require_once 'includes/sidebar.php'; ?>
    
    <main class="main-content">
        <header class="topbar">
            <h1 class="topbar-title">用户管理</h1>
        </header>
        
        <div class="content">
            <!-- 筛选栏 -->
            <div class="filter-bar">
                <input type="text" id="keyword" placeholder="搜索用户名或姓名">
                <select id="role">
                    <option value="">全部角色</option>
                    <option value="admin">管理员</option>
                    <option value="staff">员工</option>
                </select>
                <select id="status">
                    <option value="">全部状态</option>
                    <option value="1">启用</option>
                    <option value="0">禁用</option>
                </select>
                <button class="btn btn-primary" onclick="loadUsers()">搜索</button>
                <a href="/admin/user_stats.php" class="btn btn-secondary">发放统计</a>
                <button class="btn btn-primary" onclick="openModal()">添加用户</button>
            </div>
            
            <!-- 用户列表 -->
            <div class="panel">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>用户名</th>
                            <th>真实姓名</th>
                            <th>角色</th>
                            <th>手机号</th>
                            <th>状态</th>
                            <th>最后登录</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody id="userList">
                        <!-- 动态加载 -->
                    </tbody>
                </table>
                
                <div class="pagination" id="pagination">
                    <!-- 动态加载 -->
                </div>
            </div>
        </div>
    </main>
    
    <!-- 用户弹窗 -->
    <div class="modal-overlay" id="userModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="modalTitle">添加用户</h3>
                <button class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            <form id="userForm">
                <input type="hidden" name="id" id="userId">
                <div class="form-group">
                    <label>用户名 <span class="required">*</span></label>
                    <input type="text" name="username" id="username" required>
                </div>
                <div class="form-group">
                    <label>真实姓名 <span class="required">*</span></label>
                    <input type="text" name="real_name" id="realName" required>
                    <small style="color: #999;">将显示在红包发放记录中</small>
                </div>
                <div class="form-group">
                    <label>密码 <span class="required" id="passwordRequired">*</span></label>
                    <input type="password" name="password" id="password">
                    <small style="color: #999;" id="passwordHint">不填则保持不变</small>
                </div>
                <div class="form-group">
                    <label>角色 <span class="required">*</span></label>
                    <select name="role" id="roleInput" required>
                        <option value="staff">员工</option>
                        <option value="admin">管理员</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>手机号</label>
                    <input type="tel" name="phone" id="phone">
                </div>
                <div class="form-group">
                    <label>邮箱</label>
                    <input type="email" name="email" id="email">
                </div>
                <div class="form-group">
                    <label>状态</label>
                    <select name="status" id="statusInput">
                        <option value="1">启用</option>
                        <option value="0">禁用</option>
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
        let currentPage = 1;
        let editingId = null;
        
        // 加载用户列表
        async function loadUsers(page = 1) {
            currentPage = page;
            const keyword = document.getElementById('keyword').value;
            const role = document.getElementById('role').value;
            const status = document.getElementById('status').value;
            
            try {
                const response = await fetch(`/api/users.php?action=list&page=${page}&keyword=${encodeURIComponent(keyword)}&role=${role}&status=${status}`);
                const result = await response.json();
                
                if (result.success) {
                    renderUsers(result.data.list);
                    renderPagination(result.data.pagination);
                }
            } catch (error) {
                console.error('加载失败:', error);
            }
        }
        
        // 渲染用户列表
        function renderUsers(list) {
            const tbody = document.getElementById('userList');
            
            if (list.length === 0) {
                tbody.innerHTML = '<tr><td colspan="8" style="text-align: center; color: #999;">暂无数据</td></tr>';
                return;
            }
            
            tbody.innerHTML = list.map(item => `
                <tr>
                    <td>${item.id}</td>
                    <td>${item.username}</td>
                    <td>${item.real_name}</td>
                    <td><span class="role-badge ${item.role}">${item.role === 'admin' ? '管理员' : '员工'}</span></td>
                    <td>${item.phone || '-'}</td>
                    <td><span class="status-badge ${item.status == 1 ? 'active' : 'inactive'}">${item.status == 1 ? '启用' : '禁用'}</span></td>
                    <td>${item.last_login_at ? new Date(item.last_login_at).toLocaleString() : '从未登录'}</td>
                    <td>
                        <div class="actions">
                            <button class="btn btn-secondary btn-sm" onclick="editUser(${item.id})">编辑</button>
                            ${item.id != <?php echo $_SESSION['user_id']; ?> ? `
                                <button class="btn ${item.status == 1 ? 'btn-warning' : 'btn-success'} btn-sm" onclick="toggleStatus(${item.id}, ${item.status})">${item.status == 1 ? '禁用' : '启用'}</button>
                                <button class="btn btn-danger btn-sm" onclick="deleteUser(${item.id})">删除</button>
                            ` : ''}
                        </div>
                    </td>
                </tr>
            `).join('');
        }
        
        // 渲染分页
        function renderPagination(pagination) {
            const container = document.getElementById('pagination');
            let html = '';
            
            if (pagination.has_prev) {
                html += `<a href="javascript:;" onclick="loadUsers(${pagination.page - 1})">上一页</a>`;
            }
            
            for (let i = 1; i <= pagination.total_pages; i++) {
                if (i === pagination.page) {
                    html += `<span class="current">${i}</span>`;
                } else {
                    html += `<a href="javascript:;" onclick="loadUsers(${i})">${i}</a>`;
                }
            }
            
            if (pagination.has_next) {
                html += `<a href="javascript:;" onclick="loadUsers(${pagination.page + 1})">下一页</a>`;
            }
            
            container.innerHTML = html;
        }
        
        // 打开弹窗
        function openModal() {
            editingId = null;
            document.getElementById('modalTitle').textContent = '添加用户';
            document.getElementById('userForm').reset();
            document.getElementById('userId').value = '';
            document.getElementById('username').disabled = false;
            document.getElementById('passwordRequired').style.display = 'inline';
            document.getElementById('passwordHint').style.display = 'none';
            document.getElementById('password').required = true;
            document.getElementById('userModal').classList.add('active');
        }
        
        // 编辑用户
        async function editUser(id) {
            editingId = id;
            document.getElementById('modalTitle').textContent = '编辑用户';
            document.getElementById('username').disabled = true;
            document.getElementById('passwordRequired').style.display = 'none';
            document.getElementById('passwordHint').style.display = 'inline';
            document.getElementById('password').required = false;
            
            try {
                const response = await fetch(`/api/users.php?action=get&id=${id}`);
                const result = await response.json();
                
                if (result.success) {
                    const user = result.data;
                    document.getElementById('userId').value = user.id;
                    document.getElementById('username').value = user.username;
                    document.getElementById('realName').value = user.real_name;
                    document.getElementById('roleInput').value = user.role;
                    document.getElementById('phone').value = user.phone || '';
                    document.getElementById('email').value = user.email || '';
                    document.getElementById('statusInput').value = user.status;
                    document.getElementById('userModal').classList.add('active');
                }
            } catch (error) {
                alert('加载用户信息失败');
            }
        }
        
        // 关闭弹窗
        function closeModal() {
            document.getElementById('userModal').classList.remove('active');
        }
        
        // 提交表单
        document.getElementById('userForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const action = editingId ? 'update' : 'create';
            if (editingId) {
                formData.append('id', editingId);
            }
            
            try {
                const response = await fetch(`/api/users.php?action=${action}`, {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    closeModal();
                    loadUsers(currentPage);
                } else {
                    alert(result.message || '保存失败');
                }
            } catch (error) {
                alert('保存失败，请重试');
            }
        });
        
        // 删除用户
        async function deleteUser(id) {
            if (!confirm('确定要删除这个用户吗？此操作不可恢复。')) return;

            try {
                const response = await fetch('/api/users.php?action=delete', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'id=' + id
                });

                const result = await response.json();

                if (result.success) {
                    loadUsers(currentPage);
                } else {
                    alert(result.message || '删除失败');
                }
            } catch (error) {
                alert('删除失败，请重试');
            }
        }

        // 切换用户状态（启用/禁用）
        async function toggleStatus(id, currentStatus) {
            const newStatus = currentStatus == 1 ? 0 : 1;
            const actionText = newStatus == 1 ? '启用' : '禁用';

            if (!confirm(`确定要${actionText}这个用户吗？`)) return;

            try {
                const response = await fetch('/api/users.php?action=toggle_status', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'id=' + id + '&status=' + newStatus
                });

                const result = await response.json();

                if (result.success) {
                    loadUsers(currentPage);
                } else {
                    alert(result.message || '操作失败');
                }
            } catch (error) {
                alert('操作失败，请重试');
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
        
        // 初始化加载
        loadUsers();
    </script>
</body>
</html>
