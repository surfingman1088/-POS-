<?php
session_start();

// ===== 管理員密碼設定（請修改這個密碼）=====
define('ADMIN_PASSWORD', 'YoPOS2026Admin!');
define('SECRET_KEY', 'yo-pos-secret-2026');

// 登入處理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    if ($_POST['action'] === 'login') {
        if ($_POST['password'] === ADMIN_PASSWORD) {
            $_SESSION['logged_in'] = true;
            $_SESSION['token'] = md5(SECRET_KEY . date('Y-m-d'));
        } else {
            $login_error = '密碼錯誤，請重試。';
        }
    }
    
    if ($_POST['action'] === 'logout') {
        session_destroy();
        header('Location: /admin/');
        exit;
    }
    
    if ($_POST['action'] === 'add_branch' && isset($_SESSION['logged_in'])) {
        $branch_id = preg_replace('/[^a-z0-9]/', '', strtolower($_POST['branch_id']));
        $branch_name = htmlspecialchars($_POST['branch_name']);
        $branch_port = intval($_POST['branch_port']);
        
        if ($branch_id && $branch_name && $branch_port >= 8007 && $branch_port <= 8099) {
            // 執行新增分店腳本
            $script = "/opt/yo-pos-source/scripts/add-branch.sh";
            $cmd = "sudo bash $script " . escapeshellarg($branch_id) . " " . escapeshellarg($branch_name) . " " . escapeshellarg($branch_port) . " >> /tmp/pos-admin-log.txt 2>&1 &";
            exec($cmd);
            $success_msg = "分店「{$branch_name}」正在建立中，約需 2-3 分鐘，請稍後重新整理頁面確認。";
        } else {
            $error_msg = '請確認分店資訊正確（埠號需在 8007-8099 之間）。';
        }
    }
}

// 未登入則顯示登入頁
$is_logged_in = isset($_SESSION['logged_in']) && $_SESSION['logged_in'];

// 取得目前所有分店狀態
function getBranches() {
    $branches = [];
    $ports = range(8001, 8020);
    foreach ($ports as $port) {
        $result = @fsockopen('127.0.0.1', $port, $errno, $errstr, 1);
        if ($result) {
            fclose($result);
            // 嘗試取得分店名稱
            $name = getBranchName($port);
            $branches[] = [
                'port' => $port,
                'name' => $name,
                'status' => 'running',
                'url' => "http://" . explode(':', $_SERVER['HTTP_HOST'])[0] . ":" . $port
            ];
        }
    }
    return $branches;
}

function getBranchName($port) {
    $map = [
        8001 => '八德店',
        8002 => '三峽店',
        8003 => '大竹店',
        8004 => '林口店',
        8005 => '藝文店',
        8006 => '菓林店',
    ];
    if (isset($map[$port])) return $map[$port];
    
    // 嘗試從 Nginx 設定讀取分店名稱
    $nginx_conf = "/etc/nginx/sites-enabled/pos-port-{$port}.conf";
    if (file_exists($nginx_conf)) {
        $content = file_get_contents($nginx_conf);
        if (preg_match('/# Branch: (.+)/', $content, $m)) {
            return trim($m[1]);
        }
    }
    return "分店（Port {$port}）";
}

// 取得下一個可用埠號
function getNextPort() {
    for ($port = 8007; $port <= 8099; $port++) {
        $result = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.5);
        if (!$result) return $port;
        fclose($result);
    }
    return 8099;
}

