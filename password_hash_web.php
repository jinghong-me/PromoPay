<?php
$hash = '';
$password = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
    $password = trim($_POST['password']);
    if ($password !== '') {
        $hash = password_hash($password, PASSWORD_DEFAULT);
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>PHP 密码哈希生成器</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 2em;}
        input[type="text"], input[type="password"] { width: 320px; padding: 0.5em; }
        button { padding: 0.5em 1em; margin-left: 1em;}
        .result { margin-top: 1.5em; color: #217346; font-weight: bold; word-break: break-all;}
        label { font-weight: bold;}
    </style>
</head>
<body>
    <h1>PHP 密码哈希生成器</h1>
    <form method="POST">
        <label for="password">输入明文密码：</label>
        <input type="text" id="password" name="password" value="<?=htmlspecialchars($password)?>" required>
        <button type="submit">生成哈希</button>
    </form>

    <?php if ($hash): ?>
        <div class="result">
            哈希结果：<br>
            <code><?=$hash?></code>
        </div>
    <?php endif; ?>
    <p>将上方结果复制到 config.php 中的 <b>ADMIN_PASS</b> 即可。</p>
</body>
</html>