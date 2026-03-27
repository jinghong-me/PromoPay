<?php
/**
 * 红包领取页面
 */
require_once 'config.php';

// 红包编码
$rpCode = $_GET['rp'] ?? '';

// 如果没有 rp 参数，尝试从 code 参数获取（兼容旧链接）
if (empty($rpCode)) {
    $rpCode = $_GET['code'] ?? '';
}

if (empty($rpCode)) {
    die('红包编码不能为空');
}

// 检查红包状态
$redpacketObj = new Redpacket();
$status = $redpacketObj->checkStatus($rpCode);

if (!$status['valid']) {
    showErrorPage($status['message']);
    exit;
}

$redpacket = $status['redpacket'];

// 获取微信配置
$db = Database::getInstance();
$wechatAppId = $db->fetch("SELECT `value` FROM settings WHERE `key` = 'wechat_appid'")['value'] ?? '';
$appSecret = $db->fetch("SELECT `value` FROM settings WHERE `key` = 'wechat_appsecret'")['value'] ?? '';
$wechatMchId = $db->fetch("SELECT `value` FROM settings WHERE `key` = 'wechat_mchid'")['value'] ?? '';

// 获取用户 OpenID
$userOpenid = '';
$wxCode = $_GET['code'] ?? '';

if (!empty($wxCode) && $wechatAppId && $appSecret) {
    $url = "https://api.weixin.qq.com/sns/oauth2/access_token";
    $params = [
        'appid' => $wechatAppId,
        'secret' => $appSecret,
        'code' => $wxCode,
        'grant_type' => 'authorization_code'
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url . '?' . http_build_query($params));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($response, true);
    if (isset($data['openid'])) {
        $userOpenid = $data['openid'];
    }
}

// 生成页面标题
$pageTitle = $redpacket['name'] . ' - 红包领取';

// 判断是否长期有效
$isLongTerm = $redpacket['valid_hours'] == 0;

// 根据模板类型设置不同的样式
$templateStyles = [
    'wechat' => [
        'bg' => 'linear-gradient(180deg, #FF6B6B 0%, #EE5A5A 50%, #D32F2F 100%)',
        'cardBg' => '#FFFFFF',
        'accent' => '#FFD700',
        'buttonBg' => 'linear-gradient(180deg, #FFD700 0%, #FFA500 100%)',
        'buttonText' => '#8B0000',
        'pattern' => 'radial-gradient(circle at 20% 80%, rgba(255,215,0,0.3) 0%, transparent 50%), radial-gradient(circle at 80% 20%, rgba(255,215,0,0.3) 0%, transparent 50%)'
    ],
    'festive' => [
        'bg' => 'linear-gradient(135deg, #DC143C 0%, #B71C1C 50%, #8B0000 100%)',
        'cardBg' => '#FFF8E7',
        'accent' => '#FFD700',
        'buttonBg' => 'linear-gradient(180deg, #FFD700 0%, #FF8C00 100%)',
        'buttonText' => '#8B0000',
        'pattern' => 'repeating-linear-gradient(45deg, transparent, transparent 10px, rgba(255,215,0,0.1) 10px, rgba(255,215,0,0.1) 20px)'
    ],
    'business' => [
        'bg' => 'linear-gradient(135deg, #1E3A8A 0%, #3B82F6 50%, #60A5FA 100%)',
        'cardBg' => '#FFFFFF',
        'accent' => '#FCD34D',
        'buttonBg' => 'linear-gradient(180deg, #FCD34D 0%, #F59E0B 100%)',
        'buttonText' => '#1E3A8A',
        'pattern' => 'radial-gradient(circle at 10% 20%, rgba(255,255,255,0.1) 0%, transparent 20%), radial-gradient(circle at 90% 80%, rgba(255,255,255,0.1) 0%, transparent 20%)'
    ],
    'simple' => [
        'bg' => 'linear-gradient(135deg, #F5F5F5 0%, #E0E0E0 50%, #BDBDBD 100%)',
        'cardBg' => '#FFFFFF',
        'accent' => '#FF6B6B',
        'buttonBg' => 'linear-gradient(180deg, #FF6B6B 0%, #EE5A5A 100%)',
        'buttonText' => '#FFFFFF',
        'pattern' => 'none'
    ],
    'custom' => [
        'bg' => $redpacket['bg_gradient'] ?: $redpacket['bg_color'],
        'cardBg' => '#FFFFFF',
        'accent' => $redpacket['amount_color'],
        'buttonBg' => 'linear-gradient(180deg, ' . $redpacket['button_color'] . ' 0%, #FFA500 100%)',
        'buttonText' => '#333',
        'pattern' => 'none'
    ]
];

