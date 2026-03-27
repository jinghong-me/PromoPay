<?php
/**
 * 微信支付API封装类
 * 商家转账到零钱 - 用户领取模式
 */
class WechatPay {
    private $appId;
    private $mchId;
    private $apiV3Key;
    private $serialNo;
    private $privateKeyPath;
    private $publicKeyPath;
    
    // 微信支付API基础URL
    const BASE_URL = 'https://api.mch.weixin.qq.com';
    
    public function __construct($config = null) {
        if ($config) {
            $this->appId = $config['appid'] ?? '';
            $this->mchId = $config['mchid'] ?? '';
            $this->apiV3Key = $config['apiv3_key'] ?? '';
            $this->serialNo = $config['serial_no'] ?? '';
            $this->privateKeyPath = $config['private_key_path'] ?? '';
            $this->publicKeyPath = $config['public_key_path'] ?? '';
        } else {
            // 从数据库读取配置
            $this->loadConfigFromDB();
        }
    }
    
    /**
     * 从数据库加载配置
     */
    private function loadConfigFromDB() {
        try {
            $db = Database::getInstance();
            $settings = $db->fetchAll("SELECT `key`, `value` FROM settings WHERE `key` LIKE 'wechat_%'");
            $config = [];
            foreach ($settings as $setting) {
                $config[$setting['key']] = $setting['value'];
            }
            
            $this->appId = $config['wechat_appid'] ?? '';
            $this->mchId = $config['wechat_mchid'] ?? '';
            $this->apiV3Key = $config['wechat_apiv3key'] ?? '';
            $this->serialNo = $config['wechat_serial_no'] ?? '';
            $this->privateKeyPath = $config['wechat_private_key_path'] ?? '';
            $this->publicKeyPath = $config['wechat_public_key_path'] ?? '';
        } catch (Exception $e) {
            logError('Failed to load WeChat Pay config: ' . $e->getMessage());
        }
    }
    