$branches = $is_logged_in ? getBranches() : [];
$next_port = $is_logged_in ? getNextPort() : 8007;
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>YO 團購 POS — 分店管理後台</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f0f2f5; min-height: 100vh; }
        
        /* 登入頁 */
        .login-container {
            display: flex; align-items: center; justify-content: center;
            min-height: 100vh; background: linear-gradient(135deg, #1e3a5f 0%, #2d6a4f 100%);
        }
        .login-box {
            background: white; border-radius: 16px; padding: 48px 40px;
            width: 100%; max-width: 400px; box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        .login-logo { text-align: center; margin-bottom: 32px; }
        .login-logo h1 { font-size: 28px; color: #1e3a5f; font-weight: 700; }
        .login-logo p { color: #666; margin-top: 8px; font-size: 14px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-size: 14px; font-weight: 600; color: #333; margin-bottom: 8px; }
        .form-group input {
            width: 100%; padding: 12px 16px; border: 2px solid #e0e0e0;
            border-radius: 8px; font-size: 16px; transition: border-color 0.2s;
        }
        .form-group input:focus { outline: none; border-color: #1e3a5f; }
        .btn-primary {
            width: 100%; padding: 14px; background: #1e3a5f; color: white;
            border: none; border-radius: 8px; font-size: 16px; font-weight: 600;
            cursor: pointer; transition: background 0.2s;
        }
        .btn-primary:hover { background: #2d5a8f; }
        .error-msg { background: #fee2e2; color: #dc2626; padding: 12px; border-radius: 8px; margin-bottom: 16px; font-size: 14px; }
        
        /* 主介面 */
        .header {
            background: #1e3a5f; color: white; padding: 16px 32px;
            display: flex; align-items: center; justify-content: space-between;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        }
        .header h1 { font-size: 20px; font-weight: 700; }
        .header-right { display: flex; align-items: center; gap: 16px; }
        .btn-logout {
            background: rgba(255,255,255,0.15); color: white; border: 1px solid rgba(255,255,255,0.3);
            padding: 8px 16px; border-radius: 6px; cursor: pointer; font-size: 14px;
            transition: background 0.2s;
        }
        .btn-logout:hover { background: rgba(255,255,255,0.25); }
        
        .main-content { padding: 32px; max-width: 1200px; margin: 0 auto; }
        
        /* 統計卡片 */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 32px; }
        .stat-card {
            background: white; border-radius: 12px; padding: 24px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        .stat-card .label { font-size: 13px; color: #666; margin-bottom: 8px; }
        .stat-card .value { font-size: 36px; font-weight: 700; color: #1e3a5f; }
        .stat-card .sub { font-size: 13px; color: #888; margin-top: 4px; }
        
        /* 分店列表 */
        .section { background: white; border-radius: 12px; padding: 28px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); margin-bottom: 24px; }
        .section-title { font-size: 18px; font-weight: 700; color: #1e3a5f; margin-bottom: 20px; display: flex; align-items: center; gap: 8px; }
        
        .branch-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 16px; }
        .branch-card {
            border: 2px solid #e8f4f8; border-radius: 10px; padding: 20px;
            transition: all 0.2s; position: relative;
        }
        .branch-card:hover { border-color: #1e3a5f; box-shadow: 0 4px 12px rgba(30,58,95,0.1); }
        .branch-card .branch-name { font-size: 18px; font-weight: 700; color: #1e3a5f; margin-bottom: 8px; }
        .branch-card .branch-port { font-size: 13px; color: #888; margin-bottom: 12px; }
        .status-badge {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 4px 10px; border-radius: 20px; font-size: 12px; font-weight: 600;
        }
        .status-running { background: #dcfce7; color: #16a34a; }
        .status-dot { width: 8px; height: 8px; border-radius: 50%; background: currentColor; animation: pulse 2s infinite; }
        @keyframes pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.5; } }
        
        .branch-actions { margin-top: 16px; display: flex; gap: 8px; }
        .btn-visit {
            flex: 1; padding: 8px 12px; background: #1e3a5f; color: white;
            border: none; border-radius: 6px; font-size: 13px; cursor: pointer;
            text-decoration: none; text-align: center; transition: background 0.2s;
        }
        .btn-visit:hover { background: #2d5a8f; }
        
        /* 新增分店表單 */
        .add-form { display: grid; grid-template-columns: 1fr 1fr auto auto; gap: 12px; align-items: end; }
        .form-field label { display: block; font-size: 13px; font-weight: 600; color: #555; margin-bottom: 6px; }
        .form-field input, .form-field select {
            width: 100%; padding: 10px 14px; border: 2px solid #e0e0e0;
            border-radius: 8px; font-size: 14px; transition: border-color 0.2s;
        }
        .form-field input:focus, .form-field select:focus { outline: none; border-color: #1e3a5f; }
        .btn-add {
            padding: 10px 24px; background: #2d6a4f; color: white;
            border: none; border-radius: 8px; font-size: 14px; font-weight: 600;
            cursor: pointer; white-space: nowrap; transition: background 0.2s; height: 42px;
        }
        .btn-add:hover { background: #1b4332; }
        
        .success-msg { background: #dcfce7; color: #16a34a; padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; font-size: 14px; }
        .error-msg-inline { background: #fee2e2; color: #dc2626; padding: 12px 16px; border-radius: 8px; margin-bottom: 16px; font-size: 14px; }
        
        .hint { font-size: 12px; color: #888; margin-top: 8px; }
        
        @media (max-width: 768px) {
            .add-form { grid-template-columns: 1fr; }
            .main-content { padding: 16px; }
        }
    </style>
</head>
<body>

<?php if (!$is_logged_in): ?>
<!-- 登入頁面 -->
<div class="login-container">
    <div class="login-box">
        <div class="login-logo">
            <h1>🏪 YO 團購 POS</h1>
            <p>分店管理後台</p>
        </div>
        <?php if (isset($login_error)): ?>
            <div class="error-msg"><?= $login_error ?></div>
        <?php endif; ?>
        <form method="POST">
            <input type="hidden" name="action" value="login">
            <div class="form-group">
                <label>管理員密碼</label>
                <input type="password" name="password" placeholder="請輸入管理員密碼" autofocus required>
            </div>
            <button type="submit" class="btn-primary">登入管理後台</button>
        </form>
    </div>
</div>

<?php else: ?>
<!-- 主介面 -->
<div class="header">
    <h1>🏪 YO 團購 POS — 分店管理後台</h1>
    <div class="header-right">
        <span style="font-size:14px; opacity:0.8;">目前運行中：<?= count($branches) ?> 間分店</span>
        <form method="POST" style="margin:0;">
            <input type="hidden" name="action" value="logout">
            <button type="submit" class="btn-logout">登出</button>
        </form>
    </div>
</div>

<div class="main-content">
    
    <!-- 統計 -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="label">運行中分店</div>
            <div class="value"><?= count($branches) ?></div>
            <div class="sub">所有分店正常運作</div>
        </div>
        <div class="stat-card">
            <div class="label">下一個可用埠號</div>
            <div class="value"><?= $next_port ?></div>
            <div class="sub">新增分店時使用</div>
        </div>
        <div class="stat-card">
            <div class="label">伺服器狀態</div>
            <div class="value" style="color:#16a34a; font-size:24px;">● 正常</div>
            <div class="sub">Vultr Tokyo VPS</div>
        </div>
    </div>
    
    <!-- 新增分店 -->
    <div class="section">
        <div class="section-title">➕ 新增分店</div>
        
        <?php if (isset($success_msg)): ?>
            <div class="success-msg">✅ <?= $success_msg ?></div>
        <?php endif; ?>
        <?php if (isset($error_msg)): ?>
            <div class="error-msg-inline">❌ <?= $error_msg ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <input type="hidden" name="action" value="add_branch">
            <div class="add-form">
                <div class="form-field">
                    <label>分店名稱（中文）</label>
                    <input type="text" name="branch_name" placeholder="例如：中壢店" required>
                </div>
                <div class="form-field">
                    <label>分店代碼（英文小寫）</label>
                    <input type="text" name="branch_id" placeholder="例如：zhongli" pattern="[a-z0-9]+" required>
                </div>
                <div class="form-field">
                    <label>埠號</label>
                    <input type="number" name="branch_port" value="<?= $next_port ?>" min="8007" max="8099" required>
                </div>
                <div class="form-field">
                    <label>&nbsp;</label>
                    <button type="submit" class="btn-add">建立分店</button>
                </div>
            </div>
            <p class="hint">💡 分店代碼只能使用英文小寫和數字（例如：zhongli、taoyuan2）。建立完成後約需 2-3 分鐘，請稍後重新整理頁面。</p>
        </form>
    </div>
    
    <!-- 分店列表 -->
    <div class="section">
        <div class="section-title">🏪 目前所有分店</div>
        <div class="branch-grid">
            <?php foreach ($branches as $branch): ?>
            <div class="branch-card">
                <div class="branch-name"><?= $branch['name'] ?></div>
                <div class="branch-port">埠號：<?= $branch['port'] ?></div>
                <span class="status-badge status-running">
                    <span class="status-dot"></span>
                    運行中
                </span>
                <div class="branch-actions">
                    <a href="<?= $branch['url'] ?>" target="_blank" class="btn-visit">🔗 開啟分店系統</a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    
</div>
<?php endif; ?>

</body>
</html>
