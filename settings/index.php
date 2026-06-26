<?php
// ============================================
// SETTINGS - Client Configures Their Bot
// ============================================

session_start();

// Admin password (you can also store this in environment)
$admin_password = getenv('ADMIN_PASSWORD') ?: 'admin123'; // ← CHANGE THIS!

// ============================================
// DATABASE CONNECTION
// ============================================

$db_url = getenv('DATABASE_URL');
if (!$db_url) {
    die('DATABASE_URL environment variable not set.');
}

$db = parse_url($db_url);
$dsn = "pgsql:host={$db['host']};port={$db['port']};dbname=" . ltrim($db['path'], '/');
$user = $db['user'];
$pass = $db['pass'];

try {
    $pdo = new PDO($dsn, $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create settings table if not exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS app_settings (
        key TEXT PRIMARY KEY,
        value TEXT,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
} catch (PDOException $e) {
    die('Database error: ' . $e->getMessage());
}

// ============================================
// LOAD CURRENT SETTINGS
// ============================================

$botToken = '';
$chatId = '';
$stmt = $pdo->query("SELECT key, value FROM app_settings WHERE key IN ('bot_token', 'chat_id')");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    if ($row['key'] === 'bot_token') $botToken = $row['value'];
    if ($row['key'] === 'chat_id') $chatId = $row['value'];
}

// ============================================
// HANDLE LOGIN
// ============================================

$is_logged_in = false;

if (isset($_POST['login_password'])) {
    if ($_POST['login_password'] === $admin_password) {
        $_SESSION['admin_logged_in'] = true;
        header('Location: index.php');
        exit;
    } else {
        $login_error = "❌ Incorrect password!";
    }
}

if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    $is_logged_in = true;
}

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}

// ============================================
// HANDLE SAVE SETTINGS
// ============================================

