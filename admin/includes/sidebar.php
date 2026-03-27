<?php
/**
 * 通用侧边栏组件
 */
if (!isset($currentUser)) {
    $currentUser = User::getCurrentUser();
}
?>
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
        <a href="/admin/" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) === 'index.php' ? 'active' : ''; ?>">
            <svg viewBox="0 0 24 24"><path d="M3 13h8V3H3v10zm0 8h8v-6H3v6zm10 0h8V11h-8v10zm0-18v6h8V3h-8z"/></svg>
            首页概览
        </a>

        <div class="nav-section-title">红包管理</div>
        <a href="/admin/redpackets.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) === 'redpackets.php' ? 'active' : ''; ?>">
            <svg viewBox="0 0 24 24"><path d="M20 4H4c-1.11 0-1.99.89-1.99 2L2 18c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V6c0-1.11-.89-2-2-2zm0 14H4v-6h16v6zm0-10H4V6h16v2z"/></svg>
            红包列表
        </a>
        <a href="/admin/redpacket_create.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) === 'redpacket_create.php' ? 'active' : ''; ?>">
            <svg viewBox="0 0 24 24"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/></svg>
            创建红包
        </a>
        <a href="/admin/claims.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) === 'claims.php' ? 'active' : ''; ?>">
            <svg viewBox="0 0 24 24"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zM9 17H7v-7h2v7zm4 0h-2V7h2v10zm4 0h-2v-4h2v4z"/></svg>
            领取记录
        </a>

        <?php if ($currentUser['role'] === 'admin'): ?>
        <div class="nav-section-title">系统管理</div>
        <a href="/admin/users.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) === 'users.php' ? 'active' : ''; ?>">
            <svg viewBox="0 0 24 24"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>
            用户管理
        </a>
        <a href="/admin/user_stats.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) === 'user_stats.php' ? 'active' : ''; ?>">
            <svg viewBox="0 0 24 24"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zM9 17H7v-7h2v7zm4 0h-2V7h2v10zm4 0h-2v-4h2v4z"/></svg>
            发放统计
        </a>
        <a href="/admin/settings.php" class="nav-item <?php echo basename($_SERVER['PHP_SELF']) === 'settings.php' ? 'active' : ''; ?>">
            <svg viewBox="0 0 24 24"><path d="M19.14 12.94c.04-.3.06-.61.06-.94 0-.32-.02-.64-.07-.94l2.03-1.58c.18-.14.23-.41.12-.61l-1.92-3.32c-.12-.22-.37-.29-.59-.22l-2.39.96c-.5-.38-1.03-.7-1.62-.94l-.36-2.54c-.04-.24-.24-.41-.48-.41h-3.84c-.24 0-.43.17-.47.41l-.36 2.54c-.59.24-1.13.57-1.62.94l-2.39-.96c-.22-.08-.47 0-.59.22L5.09 8.87c-.12.21-.08.47.12.61l2.03 1.58c-.05.3-.09.63-.09.94s.02.64.07.94l-2.03 1.58c-.18.14-.23.41-.12.61l1.92 3.32c.12-.22.37-.29.59-.22l2.39-.96c.5.38 1.03.7 1.62.94l.36 2.54c.05.24.24.41.48.41h3.84c.24 0 .44-.17.47-.41l.36-2.54c.59-.24 1.13-.56 1.62-.94l2.39.96c.22.08.47 0 .59-.22l1.92-3.32c.12-.22.07-.47-.12-.61l-2.01-1.58zM12 15.6c-1.98 0-3.6-1.62-3.6-3.6s1.62-3.6 3.6-3.6 3.6 1.62 3.6 3.6-1.62 3.6-3.6 3.6z"/></svg>
            系统设置
        </a>
        <?php endif; ?>
    </nav>

    <!-- 用户信息区域 - 放在底部 -->
    <div class="user-section">
        <a href="/admin/profile.php" class="user-info-row" style="text-decoration: none;">
            <div class="user-avatar-small"><?php echo mb_substr($currentUser['real_name'], 0, 1); ?></div>
            <div class="user-details">
                <div class="user-name"><?php echo htmlspecialchars($currentUser['real_name']); ?></div>
                <div class="user-role"><?php echo $currentUser['role'] === 'admin' ? '系统管理员' : '员工'; ?></div>
            </div>
        </a>
        <div class="user-actions">
            <a href="/admin/profile.php" class="action-btn">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/>
                </svg>
                个人中心
            </a>
            <a href="/api/auth.php?action=logout" class="action-btn logout" onclick="return confirm('确定要退出登录吗？')">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M17 7l-1.41 1.41L18.17 11H8v2h10.17l-2.58 2.58L17 17l5-5zM4 5h8V3H4c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h8v-2H4V5z"/>
                </svg>
                退出
            </a>
        </div>
    </div>
