<?php
/**
 * 系统设置API
 */
require_once '../config.php';

checkAdmin();

header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? '';
$db = Database::getInstance();

switch ($action) {
    case 'get':
        try {
            $settings = $db->fetchAll("SELECT `key`, `value`, description FROM settings");
            $result = [];
            foreach ($settings as $setting) {
                $result[$setting['key']] = [
                    'value' => $setting['value'],
                    'description' => $setting['description']
                ];
            }
            jsonResponse(true, '', $result);
        } catch (Exception $e) {
            logError('Get settings failed: ' . $e->getMessage());
            jsonResponse(false, '获取设置失败：' . $e->getMessage());
        }
        break;
        
    case 'save':
        $settingsJson = $_POST['settings'] ?? '';
        $settings = json_decode($settingsJson, true);
        
        if (empty($settings) || !is_array($settings)) {
            jsonResponse(false, '没有要保存的设置');
        }
        
        try {
            foreach ($settings as $key => $value) {
                $db->query("UPDATE settings SET `value` = ? WHERE `key` = ?", [$value, $key]);
            }
            
            logOperation($_SESSION['user_id'], 'update_settings', 'settings', 0, $settings);
            jsonResponse(true, '设置已保存');
        } catch (Exception $e) {
            logError('Save settings failed: ' . $e->getMessage());
            jsonResponse(false, '保存失败：' . $e->getMessage());
        }
        break;
        
    case 'get_single':
        $key = $_GET['key'] ?? '';
        if (empty($key)) {
            jsonResponse(false, '设置键不能为空');
        }
        
        try {
            $setting = $db->fetch("SELECT `value` FROM settings WHERE `key` = ?", [$key]);
            jsonResponse(true, '', $setting ? $setting['value'] : '');
        } catch (Exception $e) {
            logError('Get single setting failed: ' . $e->getMessage());
            jsonResponse(false, '获取设置失败：' . $e->getMessage());
        }
        break;
        
    default:
        jsonResponse(false, '未知操作');
}