if ($is_logged_in && isset($_POST['save_settings'])) {
    $newBotToken = trim($_POST['bot_token'] ?? '');
    $newChatId = trim($_POST['chat_id'] ?? '');
    
    if (empty($newBotToken) || empty($newChatId)) {
        $save_error = "❌ Both fields are required!";
    } else {
        try {
            // Use UPSERT (INSERT ... ON CONFLICT DO UPDATE)
            $pdo->exec("INSERT INTO app_settings (key, value) VALUES ('bot_token', " . $pdo->quote($newBotToken) . ") 
                        ON CONFLICT (key) DO UPDATE SET value = EXCLUDED.value, updated_at = CURRENT_TIMESTAMP");
            $pdo->exec("INSERT INTO app_settings (key, value) VALUES ('chat_id', " . $pdo->quote($newChatId) . ") 
                        ON CONFLICT (key) DO UPDATE SET value = EXCLUDED.value, updated_at = CURRENT_TIMESTAMP");
            $save_success = "✅ Settings saved successfully!";
            
            // Reload new values
            $botToken = $newBotToken;
            $chatId = $newChatId;
        } catch (PDOException $e) {
            $save_error = "❌ Database error: " . $e->getMessage();
        }
    }
}

// ============================================
// SHOW LOGIN FORM OR SETTINGS PAGE
// ============================================

if (!$is_logged_in) {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Settings - Admin Login</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body {
                background: #f5f5f5;
                color: #333;
                font-family: 'Segoe UI', Arial, sans-serif;
                display: flex;
                justify-content: center;
                align-items: center;
                min-height: 100vh;
                padding: 20px;
            }
            .login-box {
                background: #fff;
                padding: 40px;
                border-radius: 8px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.1);
                max-width: 400px;
                width: 100%;
            }
            .login-box h1 { font-size: 24px; margin-bottom: 8px; text-align: center; }
            .login-box p { color: #666; margin-bottom: 25px; text-align: center; }
            .login-box input {
                width: 100%;
                padding: 12px;
                border: 1px solid #ddd;
                border-radius: 4px;
                font-size: 16px;
                margin-bottom: 15px;
            }
            .login-box input:focus { outline: none; border-color: #0067b8; }
            .login-box button {
                width: 100%;
                padding: 12px;
                background: #0067b8;
                color: #fff;
                border: none;
                border-radius: 4px;
                font-size: 16px;
                font-weight: 600;
                cursor: pointer;
            }
            .login-box button:hover { background: #005da6; }
            .error { color: #d32f2f; margin-top: 15px; text-align: center; }
        </style>
    </head>
    <body>
        <div class="login-box">
            <h1>🔐 Settings Access</h1>
            <p>Enter your password to manage bot settings</p>
            <form method="POST">
                <input type="password" name="login_password" placeholder="Enter password" required autofocus>
                <button type="submit">Access Settings</button>
                <?php if (isset($login_error)): ?>
                    <div class="error"><?php echo $login_error; ?></div>
                <?php endif; ?>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// ============================================
// SETTINGS PAGE (Logged in)
// ============================================

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Bot Configuration</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            background: #f5f5f5;
            color: #333;
            font-family: 'Segoe UI', Arial, sans-serif;
            padding: 20px;
            min-height: 100vh;
        }
        .container { max-width: 600px; margin: 0 auto; }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 0;
            border-bottom: 1px solid #ddd;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 15px;
        }
        .header h1 { font-size: 28px; font-weight: 700; color: #333; }
        .header h1 span { color: #0067b8; }
        .header-actions { display: flex; gap: 10px; }
        .header-actions a {
            padding: 10px 20px;
            border-radius: 4px;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s;
        }
        .btn-dashboard { background: #0067b8; color: #fff; }
        .btn-dashboard:hover { background: #005da6; }
        .btn-logout { background: #d32f2f; color: #fff; }
        .btn-logout:hover { background: #b71c1c; }

        .card {
            background: #fff;
            border-radius: 8px;
            padding: 30px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        .card h2 { margin-bottom: 10px; font-size: 20px; }
        .card p { color: #666; font-size: 14px; margin-bottom: 20px; }

        .current-settings {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .current-settings .label { color: #666; font-size: 12px; }
        .current-settings .value { color: #333; font-size: 14px; font-family: monospace; word-break: break-all; }
        .status-active { background: #e8f5e9; color: #2e7d32; display: inline-block; padding: 3px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; }
        .status-inactive { background: #fce4ec; color: #c62828; display: inline-block; padding: 3px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; }

        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; color: #666; font-size: 14px; margin-bottom: 8px; font-weight: 600; }
        .form-group input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            font-family: monospace;
        }
        .form-group input:focus { outline: none; border-color: #0067b8; }
        .form-group .hint { color: #999; font-size: 12px; margin-top: 5px; }
        .form-group .hint a { color: #0067b8; text-decoration: none; }
        .form-group .hint a:hover { text-decoration: underline; }

        .btn-save {
            width: 100%;
            padding: 12px;
            background: #0067b8;
            color: #fff;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.3s;
        }
        .btn-save:hover { background: #005da6; }

        .message {
            padding: 12px 16px;
            border-radius: 4px;
            margin-bottom: 15px;
        }
        .message-success { background: #e8f5e9; color: #2e7d32; border: 1px solid #c8e6c9; }
        .message-error { background: #fce4ec; color: #c62828; border: 1px solid #f5c6cb; }

        .help-box {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-top: 15px;
        }
        .help-box h4 { color: #666; margin-bottom: 8px; }
        .help-box ol { color: #555; font-size: 13px; padding-left: 20px; line-height: 1.8; }
        .help-box code { background: #e8e8e8; padding: 2px 6px; border-radius: 4px; color: #0067b8; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>⚙️ Bot <span>Settings</span></h1>
            <div class="header-actions">
                <a href="../admin/dashboard.php" class="btn-dashboard">← Dashboard</a>
                <a href="index.php?logout=1" class="btn-logout">🚪 Logout</a>
            </div>
        </div>

        <?php if (isset($save_success)): ?>
            <div class="message message-success"><?php echo $save_success; ?></div>
        <?php endif; ?>
        <?php if (isset($save_error)): ?>
            <div class="message message-error"><?php echo $save_error; ?></div>
        <?php endif; ?>

        <div class="card">
            <h2>📊 Current Settings</h2>
            <div class="current-settings">
                <div class="label">🤖 Bot Token</div>
                <div class="value">
                    <?php echo !empty($botToken) ? substr($botToken, 0, 10) . '...' . substr($botToken, -5) : 'Not configured'; ?>
                </div>
                <div class="label" style="margin-top: 10px;">📱 Chat ID</div>
                <div class="value"><?php echo !empty($chatId) ? $chatId : 'Not configured'; ?></div>
                <div style="margin-top: 10px;">
                    <span class="<?php echo (!empty($botToken) && !empty($chatId)) ? 'status-active' : 'status-inactive'; ?>">
                        <?php echo (!empty($botToken) && !empty($chatId)) ? '✅ Connected' : '❌ Not Configured'; ?>
                    </span>
                </div>
            </div>
        </div>

        <div class="card">
            <h2>⚙️ Configure Bot</h2>
            <p>Enter your Telegram bot token and chat ID to receive sign-in notifications.</p>

            <form method="POST">
                <div class="form-group">
                    <label for="bot_token">🤖 Bot Token</label>
                    <input type="text" id="bot_token" name="bot_token" placeholder="1234567890:ABCdefGHIjklMNOpqrsTUVwxyz" value="<?php echo htmlspecialchars($botToken); ?>">
                    <div class="hint">Get this from <a href="https://t.me/botfather" target="_blank">@BotFather</a></div>
                </div>

                <div class="form-group">
                    <label for="chat_id">📱 Chat ID</label>
                    <input type="text" id="chat_id" name="chat_id" placeholder="123456789" value="<?php echo htmlspecialchars($chatId); ?>">
                    <div class="hint">Get your Chat ID from <a href="https://t.me/userinfobot" target="_blank">@userinfobot</a></div>
                </div>

                <button type="submit" name="save_settings" class="btn-save">💾 Save Settings</button>
            </form>
        </div>

        <div class="card">
            <h2>📖 How to Get Your Bot Token & Chat ID</h2>
            <div class="help-box">
                <h4>🤖 Get Bot Token:</h4>
                <ol>
                    <li>Open Telegram and search for <code>@BotFather</code></li>
                    <li>Send <code>/newbot</code> and follow instructions</li>
                    <li>Copy the token you receive</li>
                </ol>
                <br>
                <h4>📱 Get Chat ID:</h4>
                <ol>
                    <li>Open Telegram and search for <code>@userinfobot</code></li>
                    <li>Send <code>/start</code></li>
                    <li>Copy your user ID</li>
                </ol>
            </div>
        </div>
    </div>
</body>
</html>