</aside>

<style>
/* 侧边栏样式 */
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
    display: flex;
    flex-direction: column;
}

.sidebar-header {
    padding: 20px 24px;
    border-bottom: 1px solid rgba(255,255,255,0.1);
}

.sidebar-header .logo {
    display: flex;
    align-items: center;
    gap: 12px;
}

.sidebar-header .logo-icon {
    width: 40px;
    height: 40px;
    background: linear-gradient(135deg, #FF6B6B 0%, #FF8E53 100%);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.sidebar-header .logo-icon svg {
    width: 24px;
    height: 24px;
    fill: white;
}

.sidebar-header .logo-text {
    font-size: 17px;
    font-weight: 700;
}

.sidebar-header .logo-subtext {
    font-size: 11px;
    color: rgba(255,255,255,0.5);
    margin-top: 2px;
}

/* 导航菜单 */
.nav-menu {
    padding: 12px 0;
    flex: 1;
}

.nav-item {
    display: flex;
    align-items: center;
    padding: 11px 24px;
    color: rgba(255,255,255,0.75);
    text-decoration: none;
    transition: all 0.3s;
    cursor: pointer;
    font-size: 14px;
}

.nav-item:hover, .nav-item.active {
    background: rgba(255,255,255,0.08);
    color: white;
}

.nav-item.active {
    border-left: 3px solid #FF6B6B;
    padding-left: 21px;
    background: rgba(255,255,255,0.1);
}

.nav-item svg {
    width: 18px;
    height: 18px;
    margin-right: 12px;
    fill: currentColor;
}

.nav-section-title {
    padding: 16px 24px 6px;
    font-size: 11px;
    color: rgba(255,255,255,0.4);
    text-transform: uppercase;
    letter-spacing: 1px;
}

/* 用户信息区域 - 底部 */
.user-section {
    padding: 16px 20px;
    border-top: 1px solid rgba(255,255,255,0.1);
    background: rgba(0,0,0,0.15);
}

.user-info-row {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 12px;
}

.user-avatar-small {
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
    flex-shrink: 0;
}

.user-details {
    flex: 1;
    min-width: 0;
}

.user-details .user-name {
    font-size: 14px;
    font-weight: 500;
    color: white;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.user-details .user-role {
    font-size: 11px;
    color: rgba(255,255,255,0.5);
    margin-top: 2px;
}

.user-actions {
    display: flex;
    gap: 8px;
}

.action-btn {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 4px;
    padding: 8px 10px;
    background: rgba(255,255,255,0.08);
    color: rgba(255,255,255,0.7);
    text-decoration: none;
    border-radius: 6px;
    font-size: 12px;
    transition: all 0.3s;
    border: 1px solid rgba(255,255,255,0.1);
}

.action-btn:hover {
    background: rgba(255,255,255,0.15);
    color: white;
    border-color: rgba(255,255,255,0.2);
}

.action-btn.logout:hover {
    background: rgba(255,77,79,0.2);
    border-color: rgba(255,77,79,0.3);
    color: #ff7875;
}

.action-btn svg {
    fill: currentColor;
}
</style>
