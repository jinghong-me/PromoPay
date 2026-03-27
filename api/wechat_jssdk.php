<?php
/**
 * 微信JS-SDK配置接口
 * 用于获取JS-SDK所需的配置参数
 */
require_once '../config.php';

header('Content-Type: application/json; charset=utf-8');

$url = $_GET['url'] ?? '';
if (empty($url)) {
    jsonResponse(false, 'URL不能为空');
}

// URL解码
$url = urldecode($url);

$db = Database::getInstance();

// 获取微信配置
$appId = $db->fetch("SELECT `value` FROM settings WHERE `key` = 'wechat_appid'")['value'] ?? '';
$appSecret = $db->fetch("SELECT `value` FROM settings WHERE `key` = 'wechat_appsecret'")['value'] ?? '';

if (empty($appId) || empty($appSecret)) {
    jsonResponse(false, '微信配置未设置');
}

// 获取access_token（实际生产环境应该缓存）
$accessTokenUrl = "https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid={$appId}&secret={$appSecret}";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $accessTokenUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
$tokenResponse = curl_exec($ch);
curl_close($ch);

$tokenData = json_decode($tokenResponse, true);
if (empty($tokenData['access_token'])) {
    jsonResponse(false, '获取access_token失败: ' . ($tokenData['errmsg'] ?? '未知错误'));
}

$accessToken = $tokenData['access_token'];

// 获取jsapi_ticket（实际生产环境应该缓存）
$ticketUrl = "https://api.weixin.qq.com/cgi-bin/ticket/getticket?access_token={$accessToken}&type=jsapi";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $ticketUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
$ticketResponse = curl_exec($ch);
curl_close($ch);

$ticketData = json_decode($ticketResponse, true);
if (empty($ticketData['ticket'])) {
    jsonResponse(false, '获取jsapi_ticket失败: ' . ($ticketData['errmsg'] ?? '未知错误'));
}

$jsapiTicket = $ticketData['ticket'];

// 生成签名
$timestamp = time();
$nonceStr = generateNonceStr();
$string = "jsapi_ticket={$jsapiTicket}&noncestr={$nonceStr}&timestamp={$timestamp}&url={$url}";
$signature = sha1($string);

// 记录调试信息
logError('JS-SDK Config', [
    'url' => $url,
    'string' => $string,
    'signature' => $signature,
    'appId' => $appId
]);

jsonResponse(true, '', [
    'appId' => $appId,
    'timestamp' => $timestamp,
    'nonceStr' => $nonceStr,
    'signature' => $signature,
    'debug_string' => $string // 调试用，生产环境删除
]);

function generateNonceStr($length = 16) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $str = '';
    for ($i = 0; $i < $length; $i++) {
        $str .= $chars[mt_rand(0, strlen($chars) - 1)];
    }
    return $str;
}