$style = $templateStyles[$redpacket['template_type']] ?? $templateStyles['custom'];

// 错误页面函数
function showErrorPage($message) {
    ?>
    <!DOCTYPE html>
    <html lang="zh-CN">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
        <title>领取失败</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'PingFang SC', sans-serif;
                background: linear-gradient(180deg, #f5f5f5 0%, #e0e0e0 100%);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 20px;
            }
            .error-card {
                background: white;
                border-radius: 20px;
                padding: 50px 30px;
                width: 100%;
                max-width: 320px;
                text-align: center;
                box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            }
            .error-icon {
                width: 80px;
                height: 80px;
                background: linear-gradient(135deg, #FF4D4F 0%, #FF7875 100%);
                border-radius: 50%;
                margin: 0 auto 25px;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 40px;
                color: white;
            }
            .error-title {
                font-size: 20px;
                color: #333;
                margin-bottom: 12px;
                font-weight: 600;
            }
            .error-message {
                font-size: 14px;
                color: #666;
                line-height: 1.6;
            }
        </style>
    </head>
    <body>
        <div class="error-card">
            <div class="error-icon">!</div>
            <h1 class="error-title">无法领取</h1>
            <p class="error-message"><?php echo htmlspecialchars($message); ?></p>
        </div>
    </body>
    </html>
    <?php
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'PingFang SC', sans-serif;
            background: <?php echo $style['bg']; ?>;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .card {
            background: white;
            border-radius: 24px;
            padding: 45px 30px 35px;
            width: 100%;
            max-width: 340px;
            text-align: center;
            box-shadow: 0 25px 70px rgba(0,0,0,0.35);
            animation: slideUp 0.5s ease-out;
        }

        @keyframes slideUp {
            from { transform: translateY(30px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .icon {
            width: 90px;
            height: 90px;
            background: linear-gradient(135deg, #FF6B6B 0%, #FF8E53 100%);
            border-radius: 50%;
            margin: 0 auto 25px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 45px;
            box-shadow: 0 10px 30px rgba(255, 107, 107, 0.4);
            animation: pulse 2s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }

        h1 { font-size: 22px; color: #333; margin-bottom: 8px; font-weight: 600; }

        .subtitle {
            font-size: 14px;
            color: #999;
            margin-bottom: 25px;
        }

        .amount {
            font-size: 56px;
            color: #FF6B6B;
            font-weight: 800;
            margin: 20px 0;
            text-shadow: 0 2px 10px rgba(255, 107, 107, 0.2);
        }

        .amount span { font-size: 28px; font-weight: 600; }

        .description {
            font-size: 14px;
            color: #666;
            margin-bottom: 25px;
            padding: 0 10px;
            line-height: 1.6;
        }

        .info-tags {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-bottom: 30px;
            font-size: 12px;
            color: #999;
        }

        .info-tag {
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .btn {
            width: 100%;
            height: 54px;
            background: linear-gradient(180deg, #FFD700 0%, #FFA500 100%);
            border: none;
            border-radius: 27px;
            font-size: 18px;
            font-weight: 700;
            color: #8B0000;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 6px 20px rgba(255, 165, 0, 0.4);
            letter-spacing: 2px;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(255, 165, 0, 0.5);
        }

        .btn:active {
            transform: translateY(0) scale(0.98);
        }

        .btn:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .btn.success {
            background: linear-gradient(180deg, #52C41A 0%, #389E0D 100%);
            color: white;
        }

        .btn.confirm {
            background: linear-gradient(180deg, #1890FF 0%, #096DD9 100%);
            color: white;
            animation: glow 1.5s ease-in-out infinite;
        }

        @keyframes glow {
            0%, 100% { box-shadow: 0 6px 20px rgba(24, 144, 255, 0.4); }
            50% { box-shadow: 0 6px 30px rgba(24, 144, 255, 0.7); }
        }

        .result {
            margin-top: 20px;
            padding: 15px;
            border-radius: 12px;
            font-size: 14px;
            display: none;
            animation: fadeIn 0.3s ease-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .result.success {
            background: #f6ffed;
            color: #52c41a;
            border: 1px solid #b7eb8f;
        }

        .result.error {
            background: #fff2f0;
            color: #ff4d4f;
            border: 1px solid #ffccc7;
        }

        .result.show { display: block; }

        .loading {
            display: none;
            margin-top: 20px;
        }

        .loading.show { display: block; }

        .spinner {
            width: 36px;
            height: 36px;
            border: 3px solid #f0f0f0;
            border-top-color: #FF6B6B;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 10px;
        }

        @keyframes spin { to { transform: rotate(360deg); } }

        .loading-text {
            font-size: 13px;
            color: #999;
        }

        .wechat-tip {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: rgba(0, 0, 0, 0.85);
            color: white;
            padding: 15px;
            text-align: center;
            font-size: 14px;
            z-index: 1000;
            display: none;
        }

        .wechat-tip.show { display: block; }
    </style>
</head>
<body>
    <div class="wechat-tip" id="wechatTip">
        请在微信中打开此页面领取红包
    </div>

    <div class="card">
        <div class="icon">🧧</div>
        <h1><?php echo htmlspecialchars($redpacket['name']); ?></h1>
        <p class="subtitle">恭喜发财，大吉大利</p>

        <div class="amount"><span>¥</span><?php echo number_format($redpacket['amount_per_packet'], 2); ?></div>

        <?php if ($redpacket['description']): ?>
        <p class="description"><?php echo nl2br(htmlspecialchars($redpacket['description'])); ?></p>
        <?php endif; ?>

        <div class="info-tags">
            <?php if ($isLongTerm): ?>
            <span class="info-tag">📦 剩余 <?php echo $redpacket['remaining_quantity']; ?> 个</span>
            <?php else: ?>
            <span class="info-tag">⏰ 有效期至 <?php echo date('m-d H:i', strtotime($redpacket['valid_end'])); ?></span>
            <?php endif; ?>
            <span class="info-tag">🎯 限领 <?php echo $redpacket['max_claims_per_user']; ?> 次</span>
        </div>

        <button class="btn" id="claimBtn" onclick="handleButtonClick()">
            <?php echo $redpacket['button_text'] ?: '开'; ?>
        </button>

        <div class="loading" id="loading">
            <div class="spinner"></div>
            <p class="loading-text">领取中...</p>
        </div>

        <div class="result" id="result"></div>
    </div>

    <script>
        const wechatAppId = '<?php echo $wechatAppId; ?>';
        const wechatMchId = '<?php echo $wechatMchId; ?>';
        const redpacketCode = '<?php echo $rpCode; ?>';
        let userOpenid = '<?php echo $userOpenid; ?>';
        let currentStep = 'init'; // init -> claiming -> confirm -> done

        // 检测是否在微信中
        function isWechat() {
            return /MicroMessenger/i.test(navigator.userAgent);
        }

        // 页面加载时检查
        document.addEventListener('DOMContentLoaded', function() {
            if (!isWechat()) {
                document.getElementById('wechatTip').classList.add('show');
                document.getElementById('claimBtn').disabled = true;
                return;
            }

            // 如果没有 openid，自动发起授权
            if (!userOpenid) {
                const currentUrl = window.location.origin + window.location.pathname + '?rp=' + encodeURIComponent(redpacketCode);
                const redirectUri = encodeURIComponent(currentUrl);
                const authUrl = `https://open.weixin.qq.com/connect/oauth2/authorize?appid=${wechatAppId}&redirect_uri=${redirectUri}&response_type=code&scope=snsapi_base&state=STATE#wechat_redirect`;
                window.location.href = authUrl;
                return;
            }
        });

        // 按钮点击处理
        function handleButtonClick() {
            if (currentStep === 'init' || currentStep === 'claiming') {
                claimRedpacket();
            } else if (currentStep === 'confirm') {
                confirmTransfer();
            }
        }

        // 当前领取记录ID
        let currentClaimId = null;

        // 第一步：锁定红包（点击"开"按钮）
        async function claimRedpacket() {
            if (!userOpenid) {
                showResult('error', '未获取到用户信息，请刷新页面重试');
                return;
            }

            const btn = document.getElementById('claimBtn');
            const loading = document.getElementById('loading');

            currentStep = 'claiming';
            btn.disabled = true;
            loading.classList.add('show');
            hideResult();

            try {
                const response = await fetch('/api/claim.php?action=claim', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'code=' + encodeURIComponent(redpacketCode) + '&openid=' + encodeURIComponent(userOpenid)
                });

                const responseText = await response.text();
                console.log('Raw response:', responseText);

                let result;
                try {
                    result = JSON.parse(responseText);
                } catch (parseError) {
                    console.error('JSON parse error:', parseError);
                    throw new Error('服务器返回格式错误: ' + responseText.substring(0, 100));
                }

                loading.classList.remove('show');

                if (result.success) {
                    currentClaimId = result.data.claim_id;

                    // 如果需要发起转账（新锁定的红包）
                    if (result.data.need_transfer) {
                        currentStep = 'confirm';
                        btn.textContent = '确认收款';
                        btn.classList.add('confirm');
                        btn.disabled = false;
                        showResult('success', '请点击下方按钮确认收款，金额将立即到账');
                    } else if (result.data.package_info) {
                        // 已有转账信息
                        window.currentPackageInfo = result.data.package_info;
                        window.currentTransferBillNo = result.data.transfer_bill_no || '';
                        currentStep = 'confirm';
                        btn.textContent = '确认收款';
                        btn.classList.add('confirm');
                        btn.disabled = false;
                        showResult('success', '请点击下方按钮确认收款，金额将立即到账');
                    } else {
                        currentStep = 'done';
                        showResult('success', '领取成功！¥' + result.data.amount + ' 已发放到您的微信零钱');
                        btn.textContent = '已领取';
                        btn.classList.add('success');
                    }
                } else {
                    currentStep = 'init';
                    showResult('error', result.message || '领取失败，请稍后重试');
                    btn.disabled = false;
                }
            } catch (error) {
                loading.classList.remove('show');
                currentStep = 'init';
                console.error('Claim error:', error);
                showResult('error', '网络错误: ' + error.message);
                btn.disabled = false;
            }
        }

        // 第二步：发起转账并确认收款
        async function confirmTransfer() {
            if (!currentClaimId) {
                showResult('error', '请先领取红包');
                return;
            }

            const btn = document.getElementById('claimBtn');
            const loading = document.getElementById('loading');

            btn.disabled = true;
            loading.classList.add('show');
            hideResult();

            try {
                // 先发起微信转账
                const response = await fetch('/api/claim.php?action=initiate_transfer', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'claim_id=' + currentClaimId + '&openid=' + encodeURIComponent(userOpenid)
                });

                const responseText = await response.text();
                console.log('Initiate transfer response:', responseText);

                let result;
                try {
                    result = JSON.parse(responseText);
                } catch (parseError) {
                    console.error('JSON parse error:', parseError);
                    throw new Error('服务器返回格式错误: ' + responseText.substring(0, 100));
                }

                loading.classList.remove('show');

                if (!result.success) {
                    currentStep = 'init';
                    showResult('error', result.message || '转账发起失败');
                    btn.disabled = false;
                    return;
                }

                // 保存转账信息
                window.currentPackageInfo = result.data.package_info;
                window.currentTransferBillNo = result.data.transfer_bill_no;

                // 调用微信JSAPI确认收款
                if (typeof WeixinJSBridge === 'undefined') {
                    showResult('error', '微信环境异常，请重试');
                    btn.disabled = false;
                    return;
                }

                WeixinJSBridge.invoke('requestMerchantTransfer', {
                    mchId: wechatMchId,
                    appId: wechatAppId,
                    package: window.currentPackageInfo
                }, function(res) {
                    handleConfirmResult(res);
                });

            } catch (error) {
                loading.classList.remove('show');
                currentStep = 'init';
                console.error('Initiate transfer error:', error);
                showResult('error', '转账发起失败: ' + error.message);
                btn.disabled = false;
            }
        }

        // 处理确认结果
        function handleConfirmResult(res) {
            const btn = document.getElementById('claimBtn');

            if (res.err_msg === 'requestMerchantTransfer:ok') {
                currentStep = 'done';
                showResult('success', '🎉 领取成功！金额已到账微信零钱');
                btn.textContent = '已领取';
                btn.classList.remove('confirm');
                btn.classList.add('success');
                // 通知服务器确认成功
                notifyConfirmSuccess(window.currentTransferBillNo);
            } else if (res.err_msg === 'requestMerchantTransfer:cancel') {
                showResult('error', '您取消了收款，可点击上方按钮重新确认');
                btn.disabled = false;
            } else {
                showResult('error', '收款失败：' + (res.err_desc || '请重试'));
                btn.disabled = false;
            }
        }

        // 通知服务器确认成功
        async function notifyConfirmSuccess(billNo) {
            try {
                await fetch('/api/claim.php?action=confirm', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'bill_no=' + encodeURIComponent(billNo)
                });
            } catch (e) {
                console.log('通知服务器失败', e);
            }
        }

        // 显示结果
        function showResult(type, message) {
            const result = document.getElementById('result');
            result.className = 'result ' + type + ' show';
            result.textContent = message;
        }

        // 隐藏结果
        function hideResult() {
            const result = document.getElementById('result');
            result.classList.remove('show');
        }
    </script>
</body>
</html>
