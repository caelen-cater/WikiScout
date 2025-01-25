<?php
require_once '../../config.php';

$server = $servers[array_rand($servers)];

function report_error($message, $code, $trace, $user_id, $severity) {
    global $server, $apikey, $webhook;

    $errorData = [
        'message' => $message,
        'code' => $code,
        'trace' => $trace,
        'user_id' => $user_id,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
        'device_info' => php_uname(),
        'server' => $_SERVER['SERVER_NAME'] ?? 'unknown',
        'request_url' => $_SERVER['REQUEST_URI'] ?? 'unknown',
        'request_method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
        'request_headers' => getallheaders(),
        'request_parameters' => $_GET,
        'request_body' => file_get_contents('php://input'),
        'metadata' => [
            'team_number' => $_GET['team'] ?? null,
            'season' => date('Y')
        ],
        'severity' => $severity,
        'webhook_url' => $webhook,
        'webhook_content' => "An error (:error_id) occurred with :trace by user :user_id with error ':message' and code :code at :timestamp"
    ];

    $errorUrl = "https://$server/v2/data/error/";
    $errorHeaders = [
        "Authorization: Bearer $apikey",
        "Content-Type: application/json"
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $errorUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $errorHeaders);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($errorData));

    $response = curl_exec($ch);
    curl_close($ch);

    // Send webhook notification
    $webhookData = [
        'content' => "An error (:error_id) occurred with :trace by user :user_id with error ':message' and code :code at :timestamp"
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $webhook);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($webhookData));

    curl_exec($ch);
    curl_close($ch);
}

header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    report_error('Method Not Allowed', 405, __FILE__ . ':' . __LINE__, null, 'medium');
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

// Check authentication
$token = $_COOKIE['auth'] ?? null;
if (!$token) {
    http_response_code(401);
    report_error('Unauthorized', 401, __FILE__ . ':' . __LINE__, null, 'high');
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Validate user and team number
$authUrl = "https://$server/v2/auth/user/";
$authHeaders = [
    "Authorization: Bearer $apikey",
    "Token: $token"
];

$authCh = curl_init();
curl_setopt($authCh, CURLOPT_URL, $authUrl);
curl_setopt($authCh, CURLOPT_RETURNTRANSFER, true);
curl_setopt($authCh, CURLOPT_HTTPHEADER, $authHeaders);

$authResponse = curl_exec($authCh);
$authHttpCode = curl_getinfo($authCh, CURLINFO_HTTP_CODE);
curl_close($authCh);

$user_id = null;
if ($authHttpCode === 200) {
    $authData = json_decode($authResponse, true);
    $user_id = $authData['user']['id'] ?? null;
}

if ($authHttpCode === 401) {
    http_response_code(401);
    report_error('Unauthorized', 401, __FILE__ . ':' . __LINE__, $user_id, 'high');
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if ($authHttpCode !== 200) {
    http_response_code($authHttpCode);
    report_error('Authentication failed', $authHttpCode, __FILE__ . ':' . __LINE__, $user_id, 'high');
    echo json_encode(['error' => 'Authentication failed']);
    exit;
}

$eventId = $_GET['event'] ?? null;

if (!$eventId) {
    http_response_code(400);
    report_error('Missing event ID', 400, __FILE__ . ':' . __LINE__, $user_id, 'medium');
    echo json_encode(['error' => 'Missing event ID']);
    exit;
}

// Remove any unwanted characters
$eventId = preg_replace('/[^a-zA-Z0-9]/', '', $eventId);

// Instead of making API call, generate demo teams 1-50
$teams = range(1, 50);

echo json_encode(['teams' => $teams]);
?>