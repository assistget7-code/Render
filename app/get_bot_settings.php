<?php
// ============================================
// API: Get Bot Settings from Environment
// ============================================

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$botToken = getenv('BOT_TOKEN') ?: '';
$chatId = getenv('CHAT_ID') ?: '';

if ($botToken && $chatId) {
    echo json_encode([
        'status' => 'success',
        'bot_token' => $botToken,
        'chat_id' => $chatId
    ]);
} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'Bot not configured. Set BOT_TOKEN and CHAT_ID in Render environment variables.'
    ]);
}
?>
