<?php
/**
 * 手机端海报分享预览页
 */
require_once 'config.php';

// 确保 SITE_NAME 已定义
if (!defined('SITE_NAME')) {
    define('SITE_NAME', '现金红包营销系统');
}

$code = $_GET['code'] ?? '';
if (empty($code)) {
    die('参数错误');
}

$db = Database::getInstance();
// 获取红包信息
$redpacket = $db->fetch("SELECT r.*, t.name as template_name, t.type as template_type, t.bg_color, t.bg_gradient,
    t.title_color, t.amount_color, t.button_color, t.button_text, t.animation_type
    FROM redpackets r
    LEFT JOIN redpacket_templates t ON r.template_id = t.id
    WHERE r.code = ?", [$code]);

if (!$redpacket) {
    die('红包不存在');
}

// 在服务端将二维码图片转换为 Base64，彻底解决跨域问题
$qrcodeFile = BASE_PATH . '/' . ltrim($redpacket['qrcode_path'], '/');
$qrcodeBase64 = '';
if (file_exists($qrcodeFile)) {
    $type = pathinfo($qrcodeFile, PATHINFO_EXTENSION);
    $data = file_get_contents($qrcodeFile);
    $qrcodeBase64 = 'data:image/' . $type . ';base64,' . base64_encode($data);
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>保存海报发送给好友</title>
    <script src="https://cdn.bootcdn.net/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdn.bootcdn.net/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            background: #f0f2f5;
            font-family: -apple-system, sans-serif;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 20px;
            min-height: 100vh;
        }

        .tip {
            margin-bottom: 15px;
            color: #52c41a;
            font-size: 14px;
            font-weight: 500;
            background: #f6ffed;
            padding: 8px 16px;
            border-radius: 20px;
            border: 1px solid #b7eb8f;
        }

        /* 海报样式 (同步 PC 端) */
        .poster-wrapper {
            width: 100%;
            max-width: 350px;
            position: relative;
        }

        .poster {
            background: linear-gradient(135deg, #FF6B6B 0%, #FF8E53 100%);
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(255, 107, 107, 0.2);
        }

        .poster-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 20px;
            background: rgba(255,255,255,0.2);
            border-radius: 16px 16px 0 0;
        }

        .poster-logo {
            display: flex;
            align-items: center;
            gap: 8px;
            color: white;
            font-weight: 600;
            font-size: 15px;
        }

        .poster-badge {
            background: rgba(255,255,255,0.9);
            color: #FF6B6B;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .poster-body {
            padding: 30px 20px;
            text-align: center;
        }

        .poster-amount-section {
            margin-bottom: 24px;
        }

        .poster-amount-label {
            color: rgba(255,255,255,0.9);
            font-size: 14px;
            margin-bottom: 8px;
        }

        .poster-amount {
            font-size: 48px;
            font-weight: 700;
            color: white;
            text-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 4px;
        }

        .poster-amount-desc {
            color: rgba(255,255,255,0.8);
            font-size: 13px;
        }

        .poster-qrcode-section {
            background: white;
            border-radius: 12px;
            padding: 20px;
            display: inline-block;
            position: relative;
            z-index: 50;
        }

        #posterQrcode {
            width: 180px;
            height: 180px;
            position: relative;
            z-index: 100;
        }
        #posterQrcode canvas {
            display: block;
            border-radius: 8px;
            position: relative;
            z-index: 100;
        }

        .poster-qrcode-tip {
            margin-top: 12px;
            color: #666;
            font-size: 13px;
            font-weight: 500;
        }

        .poster-footer {
            background: rgba(255,255,255,0.95);
            padding: 16px 20px;
            text-align: center;
            border-radius: 0 0 16px 16px;
        }

        .poster-info {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-bottom: 10px;
        }

        .poster-info-item {
            display: flex;
            align-items: center;
            gap: 4px;
            font-size: 11px;
            color: #666;
        }

        .poster-slogan {
            font-size: 10px;
            color: #999;
        }

        /* 生成后的图片覆盖层 */
        #resultImage {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            z-index: 1000;
            pointer-events: auto;
        }

        /* 原海报禁用交互 */
        #posterDom {
            pointer-events: none;
        }

        #loading {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: rgba(0,0,0,0.7);
            color: white;
            padding: 15px 25px;
            border-radius: 10px;
            font-size: 14px;
            z-index: 100;
        }
    </style>
</head>
<body>
    <div id="loading">海报生成中...</div>
    <div class="tip">长按下方海报，发送给朋友</div>

    <div class="poster-wrapper">
        <!-- 海报 DOM -->
        <div class="poster" id="posterDom">
            <div class="poster-header">
                <div class="poster-logo">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="white">
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                    </svg>
                    <span><?php echo SITE_NAME; ?></span>
                </div>
                <div class="poster-badge">微信红包</div>
            </div>
            <div class="poster-body">
                <div class="poster-amount-section">
                    <div class="poster-amount-label">扫码领取现金红包</div>
                    <div class="poster-amount">¥<?php echo number_format($redpacket['amount_per_packet'], 2); ?></div>
                    <div class="poster-amount-desc"><?php echo $redpacket['type'] === 'single' ? '固定金额' : '每人可领'; ?></div>
                </div>
                <div class="poster-qrcode-section">
                    <div id="posterQrcode"></div>
                    <div class="poster-qrcode-tip">微信扫一扫领取</div>
                </div>
            </div>
            <div class="poster-footer">
                <div class="poster-info">
                    <div class="poster-info-item">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="#52c41a">
                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                        </svg>
                        <span>安全可信</span>
                    </div>
                    <div class="poster-info-item">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="#52c41a">
                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                        </svg>
                        <span>即时到账</span>
                    </div>
                    <div class="poster-info-item">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="#52c41a">
                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/>
                        </svg>
                        <span>官方认证</span>
                    </div>
                </div>
                <div class="poster-slogan"><?php echo SITE_NAME; ?> · 扫码即领 · 秒到零钱</div>
            </div>
        </div>
        <!-- 最终生成的图片将显示在这里，供长按 -->
        <img id="resultImage" alt="海报图片">
    </div>

    <script>
        window.onload = async function() {
            const posterDom = document.getElementById('posterDom');
            const resultImage = document.getElementById('resultImage');
            const loading = document.getElementById('loading');

            try {
                // 动态生成二维码
                const claimUrl = window.location.origin + '/claim.php?code=<?php echo $code; ?>';
                const qrcodeContainer = document.getElementById('posterQrcode');
                qrcodeContainer.innerHTML = '';
                new QRCode(qrcodeContainer, {
                    text: claimUrl,
                    width: 180,
                    height: 180,
                    colorDark: "#000000",
                    colorLight: "#ffffff",
                    correctLevel: QRCode.CorrectLevel.H
                });

                // 等待二维码渲染完成
                await new Promise(resolve => setTimeout(resolve, 500));

                // 生成海报图片
                const canvas = await html2canvas(posterDom, {
                    scale: 3,
                    useCORS: true,
                    backgroundColor: null
                });

                const base64 = canvas.toDataURL('image/png');
                resultImage.src = base64;
                resultImage.style.opacity = 1;
                loading.style.display = 'none';
            } catch (e) {
                console.error(e);
                loading.textContent = '生成失败，请刷新重试';
            }
        };
    </script>
</body>
</html>