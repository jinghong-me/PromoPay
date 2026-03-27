<?php
/**
 * 创建红包页面
 */
require_once '../config.php';
checkAuth();

// 获取模板列表
$db = Database::getInstance();
$templates = $db->fetchAll("SELECT * FROM redpacket_templates WHERE status = 1 ORDER BY is_default DESC, id ASC");
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>创建红包 - <?php echo SITE_NAME; ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'PingFang SC', 'Hiragino Sans GB', 'Microsoft YaHei', sans-serif;
            background: #f5f7fa;
            min-height: 100vh;
        }
        

        
        .main-content {
            margin-left: 260px;
            min-height: 100vh;
        }
        
        .topbar {
            background: white;
            padding: 16px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            position: sticky;
            top: 0;
            z-index: 99;
        }
        
        .topbar-title {
            font-size: 20px;
            font-weight: 600;
            color: #333;
        }
        

        
        .content { padding: 30px; }
        
        .form-container {
            background: white;
            border-radius: 16px;
            padding: 40px;
            max-width: 800px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.04);
        }
        
        .form-section {
            margin-bottom: 32px;
        }
        
        .form-section-title {
            font-size: 16px;
            font-weight: 600;
            color: #333;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group.full-width {
            grid-column: 1 / -1;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-size: 14px;
            color: #555;
            font-weight: 500;
        }
        
        .form-group label .required {
            color: #ff4d4f;
            margin-left: 4px;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #e8e8e8;
            border-radius: 10px;
            font-size: 14px;
            transition: all 0.3s;
            font-family: inherit;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }
        
        .form-group .help-text {
            font-size: 12px;
            color: #999;
            margin-top: 6px;
        }
        
        /* 模板选择 */
        .template-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 16px;
        }
        
        .template-item {
            border: 2px solid #e8e8e8;
            border-radius: 12px;
            padding: 16px;
            cursor: pointer;
            text-align: center;
            transition: all 0.3s;
        }
        
        .template-item:hover {
            border-color: #667eea;
        }
        
        .template-item.selected {
            border-color: #667eea;
            background: rgba(102, 126, 234, 0.05);
        }
        
        .template-preview {
            height: 80px;
            border-radius: 8px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
        }
        
        .template-name {
            font-size: 13px;
            color: #333;
        }
        
        /* 类型选择 */
        .type-options {
            display: flex;
            gap: 16px;
        }
        
        .type-option {
            flex: 1;
            border: 2px solid #e8e8e8;
            border-radius: 12px;
            padding: 20px;
            cursor: pointer;
            text-align: center;
            transition: all 0.3s;
        }
        
        .type-option:hover {
            border-color: #667eea;
        }
        
        .type-option.selected {
            border-color: #667eea;
            background: rgba(102, 126, 234, 0.05);
        }
        
        .type-option .icon {
            width: 48px;
            height: 48px;
            margin: 0 auto 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .type-option .icon svg {
            width: 24px;
            height: 24px;
            fill: white;
        }
        
        .type-option .name {
            font-weight: 600;
            color: #333;
            margin-bottom: 4px;
        }
        
        .type-option .desc {
            font-size: 12px;
            color: #999;
        }
        
        /* 按钮 */
        .form-actions {
            display: flex;
            gap: 16px;
            justify-content: flex-end;
            padding-top: 20px;
            border-top: 1px solid #f0f0f0;
        }
        
        .btn {
            padding: 12px 32px;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
        }
        
        .btn-primary:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }
        
        .btn-secondary {
            background: #f5f5f5;
            color: #666;
        }
        
        .btn-secondary:hover { background: #e8e8e8; }
        
        /* 结果弹窗 */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.6);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }
        
        .modal-overlay.active { display: flex; }
        
        .modal-content {
            background: white;
            border-radius: 20px;
            padding: 40px;
            max-width: 500px;
            width: 90%;
            text-align: center;
        }
        
        .modal-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 24px;
            background: linear-gradient(135deg, #52c41a 0%, #73d13d 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .modal-icon svg {
            width: 45px;
            height: 45px;
            fill: white;
        }
        
        .modal-title {
            font-size: 24px;
            font-weight: 700;
            color: #333;
            margin-bottom: 12px;
        }
        
        .modal-message {
            color: #666;
            margin-bottom: 24px;
        }
        
        .qrcode-result {
            margin: 20px 0;
        }
        
        .qrcode-result img {
            max-width: 200px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .qrcode-code {
            font-family: monospace;
            background: #f5f5f5;
            padding: 8px 16px;
            border-radius: 6px;
            margin-top: 12px;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <?php require_once 'includes/sidebar.php'; ?>
    
    <main class="main-content">
        <header class="topbar">
            <h1 class="topbar-title">创建红包</h1>
        </header>
        
        <div class="content">
            <div class="form-container">
                <form id="createForm">
                    <!-- 红包类型 -->
                    <div class="form-section">
                        <h3 class="form-section-title">选择红包类型</h3>
                        <div class="type-options">
                            <div class="type-option selected" data-type="single" onclick="selectType(this)">
                                <div class="icon">
                                    <svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
                                </div>
                                <div class="name">一次性红包</div>
                                <div class="desc">领取后即失效</div>
                            </div>
                            <div class="type-option" data-type="batch" onclick="selectType(this)">
                                <div class="icon">
                                    <svg viewBox="0 0 24 24"><path d="M20 4H4c-1.11 0-1.99.89-1.99 2L2 18c0 1.11.89 2 2 2h16c1.11 0 2-.89 2-2V6c0-1.11-.89-2-2-2zm0 14H4v-6h16v6zm0-10H4V6h16v2z"/></svg>
                                </div>
                                <div class="name">批量红包</div>
                                <div class="desc">生成多个独立红包</div>
                            </div>
                            <div class="type-option" data-type="multiple" onclick="selectType(this)">
                                <div class="icon">
                                    <svg viewBox="0 0 24 24"><path d="M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5z"/></svg>
                                </div>
                                <div class="name">多次领取</div>
                                <div class="desc">可限制领取次数</div>
                            </div>
                        </div>
                        <input type="hidden" name="type" id="typeInput" value="single">
                    </div>
                    
                    <!-- 基本信息 -->
                    <div class="form-section">
                        <h3 class="form-section-title">基本信息</h3>
                        <div class="form-row">
                            <div class="form-group full-width">
                                <label>红包名称 <span class="required">*</span></label>
                                <input type="text" name="name" placeholder="例如：课程推广返现" required>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group full-width">
                                <label>红包描述</label>
                                <textarea name="description" placeholder="可选，填写红包的详细说明"></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <!-- 金额设置 -->
                    <div class="form-section">
                        <h3 class="form-section-title">金额设置</h3>
                        <div class="form-row">
                            <div class="form-group">
                                <label>单个红包金额 <span class="required">*</span></label>
                                <input type="number" name="amount_per_packet" step="0.01" min="0.01" placeholder="例如：10.80" required>
                                <div class="help-text">单位：元，精确到分</div>
                            </div>
                            <div class="form-group">
                                <label>红包数量 <span class="required">*</span></label>
                                <input type="number" name="total_quantity" min="1" value="1" required>
                                <div class="help-text">可领取的总次数</div>
                            </div>
                        </div>
                        <div class="form-row" id="batchCountRow" style="display: none;">
                            <div class="form-group">
                                <label>批量生成数量</label>
                                <input type="number" name="batch_count" min="1" value="1">
                                <div class="help-text">一次性生成多个独立红包</div>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label>每人限领次数</label>
                                <input type="number" name="max_claims_per_user" min="1" value="1">
                                <div class="help-text">每个用户最多可领取次数</div>
                            </div>
                            <div class="form-group">
                                <label>有效期（小时）</label>
                                <input type="number" name="valid_hours" min="0" value="24">
                                <div class="help-text">填0表示长期有效，按领取次数控制</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- 模板选择 -->
                    <div class="form-section">
                        <h3 class="form-section-title">选择模板</h3>
                        <div class="template-grid">
                            <?php foreach ($templates as $template): ?>
                            <div class="template-item <?php echo $template['is_default'] ? 'selected' : ''; ?>" 
                                 data-id="<?php echo $template['id']; ?>"
                                 onclick="selectTemplate(this)">
                                <div class="template-preview" style="background: <?php echo $template['bg_gradient'] ?: $template['bg_color']; ?>">
                                    ¥
                                </div>
                                <div class="template-name"><?php echo htmlspecialchars($template['name']); ?></div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <input type="hidden" name="template_id" id="templateInput" value="<?php echo $templates[0]['id']; ?>">
                    </div>
                    
                    <!-- 备注 -->
                    <div class="form-section">
                        <h3 class="form-section-title">其他</h3>
                        <div class="form-row">
                            <div class="form-group full-width">
                                <label>微信转账备注</label>
                                <input type="text" name="transfer_remark" value="课程推广返现" placeholder="用户微信零钱到账显示的备注">
                                <div class="help-text">显示在微信零钱账单中的备注信息</div>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group full-width">
                                <label>内部备注 <span style="color: #ff4d4f;">*</span></label>
                                <textarea name="remark" placeholder="请输入内部备注，用于后台识别" required></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <a href="/admin/redpackets.php" class="btn btn-secondary">取消</a>
                        <button type="submit" class="btn btn-primary" id="submitBtn">创建红包</button>
                    </div>
                </form>
            </div>
        </div>
    </main>
    
    <!-- 成功弹窗 -->
    <div class="modal-overlay" id="successModal">
        <div class="modal-content">
            <div class="modal-icon">
                <svg viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.41 1.41L9 19 21 7l-1.41-1.41z"/></svg>
            </div>
            <h3 class="modal-title">红包创建成功！</h3>
            <p class="modal-message">请保存以下二维码，用户微信扫码即可领取</p>
            <div class="qrcode-result">
                <img src="" alt="红包二维码" id="resultQrcode">
                <div class="qrcode-code" id="resultCode"></div>
            </div>
            <div style="display: flex; gap: 12px;">
                <a href="/admin/redpackets.php" class="btn btn-secondary" style="flex: 1; text-align: center; text-decoration: none;">关闭</a>
                <button class="btn btn-primary" onclick="closeModalAndContinue()" style="flex: 1;">继续创建</button>
            </div>
        </div>
    </div>
    
    <script>
        // 页面加载时初始化
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const editId = urlParams.get('edit');

            if (editId) {
                loadRedpacketForEdit(editId);
            } else {
                // 创建模式，初始化默认类型（一次性红包）
                const defaultType = document.querySelector('.type-option[data-type="single"]');
                if (defaultType) {
                    selectType(defaultType);
                }
            }
        });

        // 加载红包数据进行编辑
        async function loadRedpacketForEdit(id) {
            try {
                const response = await fetch(`/api/redpackets.php?action=get&id=${id}`);
                const result = await response.json();

                if (result.success) {
                    const data = result.data;

                    // 填充表单数据
                    document.querySelector('input[name="name"]').value = data.name;
                    document.querySelector('textarea[name="description"]').value = data.description || '';
                    document.querySelector('input[name="amount_per_packet"]').value = parseFloat(data.amount_per_packet).toFixed(2);
                    document.querySelector('input[name="total_quantity"]').value = data.total_quantity;
                    document.querySelector('input[name="max_claims_per_user"]').value = data.max_claims_per_user;
                    document.querySelector('input[name="valid_hours"]').value = data.valid_hours;
                    document.querySelector('input[name="transfer_remark"]').value = data.transfer_remark || '';
                    document.querySelector('textarea[name="remark"]').value = data.remark || '';

                    // 设置红包类型
                    const typeOption = document.querySelector(`.type-option[data-type="${data.type}"]`);
                    if (typeOption) {
                        selectType(typeOption);
                    }

                    // 设置模板
                    const templateOption = document.querySelector(`.template-item[data-id="${data.template_id}"]`);
                    if (templateOption) {
                        selectTemplate(templateOption);
                    }

                    // 修改页面标题和按钮
                    document.querySelector('.topbar-title').textContent = '编辑红包';
                    document.getElementById('submitBtn').textContent = '保存修改';

                    // 添加 hidden input 用于标识是编辑操作
                    const hiddenInput = document.createElement('input');
                    hiddenInput.type = 'hidden';
                    hiddenInput.name = 'edit_id';
                    hiddenInput.value = id;
                    document.getElementById('createForm').appendChild(hiddenInput);
                } else {
                    alert(result.message || '加载失败');
                    window.location.href = '/admin/redpackets.php';
                }
            } catch (error) {
                alert('加载失败');
            }
        }

        // 选择红包类型
        function selectType(element) {
            document.querySelectorAll('.type-option').forEach(el => el.classList.remove('selected'));
            element.classList.add('selected');
            const type = element.dataset.type;
            document.getElementById('typeInput').value = type;

            // 显示/隐藏批量数量
            document.getElementById('batchCountRow').style.display =
                type === 'batch' ? 'block' : 'none';

            // 一次性红包固定数量和限领次数为1
            const quantityInput = document.querySelector('input[name="total_quantity"]');
            const maxClaimsInput = document.querySelector('input[name="max_claims_per_user"]');

            if (type === 'single' || type === 'batch') {
                quantityInput.value = 1;
                quantityInput.readOnly = true;
                quantityInput.style.background = '#f5f5f5';
                maxClaimsInput.value = 1;
                maxClaimsInput.readOnly = true;
                maxClaimsInput.style.background = '#f5f5f5';
            } else {
                quantityInput.readOnly = false;
                quantityInput.style.background = '';
                maxClaimsInput.readOnly = false;
                maxClaimsInput.style.background = '';
            }
        }
        
        // 选择模板
        function selectTemplate(element) {
            document.querySelectorAll('.template-item').forEach(el => el.classList.remove('selected'));
            element.classList.add('selected');
            document.getElementById('templateInput').value = element.dataset.id;
        }
        
        // 提交表单
        document.getElementById('createForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const btn = document.getElementById('submitBtn');
            const isEdit = document.querySelector('input[name="edit_id"]') !== null;
            
            btn.disabled = true;
            btn.textContent = isEdit ? '保存中...' : '创建中...';
            
            const formData = new FormData(this);
            
            try {
                const response = await fetch('/api/redpackets.php?action=create', {
                    method: 'POST',
                    body: formData
                });

                // 检查响应内容类型
                const contentType = response.headers.get('content-type');
                console.log('响应内容类型:', contentType);

                // 获取原始响应文本
                const responseText = await response.text();
                console.log('原始响应:', responseText.substring(0, 500));

                // 尝试解析 JSON
                let result;
                try {
                    result = JSON.parse(responseText);
                } catch (e) {
                    console.error('JSON解析失败:', e);
                    alert('服务器返回错误: ' + responseText.substring(0, 200));
                    return;
                }

                console.log('API返回结果:', result);
                if (result.success) {
                    // 显示成功弹窗
                    document.getElementById('resultQrcode').src = result.data.qrcode;
                    document.getElementById('resultCode').textContent = result.data.code;
                    document.getElementById('successModal').classList.add('active');

                    // 重置表单
                    this.reset();
                    selectType(document.querySelector('.type-option[data-type="single"]'));
                    selectTemplate(document.querySelector('.template-item'));
                } else {
                    alert('创建失败: ' + (result.message || '未知错误'));
                }
            } catch (error) {
                console.error('创建异常:', error);
                alert('创建失败，请重试: ' + error.message);
            } finally {
                btn.disabled = false;
                btn.textContent = isEdit ? '保存修改' : '创建红包';
            }
        });
        
        function closeModalAndContinue() {
            document.getElementById('successModal').classList.remove('active');
            // 重置表单
            document.getElementById('createForm').reset();
            // 重置模板选择
            document.querySelectorAll('.template-item').forEach((el, index) => {
                el.classList.toggle('selected', index === 0);
            });
            document.getElementById('templateInput').value = document.querySelector('.template-item').dataset.id;
        }
    </script>
</body>
</html>