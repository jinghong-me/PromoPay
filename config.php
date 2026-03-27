<?php
/**
 * 红包领取系统 - 配置文件
 * 微信支付商家转账到零钱 - 用户领取模式
 */

// 错误报告设置（生产环境请关闭）
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 时区设置
date_default_timezone_set('Asia/Shanghai');

// 数据库配置
define('DB_HOST', 'localhost');
define('DB_NAME', 'redpacket_system');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// 网站配置
define('SITE_URL', 'https://your-domain.com'); // 修改为您的域名
// SITE_NAME 改为从数据库读取，见下方初始化代码
define('ADMIN_EMAIL', 'admin@your-domain.com');

// 路径配置
define('BASE_PATH', dirname(__FILE__));
define('UPLOAD_PATH', BASE_PATH . '/uploads');
define('QR_CODE_PATH', UPLOAD_PATH . '/qrcodes');
define('LOG_PATH', BASE_PATH . '/logs');
define('CERT_PATH', BASE_PATH . '/cert'); // 微信支付证书目录

// 自动创建必要的目录
$requiredDirs = [UPLOAD_PATH, QR_CODE_PATH, LOG_PATH, CERT_PATH];
foreach ($requiredDirs as $dir) {
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
}

// 微信支付配置（从数据库读取，这里仅作默认值）
define('WECHAT_APPID', '');
define('WECHAT_MCHID', '');
define('WECHAT_APIKEY', '');
define('WECHAT_APIV3_KEY', '');

// 会话配置
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 0); // HTTPS环境下设为1

// 分页配置
define('PAGE_SIZE', 20);

// 红包默认有效期（小时），0表示长期有效
define('REDPACKET_DEFAULT_VALID_HOURS', 24);

// 日志保留天数
define('LOG_RETENTION_DAYS', 90);

// 安全密钥（用于加密敏感数据，请修改）
define('ENCRYPTION_KEY', 'your-secret-key-here-change-this-in-production');

// 自动加载类
spl_autoload_register(function ($class) {
    $file = BASE_PATH . '/includes/' . $class . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

// 启动会话
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 从数据库读取网站名称（如果数据库可用）
try {
    $db = Database::getInstance();
    $siteName = $db->fetch("SELECT `value` FROM settings WHERE `key` = 'site_name'");
    define('SITE_NAME', $siteName['value'] ?? '红包领取系统');
} catch (Exception $e) {
    // 数据库不可用时使用默认值
    define('SITE_NAME', '红包领取系统');
}

// 通用函数
function jsonResponse($success, $message = '', $data = null) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
        'timestamp' => time()
    ]);
    exit;
}

function redirect($url) {
    header('Location: ' . $url);
    exit;
}

function generateRandomString($length = 16) {
    return bin2hex(random_bytes($length / 2));
}

function generateRedpacketCode() {
    return 'RP' . date('Ymd') . strtoupper(substr(uniqid(), -8)) . rand(1000, 9999);
}

function formatAmount($amount) {
    return number_format($amount, 2);
}

function sanitizeInput($data) {
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

function getClientIP() {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } elseif (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    }
    return filter_var($ip, FILTER_VALIDATE_IP) ?: '0.0.0.0';
}

function logError($message, $context = []) {
    // 确保日志目录存在
    if (!is_dir(LOG_PATH)) {
        @mkdir(LOG_PATH, 0755, true);
    }

    $logFile = LOG_PATH . '/error_' . date('Y-m-d') . '.log';
    $logEntry = date('Y-m-d H:i:s') . ' | ' . $message;
    if (!empty($context)) {
        $logEntry .= ' | ' . json_encode($context, JSON_UNESCAPED_UNICODE);
    }
    $logEntry .= PHP_EOL;

    // 如果文件无法写入，使用系统日志
    if (!@error_log($logEntry, 3, $logFile)) {
        error_log('Redpacket Error: ' . $message);
    }
}

function logOperation($userId, $action, $targetType = null, $targetId = null, $details = []) {
    try {
        $db = Database::getInstance();
        $db->insert('operation_logs', [
            'user_id' => $userId,
            'action' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'details' => json_encode($details, JSON_UNESCAPED_UNICODE),
            'ip_address' => getClientIP(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
    } catch (Exception $e) {
        logError('Failed to log operation: ' . $e->getMessage());
    }
}

// 检查登录状态
function checkAuth() {
    if (!isset($_SESSION['user_id'])) {
        if (isAjaxRequest()) {
            jsonResponse(false, '请先登录');
        } else {
            redirect('/admin/login.php');
        }
    }
}

function checkAdmin() {
    checkAuth();
    if ($_SESSION['user_role'] !== 'admin') {
        if (isAjaxRequest()) {
            jsonResponse(false, '权限不足');
        } else {
            redirect('/admin/');
        }
    }
}

function isAjaxRequest() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

// CSRF保护
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// 分页函数
function paginate($total, $page, $perPage = PAGE_SIZE) {
    $totalPages = ceil($total / $perPage);
    $page = max(1, min($page, $totalPages));
    $offset = ($page - 1) * $perPage;
    
    return [
        'total' => $total,
        'page' => $page,
        'per_page' => $perPage,
        'total_pages' => $totalPages,
        'offset' => $offset,
        'has_prev' => $page > 1,
        'has_next' => $page < $totalPages
    ];
}
