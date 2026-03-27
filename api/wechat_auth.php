<?php
/**
 * 微信授权接口
 * 用于获取用户 OpenID
 */
require_once '../config.php';

header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? '';
$db = Database::getInstance();

// 获取微信配置
$appId = $db->fetch("SELECT `value` FROM settings WHERE `key` = 'wechat_appid'")['value'] ?? '';
$appSecret = $db->fetch("SELECT `value` FROM settings WHERE `key` = 'wechat_appsecret'")['value'] ?? '';

switch ($action) {
    case 'get_config':
        // 返回微信配置（不包含敏感信息）
        jsonResponse(true, '', [
            'appid' => $appId,
            'enabled' => !empty($appId) && !empty($appSecret)
        ]);
        break;
        
    case 'get_openid':
        // 通过 code 获取 openid
        $code = $_GET['code'] ?? '';
        
        if (empty($code)) {
            jsonResponse(false, '授权码不能为空');
        }
        
        if (empty($appId) || empty($appSecret)) {
            jsonResponse(false, '微信配置未设置');
        }
        
        // 调用微信接口获取 openid
        $url = "https://api.weixin.qq.com/sns/oauth2/access_token";
        $params = [
            'appid' => $appId,
            'secret' => $appSecret,
            'code' => $code,
            'grant_type' => 'authorization_code'
        ];
        
        $response = httpGet($url . '?' . http_build_query($params));
        $data = json_decode($response, true);
        
        if (isset($data['openid'])) {
            jsonResponse(true, '', [
                'openid' => $data['openid'],
                'unionid' => $data['unionid'] ?? ''
            ]);
        } else {
            $errorMsg = $data['errmsg'] ?? '获取 OpenID 失败';
            jsonResponse(false, $errorMsg);
        }
        break;
        
    case 'get_userinfo':
        // 通过 access_token 和 openid 获取用户信息（需要 scope 为 snsapi_userinfo）
        $accessToken = $_GET['access_token'] ?? '';
        $openid = $_GET['openid'] ?? '';
        
        if (empty($accessToken) || empty($openid)) {
            jsonResponse(false, '参数错误');
        }
        
        $url = "https://api.weixin.qq.com/sns/userinfo";
        $params = [
            'access_token' => $accessToken,
            'openid' => $openid,
            'lang' => 'zh_CN'
        ];
        
        $response = httpGet($url . '?' . http_build_query($params));
        $data = json_decode($response, true);
        
        if (isset($data['openid'])) {
            jsonResponse(true, '', [
                'openid' => $data['openid'],
                'nickname' => $data['nickname'] ?? '',
                'avatar' => $data['headimgurl'] ?? ''
            ]);
        } else {
            $errorMsg = $data['errmsg'] ?? '获取用户信息失败';
            jsonResponse(false, $errorMsg);
        }
        break;
        
    default:
        jsonResponse(false, '未知操作');
}

// HTTP GET 请求
function httpGet($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    
    if (curl_errno($ch)) {
        $error = curl_error($ch);
        curl_close($ch);
        throw new Exception('Curl error: ' . $error);
    }
    
    curl_close($ch);
    return $response;
}
