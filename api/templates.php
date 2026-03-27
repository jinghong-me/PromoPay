<?php
/**
 * 红包模板API
 */
require_once '../config.php';

checkAuth();

header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? '';
$db = Database::getInstance();

switch ($action) {
    case 'list':
        $templates = $db->fetchAll("SELECT * FROM redpacket_templates WHERE status = 1 ORDER BY is_default DESC, id ASC");
        jsonResponse(true, '', $templates);
        break;
        
    case 'get':
        $id = intval($_GET['id'] ?? 0);
        if (!$id) {
            jsonResponse(false, '模板ID不能为空');
        }
        
        $template = $db->fetch("SELECT * FROM redpacket_templates WHERE id = ?", [$id]);
        if (!$template) {
            jsonResponse(false, '模板不存在');
        }
        
        jsonResponse(true, '', $template);
        break;
        
    case 'create':
        checkAdmin();
        
        $data = [
            'name' => $_POST['name'] ?? '',
            'type' => $_POST['type'] ?? 'custom',
            'bg_color' => $_POST['bg_color'] ?? '#FF6B6B',
            'bg_gradient' => $_POST['bg_gradient'] ?? '',
            'title_color' => $_POST['title_color'] ?? '#FFFFFF',
            'amount_color' => $_POST['amount_color'] ?? '#FFD700',
            'button_color' => $_POST['button_color'] ?? '#FFD700',
            'button_text' => $_POST['button_text'] ?? '立即领取',
            'animation_type' => $_POST['animation_type'] ?? 'bounce'
        ];
        
        if (empty($data['name'])) {
            jsonResponse(false, '请输入模板名称');
        }
        
        $id = $db->insert('redpacket_templates', $data);
        jsonResponse(true, '模板创建成功', ['id' => $id]);
        break;
        
    case 'update':
        checkAdmin();
        
        $id = intval($_POST['id'] ?? 0);
        if (!$id) {
            jsonResponse(false, '模板ID不能为空');
        }
        
        $data = [
            'name' => $_POST['name'] ?? '',
            'bg_color' => $_POST['bg_color'] ?? '',
            'bg_gradient' => $_POST['bg_gradient'] ?? '',
            'title_color' => $_POST['title_color'] ?? '',
            'amount_color' => $_POST['amount_color'] ?? '',
            'button_color' => $_POST['button_color'] ?? '',
            'button_text' => $_POST['button_text'] ?? '',
            'animation_type' => $_POST['animation_type'] ?? ''
        ];
        
        $data = array_filter($data, function($v) { return $v !== ''; });
        
        $db->update('redpacket_templates', $data, 'id = :id', ['id' => $id]);
        jsonResponse(true, '模板更新成功');
        break;
        
    case 'delete':
        checkAdmin();
        
        $id = intval($_POST['id'] ?? 0);
        if (!$id) {
            jsonResponse(false, '模板ID不能为空');
        }
        
        // 检查是否被使用
        $used = $db->fetch("SELECT COUNT(*) as count FROM redpackets WHERE template_id = ?", [$id]);
        if ($used['count'] > 0) {
            jsonResponse(false, '该模板已被使用，无法删除');
        }
        
        $db->delete('redpacket_templates', 'id = :id', ['id' => $id]);
        jsonResponse(true, '模板删除成功');
        break;
        
    default:
        jsonResponse(false, '未知操作');
}
