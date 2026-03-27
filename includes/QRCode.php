<?php
/**
 * 二维码生成类
 */
class QRCode {
    
    /**
     * 生成二维码图片
     * @param string $data 二维码内容
     * @param string $outputPath 输出路径
     * @param int $size 尺寸
     * @return string 生成的文件路径
     */
    public static function generate($data, $outputPath = null, $size = 300) {
        // 使用Google Chart API生成二维码
        $url = 'https://api.qrserver.com/v1/create-qr-code/?size=' . $size . 'x' . $size . '&data=' . urlencode($data);
        
        if ($outputPath === null) {
            $filename = 'qr_' . uniqid() . '.png';
            $outputPath = QR_CODE_PATH . '/' . $filename;
        }
        
        // 下载二维码图片
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $imageData = curl_exec($ch);
        curl_close($ch);
        
        if ($imageData) {
            file_put_contents($outputPath, $imageData);
            return $outputPath;
        }
        
        return false;
    }
    
    /**
     * 生成纯二维码（用于直接展示）
     */
    public static function generateSimple($code, $size = 300) {
        $claimUrl = SITE_URL . '/claim.php?code=' . $code;
        $filename = 'qr_' . $code . '.png';
        $filepath = QR_CODE_PATH . '/' . $filename;
        
        if (self::generate($claimUrl, $filepath, $size)) {
            return '/uploads/qrcodes/' . $filename;
        }
        
        return false;
    }
    
    /**
     * 十六进制颜色转RGB
     */
    private static function hexToRgb($hex) {
        $hex = ltrim($hex, '#');
        if (strlen($hex) == 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        return [
            'r' => hexdec(substr($hex, 0, 2)),
            'g' => hexdec(substr($hex, 2, 2)),
            'b' => hexdec(substr($hex, 4, 2))
        ];
    }
}