<?php
// ============================================
// API: Get Bot Settings from Database
// ============================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$db_url = getenv('DATABASE_URL');
if (!$db_url) {
    // Fallback to environment (for initial setup)
    $botToken = getenv('BOT_TOKEN') ?: '';
    $chatId = getenv('CHAT_ID') ?: '';
    if ($botToken && $chatId) {
        echo json_encode(['status' => 'success', 'bot_token' => $botToken, 'chat_id' => $chatId]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Bot not configured']);
    }
    exit;
}

$db = parse_url($db_url);
$dsn = "pgsql:host={$db['host']};port={$db['port']};dbname=" . ltrim($db['path'], '/');
$user = $db['user'];
$pass = $db['pass'];

try {
    $pdo = new PDO($dsn, $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Ensure table exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS app_settings (
        key TEXT PRIMARY KEY,
        value TEXT,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Fetch bot_token and chat_id
    $stmt = $pdo->query("SELECT key, value FROM app_settings WHERE key IN ('bot_token', 'chat_id')");
    $botToken = '';
    $chatId = '';
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if ($row['key'] === 'bot_token') $botToken = $row['value'];
        if ($row['key'] === 'chat_id') $chatId = $row['value'];
    }
    
    if ($botToken && $chatId) {
        echo json_encode(['status' => 'success', 'bot_token' => $botToken, 'chat_id' => $chatId]);
    } else {
        // Fallback to environment if database empty
        $envBotToken = getenv('BOT_TOKEN') ?: '';
        $envChatId = getenv('CHAT_ID') ?: '';
        if ($envBotToken && $envChatId) {
            echo json_encode(['status' => 'success', 'bot_token' => $envBotToken, 'chat_id' => $envChatId]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Bot not configured. Please set up in settings.']);
        }
    }
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