    /**
     * 发起商家转账到零钱
     * @param string $openid 用户openid
     * @param float $amount 转账金额
     * @param string $transferBillNo 转账单号
     * @param string $userName 用户真实姓名（可选，用于校验）
     * @return array
     */
    public function transfer($openid, $amount, $transferBillNo, $userName = '', $transferRemark = '课程推广返现') {
        try {
            // 升级版本商家转账到零钱接口路径
            $url = self::BASE_URL . '/v3/fund-app/mch-transfer/transfer-bills';

            // 构建请求参数（升级版本格式）
            $params = [
                'appid' => $this->appId,
                'out_bill_no' => $transferBillNo,
                'transfer_scene_id' => '1000', // 现金营销
                'openid' => $openid,
                'transfer_amount' => intval($amount * 100), // 转换为分
                'transfer_remark' => $transferRemark,
                'notify_url' => SITE_URL . '/api/wechat_notify.php',
                // 现金营销场景需要用户收款感知
                'user_recv_perception' => '现金奖励',
                // 现金营销场景需要报备信息
                'transfer_scene_report_infos' => [
                    [
                        'info_type' => '活动名称',
                        'info_content' => '课程推广返现活动'
                    ],
                    [
                        'info_type' => '奖励说明',
                        'info_content' => '用户购买课程后截图返现'
                    ]
                ]
            ];

            // 如果提供了用户姓名，添加校验
            if (!empty($userName)) {
                $params['user_name'] = $this->encryptUserName($userName);
            }

            $response = $this->request('POST', $url, $params);

            return [
                'success' => true,
                'data' => $response
            ];
            
        } catch (Exception $e) {
            logError('WeChat transfer failed: ' . $e->getMessage(), [
                'openid' => $openid,
                'amount' => $amount,
                'bill_no' => $transferBillNo
            ]);
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * 查询转账结果
     * @param string $transferBillNo 转账单号
     * @return array
     */
    public function queryTransfer($transferBillNo) {
        try {
            // 升级版本查询转账单接口
            $url = self::BASE_URL . '/v3/fund-app/mch-transfer/transfer-bills/out-bill-no/' . $transferBillNo;
            $response = $this->request('GET', $url);
            
            return [
                'success' => true,
                'data' => $response
            ];
        } catch (Exception $e) {
            logError('Query transfer failed: ' . $e->getMessage(), ['bill_no' => $transferBillNo]);
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * 发送HTTP请求
     */
    private function request($method, $url, $body = null) {
        $timestamp = time();
        $nonceStr = $this->generateNonceStr();
        $bodyStr = $body ? json_encode($body, JSON_UNESCAPED_UNICODE) : '';
        
        // 构建签名
        $signMessage = $method . "\n" . 
                       parse_url($url, PHP_URL_PATH) . "\n" . 
                       $timestamp . "\n" . 
                       $nonceStr . "\n" . 
                       $bodyStr . "\n";
        
        $signature = $this->sign($signMessage);
        
        // 构建Authorization头
        $auth = sprintf(
            'WECHATPAY2-SHA256-RSA2048 mchid="%s",nonce_str="%s",signature="%s",timestamp="%d",serial_no="%s"',
            $this->mchId,
            $nonceStr,
            $signature,
            $timestamp,
            $this->serialNo
        );
        
        $headers = [
            'Authorization: ' . $auth,
            'Content-Type: application/json',
            'Accept: application/json',
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $bodyStr);
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if (curl_errno($ch)) {
            throw new Exception('Curl error: ' . curl_error($ch));
        }
        
        curl_close($ch);
        
        $result = json_decode($response, true);

        // 记录调试日志
        logError('WeChat Pay API Response', [
            'http_code' => $httpCode,
            'url' => $url,
            'request_body' => $body,
            'response' => $result,
            'raw_response' => $response
        ]);

        if ($httpCode !== 200 && $httpCode !== 202) {
            // 微信错误格式可能有多种，尝试提取所有可能的错误信息
            $errorParts = [];
            if (!empty($result['code'])) $errorParts[] = 'Code: ' . $result['code'];
            if (!empty($result['message'])) $errorParts[] = 'Msg: ' . $result['message'];
            if (!empty($result['detail'])) $errorParts[] = 'Detail: ' . $result['detail'];
            if (empty($errorParts)) $errorParts[] = 'HTTP ' . $httpCode;
            if (!empty($result['field'])) $errorParts[] = 'Field: ' . $result['field'];

            $errorMsg = implode(' | ', $errorParts);
            throw new Exception('WeChat API error: ' . $errorMsg);
        }

        return $result;
    }
    
    /**
     * RSA签名
     */
    private function sign($message) {
        if (!file_exists($this->privateKeyPath)) {
            throw new Exception('Private key file not found');
        }
        
        $privateKey = file_get_contents($this->privateKeyPath);
        
        if (!openssl_sign($message, $signature, $privateKey, 'sha256WithRSAEncryption')) {
            throw new Exception('Sign failed: ' . openssl_error_string());
        }
        
        return base64_encode($signature);
    }
    
    /**
     * 加密用户姓名
     */
    private function encryptUserName($userName) {
        if (!file_exists($this->publicKeyPath)) {
            throw new Exception('Public key file not found');
        }
        
        $publicKey = file_get_contents($this->publicKeyPath);
        
        if (!openssl_public_encrypt($userName, $encrypted, $publicKey, OPENSSL_PKCS1_OAEP_PADDING)) {
            throw new Exception('Encrypt failed: ' . openssl_error_string());
        }
        
        return base64_encode($encrypted);
    }
    
    /**
     * 生成随机字符串
     */
    private function generateNonceStr($length = 32) {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $str = '';
        for ($i = 0; $i < $length; $i++) {
            $str .= $chars[mt_rand(0, strlen($chars) - 1)];
        }
        return $str;
    }
    
    /**
     * 验证签名（用于回调通知）
     */
    public function verifyNotify($timestamp, $nonce, $body, $signature) {
        $message = $timestamp . "\n" . $nonce . "\n" . $body . "\n";
        
        if (!file_exists($this->publicKeyPath)) {
            return false;
        }
        
        $publicKey = file_get_contents($this->publicKeyPath);
        $signature = base64_decode($signature);
        
        return openssl_verify($message, $signature, $publicKey, 'sha256WithRSAEncryption') === 1;
    }
    
    /**
     * 解密回调数据
     */
    public function decryptNotify($associatedData, $nonceStr, $ciphertext) {
        $ciphertext = base64_decode($ciphertext);
        
        $plaintext = openssl_decrypt(
            $ciphertext,
            'aes-256-gcm',
            $this->apiV3Key,
            OPENSSL_RAW_DATA,
            $nonceStr,
            '',
            $associatedData
        );
        
        if ($plaintext === false) {
            throw new Exception('Decrypt failed');
        }
        
        return json_decode($plaintext, true);
    }
}
